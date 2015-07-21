<?php
namespace Tarik02\Love;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

use pocketmine\item\Item;

use Tarik02\Love\provider\DataProvider;
use Tarik02\Love\provider\DummyDataProvider;
use Tarik02\Love\provider\MySQLDataProvider;

use pocketmine\event\Listener;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;


class Query
{
	public $time = 0;
	public $from = 0;
	public $to = 0;
};

class Love extends PluginBase implements Listener
{
	protected $provider;
	protected $messages = [];

	private $queries = array();

	private $commands = [ "marry" , "divorce" , "msg" , "gift" , "tp" , "who" , "yes" , "no" , "help" ];

	public function setDataProvider(DataProvider $provider)
	{
		$this->provider = $provider;
	}

	public function getDataProvider()
	{
		return $this->provider;
	}

	public function onPlayerJoin(PlayerJoinEvent $event)
	{
		$Player = $event->getPlayer();
		$Messages = $this->provider->getMessages($Player->getName());
		
		if (count($Messages) > 0)
		{
			$Player->sendMessage($this->getMessage("l.pair-messages"));
			foreach ($Messages as $Message)
				$Player->sendMessage($Message);
		};
	}

	public function onEntityDamage(EntityDamageEvent $event)
	{
		if (($event instanceof EntityDamageByEntityEvent) && (!($event instanceof EntityDamageByChildEntityEvent)))
		{
			if ((($Victim = $event->getEntity()) instanceof Player) && (($Damager = $event->getDamager()) instanceof Player))
			{
				if ($this->provider->isPair($Victim->getName(),$Damager->getName()))
				{
					if ((($Victim->getGamemode() == Player::SURVIVAL) || (($Victim->getGamemode() == Player::ADVENTURE))) && (($Damager->getGamemode() == Player::SURVIVAL) || (($Damager->getGamemode() == Player::ADVENTURE))))
					{
						$DamagerInventory = $Damager->getInventory();
						$VictimInventory = $Victim->getInventory();

						$Item = $DamagerInventory->getItemInHand();
						if (($Item) && ($Item->getId() !== 0))
						{
							$VictimInventory->addItem($VictimInventory->getItemInHand());
							$VictimInventory->setItemInHand($Item);
							$DamagerInventory->setItemInHand(Item::get(0));
						};
					};
					$event->setCancelled(true);
				};
			};
		};
	}
	
	public function onCommand(CommandSender $sender,Command $command,$label,array $args)
	{
		switch ($command->getName())
		{
			case "l":
				if ($sender instanceof Player)
				{
					if (count($args) === 0)
					{
						$sender->sendMessage($command->getDescription());
						return true;
					}
					else
					{
						$subcmd = array_shift($args);
						switch ($subcmd)
						{
							case "marry": return $this->marryCommand($sender,$args); break;
							case "divorce": return $this->divorceCommand($sender,$args); break;
							case "msg": return $this->msgCommand($sender,$args); break;
							case "tp": return $this->tpCommand($sender,$args); break;
							case "who": return $this->whoCommand($sender,$args); break;
							case "yes": return $this->yesCommand($sender,$args); break;
							case "no": return $this->noCommand($sender,$args); break;
							case "help": return $this->helpCommand($sender,$args); break;
							default: return false; break;
						}
					};
				}
				else
				{
					$sender->sendMessage(TextFormat::RED."This command only works in-game.");
					return true;
				};
				break;
		};

		return false;
	}

