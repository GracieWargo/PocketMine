<?php
namespace Tarik02\Love\provider;

use Tarik02\Love\Love;
use Tarik02\Love\task\MySQLPingTask;

class MySQLDataProvider implements DataProvider
{
	protected $plugin;
	protected $database;

	public function __construct(Love $plugin)
	{
		$this->plugin = $plugin;

		$config = $this->plugin->getConfig()->get("mysql");
		if ((!isset($config["host"])) || (!isset($config["user"])) || (!isset($config["password"])) || (!isset($config["database"])))
		{
			$this->plugin->getLogger()->critical("Invalid MySQL settings");
			$this->plugin->setDataProvider(new DummyDataProvider($this->plugin));
			return;
		};
		
		$this->database = new \mysqli($config["host"], $config["user"], $config["password"], $config["database"], isset($config["port"]) ? $config["port"] : 3306);
		if ($this->database->connect_error)
		{
			$this->plugin->getLogger()->critical("Couldn't connect to MySQL: ".$this->database->connect_error);
			$this->plugin->setDataProvider(new DummyDataProvider($this->plugin));
			return;
		};

		$resource = $this->plugin->getResource("mysql.sql");
		foreach (explode(";",stream_get_contents($resource)) as $query)
			if (trim($query) != "")
				$this->database->query($query);
		fclose($resource);

		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask(new MySQLPingTask($this->plugin,$this->database),600);
	}
	
	public function getPair($Player)
	{
		$Player = strtolower($Player);
		$result = $this->database->query("SELECT `user1`,`user2` FROM `l_pairs` WHERE LOWER(`user1`)='".$this->database->escape_string($Player)."' OR LOWER(`user2`)='".$this->database->escape_string($Player)."'");
		if ($result instanceof \mysqli_result)
		{
			$data = $result->fetch_assoc();
			$result->free();
			if ((isset($data["user1"])) and (strtolower($data["user1"]) === $Player))
			{
				return $data["user2"];
			}
			elseif ((isset($data["user2"])) and (strtolower($data["user2"]) === $Player))
			{
				return $data["user1"];
			};
		}
		return null;
	}
	public function isMarried($Player)
	{
		return $this->getPair($Player) !== null;
	}
	public function isPair($Player1,$Player2)
	{
		return $this->getPair($Player1) == $Player2;
	}
	public function marry($Player1,$Player2)
	{
		return $this->database->query("INSERT INTO `l_pairs`(`user1`,`user2`) VALUES('".$this->database->escape_string($Player1)."','".$this->database->escape_string($Player2)."')");
	}
	public function divorce($Player1,$Player2 = null)
	{
		$Player1 = strtolower($Player1);
		if ($Player2 === null)
			if (($Player2 = $this->getPair($Player1)) === null)
				return false;
		$Player2 = strtolower($Player2);
		return $this->database->query("DELETE FROM `l_pairs` WHERE (LOWER(`user1`)='".$this->database->escape_string($Player1)."' AND LOWER(`user2`)='".$this->database->escape_string($Player2)."') OR (LOWER(`user1`)='".$this->database->escape_string($Player2)."' AND LOWER(`user2`)='".$this->database->escape_string($Player1)."')");
	}
	public function addMessage($Player,$Message)
	{
		if (($Player2 = $this->getPair($Player)) === null)
			return null;
		return $this->database->query("INSERT INTO `l_messages`(`user1`,`user2`,`message`) VALUES('".$this->database->escape_string($Player)."','".$this->database->escape_string($Player2)."','".$this->database->escape_string($Message)."')");		
	}
	public function getMessages($Player)
	{
		$r = [  ];
		$result = $this->database->query("SELECT `user2`,`message` FROM `l_messages` WHERE LOWER(`user1`)=LOWER('".$this->database->escape_string($Player)."')");
		if ($result instanceof \mysqli_result)
		{
			while ($data = $result->fetch_assoc())
			{
				if ((!isset($data["user2"])) || (!isset($data["message"])))
					return [  ];
				$r[$data["user2"]] = $data["message"];
			};
			$result->free();
		};
		$this->database->query("DELETE FROM `l_messages` WHERE LOWER(`user1`)=LOWER('".$this->database->escape_string($Player)."')");
		return $r;
	}

	public function close()
	{
		$this->database->close();
	}
}