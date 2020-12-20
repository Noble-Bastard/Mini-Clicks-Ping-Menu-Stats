<?php
declare(strict_types=1);

namespace Menu\Task;

use pocketmine\scheduler\Task;
use Menu\Bastard;

class CpsTask extends Task {

	private $plugin;
  
	public function __construct(Bastard $plugin) {
		$this->plugin = $plugin;
	}

	public function onRun(int $currentTick): void
	{

		foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
			$cpspopup = $this->plugin->getCPS($player);
			if ($this->plugin->getValue($player, "cpssetting") == "on") {
				$player->sendTip("CPS: $cpspopup");
				$this->plugin->updateMaxCps($player);
			}

			if ($cpspopup >= 30) {
				$player->close("", "ОФФАЙ АНТИКЛИКЕР!!!!!");
			}
		}
	}
  
}
