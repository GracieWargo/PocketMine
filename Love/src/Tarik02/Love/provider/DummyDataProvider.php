<?
namespace Tarik02\Love\provider;

use Tarik02\Love\Love;

class DummyDataProvider implements DataProvider
{
	protected $plugin;

	public function __construct(Love $plugin) { $this->plugin = $plugin; $plugin->getLogger()->warning("Dummy Data Provider"); }
	
	public function getPair($Player) { return null; }
	public function isMarried($Player) { return null; }
	public function isPair($Player1,$Player2) { return null; }
	public function marry($Player1,$Player2) { return null; }
	public function divorce($Player1,$Player2) { return null; }
	public function addMessage($Player,$Message) { return null; }
	public function getMessages($Player) { return [  ]; };

	public function close() { return null; }
}