	function marryCommand(CommandSender $sender,array $args)
	{
		if (count($args) !== 1)
		{
			$sender->sendMessage($this->getMessage("l.marry.usage"));
			return true;
		};

		if ($this->provider->isMarried($sender->getName()))
		{
			$sender->sendMessage($this->getMessage("l.married"));
			return true;
		};

		if ($this->provider->isMarried($args[0]))
		{
			$sender->sendMessage($this->getMessage("l.is-married",$args[0]));
			return true;
		};

		if (strtolower($sender->getName()) == strtolower($args[0]))
		{
			$sender->sendMessage($this->getMessage("no-marry-you"));
			return true;
		};

		$this->updateQueries();

		if (isset($this->queries[strtolower($sender->getName())]))
		{
			$sender->sendMessage($this->getMessage("l.queried",$this->queries[strtolower($sender->getName())]->to));
			return true;
		};

		if (!($to = $this->getServer()->getPlayer($args[0])))
		{
			$sender->sendMessage($this->getMessage("l.offline"));
			return true;
		};

		$query = new Query();
		$query->time = time();
		$query->from = $sender->getName();
		$query->to = $to->getName();
		$this->queries[strtolower($sender->getName())] = $query;

		foreach ($this->getServer()->getLevels() as $Level)
			foreach ($Level->getPlayers() as $Player)
				if (($Player->getId() != $sender->getId()) && ($Player->getId() != $to->getId()))
					$Player->sendMessage($this->getMessage("l.query-all",$sender->getName(),$to->getName()));

		$sender->sendMessage($this->getMessage("l.you-query",$to->getName()));
		$to->sendMessage($this->getMessage("l.query",$sender->getName()));
		$to->sendMessage($this->getMessage("l.query-yes",$sender->getName()));
		$to->sendMessage($this->getMessage("l.query-no"));

		return true;
	}

	function divorceCommand(CommandSender $sender,array $args)
	{
		if (count($args) !== 0)
		{
			$sender->sendMessage($this->getMessage("l.divorce.usage"));
			return true;
		};

		if (!$this->provider->isMarried($sender->getName()))
		{
			$sender->sendMessage($this->getMessage("l.no-married"));
			return true;
		};

		$pair = $this->provider->getPair($sender->getName());
		if ($this->provider->divorce($sender->getName(),$pair))
		{
			$sender->sendMessage($this->getMessage("l.divorced",$pair));

			if ($Player = $this->getServer()->getPlayer($pair))
				$Player->sendMessage($this->getMessage("l.msg-divorced",$sender->getName()));
			foreach ($this->getServer()->getLevels() as $Level)
				foreach ($Level->getPlayers() as $Player)
					$Player->sendMessage($this->getMessage("l.all-divorced",$sender->getName(),$pair));
			return true;
		};
	}

	function msgCommand(CommandSender $sender,array $args)
	{
		if (count($args) === 0)
		{
			$sender->sendMessage($this->getMessage("l.msg.usage"));
			return true;
		};

		if (($pair = $this->provider->getPair($sender->getName())) === null)
		{
			$sender->sendMessage($this->getMessage("l.no-married"));
			return true;
		};

		if (!($Player = $this->getServer()->getPlayer($pair)))
		{
			$this->provider->addMessage($pair,"[".TextFormat::RED.$this->getMessage("l.you-pair").TextFormat::RESET."] <".TextFormat::BLUE.$sender->getName().TextFormat::RESET."> ".implode(" ",$args));
			return true;
		};

		$Player->sendMessage("[".TextFormat::RED.$this->getMessage("l.you-pair").TextFormat::RESET."] <".TextFormat::BLUE.$sender->getName().TextFormat::RESET."> ".implode(" ",$args));
		return true;
	}
	
	function tpCommand(CommandSender $sender,array $args)
	{
		if (count($args) !== 0)
		{
			$sender->sendMessage($this->getMessage("l.tp.usage"));
			return true;
		};

		if (($pair = $this->provider->getPair($sender->getName())) === null)
		{
			$sender->sendMessage($this->getMessage("l.no-married"));
			return true;
		};

		if (!($Player = $this->getServer()->getPlayer($pair)))
		{
			$sender->sendMessage($this->getMessage("l.pair-offline"));
			return true;
		};

		$sender->teleport($Player);
		return true;
	}
	
	function whoCommand(CommandSender $sender,array $args)
	{
		if ((count($args) !== 0) && (count($args) !== 1))
		{
			$sender->sendMessage($this->getMessage("l.who.usage"));
			return true;
		};
		if (count($args) !== 0)
			$args []= $sender->getName();

		if (($pair = $this->provider->getPair($args[0])) === null)
		{
			$sender->sendMessage($this->getMessage("l.no-is-married",$args[0]));
			return true;
		};

		$sender->sendMessage($this->getMessage("l.is-pair",$args[0],$pair));
		return true;
	}
	
