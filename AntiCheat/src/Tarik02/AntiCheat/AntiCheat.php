<?php
namespace Tarik02\AntiCheat;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

use pocketmine\event\Listener;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use EntityDamageByChildEntityEvent;

use Tarik02\AntiCheat\task\ExecuteTask;

class AntiCheat extends PluginBase implements Listener
{
	private $State = [  ];
	private $Damagers = [  ];

	/* STATE FUNCTIONS */
	public function playerName($Player)
	{
		if ($Player instanceof Player)
			return $Player->getName();
		return $Player;
	}
	public function getState($label,$Player,$default)
	{
		$n = $this->playerName($Player);
		if (!isset($this->State[$n]))
			return $default;
		if (!isset($this->State[$n][$label]))
			return $default;
		return $this->State[$n][$label];
	}
	public function setState($label,$Player,$val)
	{
		$n = $this->playerName($Player);
		if (!isset($this->State[$n]))
			$this->State[$n] = [];
		$this->State[$n][$label] = $val;
	}
	public function unsetState($label,$Player)
	{
		$n = $this->playerName($Player);
		if (!isset($this->State[$n]))
			return;
		if (!isset($this->State[$n][$label]))
			return;
		unset($this->State[$n][$label]);
	}

	public function getStates($label)
	{
		$States = [];

		foreach ($this->State as $Player => $Labels)
			if (isset($Labels[$label]))
				$States[$Player] = $Labels[$label];
		
		return $States;
	}
	public function unsetStates($label)
	{
		foreach ($this->getStates($label) as $Player => $Value)
			$this->unsetState($label,$Player);
	}

	/* EVENTS FUNCTIONS */
	public function onEntityDamage(EntityDamageEvent $event)
	{
		$Victim = $event->getEntity();
		if (($event instanceof EntityDamageByEntityEvent) && (!($event instanceof EntityDamageByChildEntityEvent)))
		{
			$Damager = $event->getDamager();

			if ($Damager instanceof Player)
			{
				if ($this->getState("damaged",$Damager,false))
				{
					$event->setCancelled(true);
					return;
				};
				if (($Damager->getGamemode() !== Player::CREATIVE) && ($Damager->distance($Victim) > 4))
				{
					$event->setCancelled(true);
					return;
				};
				$this->setState("damaged",$Damager,true);
				ExecuteTask::Execute($this,function() use($Damager)
					{
						$this->setState("damaged",$Damager,false);
					},6);
			};
		};
	}

	/* MISC FUNCTIONS */
	

	/* OTHER FUNCTIONS */

	/* PLUGIN FUNCTIONS */
	public function onEnable()
	{
		//$this->saveDefaultConfig();
		//$this->reloadConfig();
		
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
	}
	public function onDisable()
	{

	}
}