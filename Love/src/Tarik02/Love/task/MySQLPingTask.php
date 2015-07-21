<?php
namespace Tarik02\Love\task;

use pocketmine\scheduler\PluginTask;
use Tarik02\Love\Love;

class MySQLPingTask extends PluginTask
{
	private $database;
	
	public function __construct(Love $owner,\mysqli $database)
	{
		parent::__construct($owner);
		$this->database = $database;
	}

	public function onRun($currentTick)
	{
		$this->database->ping();
	}
}