<?php
namespace Tarik02\Love\provider;

use pocketmine\utils\Config;
use Tarik02\Love\Love;

interface DataProvider
{
	public function getPair($Player);
	public function isMarried($Player);
	public function isPair($Player1,$Player2);
	public function marry($Player1,$Player2);
	public function divorce($Player1,$Player2);
	public function addMessage($Player,$Message);
	public function getMessages($Player);
	public function close();
};