	function yesCommand(CommandSender $sender,array $args)
	{
		if ((count($args) !== 0) && (count($args) !== 1))
		{
			$sender->sendMessage($this->getMessage("l.yes.usage"));
			return true;
		};

		if ($this->provider->isMarried($sender->getName()))
		{
			$sender->sendMessage($this->getMessage("l.married"));
			return true;
		};

		$this->updateQueries();

		$query = null;
		switch (count($args))
		{
			case 0:
				foreach ($this->queries as $from => $query2)
					if (strtolower($query2->to) == strtolower($sender->getName()))
					{
						$query = clone $query2;
						unset($this->queries[$from]);
					};
				break;
			case 1:
				if (isset($this->queries[strtolower($sender->getName())]))
				{
					$query = clone $this->queries[strtolower($sender->getName())];
					unset($this->queries[strtolower($sender->getName())]);
				};
				foreach ($this->queries as $from => $query2)
					if (strtolower($query2->to) == strtolower($sender->getName()))
					{
						unset($this->queries[strtolower($args[0])]);
					};
				break;
		};
		if ($query == null)
		{
			$sender->sendMessage($this->getMessage("l.no-queries"));
			return true;
		};

		if ($this->provider->marry($query->from,$query->to))
		{
			foreach ($this->getServer()->getLevels() as $Level)
				foreach ($Level->getPlayers() as $Player)
					$Player->sendMessage($this->getMessage("l.marry-msg",$query->from,$query->to));

			if ($Player = $this->getServer()->getPlayer($query->from))
				$Player->sendMessage($this->getMessage("l.query-on",$sender->getName()));
			return true;
		};
	}
	
	function noCommand(CommandSender $sender,array $args)
	{
		if (count($args) !== 0)
		{
			$sender->sendMessage($this->getMessage("l.no.usage"));
			return true;
		};

		$this->updateQueries();


		foreach ($this->queries as $from => $query)
			if (strtolower($query->to) == strtolower($sender->getName()))
			{
				if ($Player = $this->getServer()->getPlayer($query->from))
					$Player->sendMessage($this->getMessage("l.query-off",$sender->getName()));
				unset($this->queries[$from]);
			};
		return true;
	}

	function helpCommand(CommandSender $sender,array $args)
	{
		if (count($args) !== 0)
		{
			$sender->sendMessage($this->getMessage("l.no.usage"));
			return true;
		};

		foreach ($this->commands as $command)
			$sender->sendMessage($this->getMessage("l.".$command.".usage")." - ".$this->getMessage("l.".$command.".description"));
		return true;
	}


	private function updateQueries()
	{
		foreach ($this->queries as $key => $query)
		{
			if (time() - $query->time > $this->getConfig()->get("query-time"))
				unset($this->queries[$key]);
		};
	}

	private function parseMessages(array $messages)
	{
		$result = [];
		foreach ($messages as $key => $value)
			if (is_array($value))
				foreach($this->parseMessages($value) as $k => $v)
					$result[$key.".".$k] = $v;
			else
				$result[$key] = $value;
		return $result;
	}

	public function getMessage($key,...$args)
	{
		return isset($this->messages[$key]) ? vsprintf($this->messages[$key],$args) : $key;
	}

	public function onEnable()
	{
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->saveResource("messages.yml",false);
		$this->messages = $this->parseMessages((new Config($this->getDataFolder()."messages.yml"))->getAll());

		$lCommand = $this->getCommand("l");
		$lCommand->setUsage($this->getMessage("l.usage"));
		$lCommand->setDescription($this->getMessage("l.description"));
		$lCommand->setPermissionMessage($this->getMessage("l.permission"));

		switch (strtolower($this->getConfig()->get("dataProvider")))
		{
			case "mysql": $this->provider = new MySQLDataProvider($this); break;
			default: $this->provider = new DummyDataProvider($this); break;
		};
		if (!$this->provider)
			$this->provider = new DummyDataProvider($this);

		$this->getServer()->getPluginManager()->registerEvents($this,$this);
	}
	public function onDisable()
	{
		$this->getServer()->getPluginManager();
		$this->provider->close();
	}
}