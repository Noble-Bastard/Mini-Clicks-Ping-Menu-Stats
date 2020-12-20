<?php
declare(strict_types=1);

namespace Menu\Task;

use pocketmine\scheduler\Task;
use Menu\Bastard;

class PingTask extends Task {

	private $plugin;
  
	public function __construct(Bastard $plugin) {
		$this->plugin = $plugin;
	}

	public function onRun(int $currentTick): void
	{

		foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
			$ping = $player->getPing();
			if ($this->plugin->getValue($player, "pingsetting") == "on") {
				$player->sendPopup("Ping: $ping");
				$this->plugin->updateMinPing($player);
			}

			if ($ping >= 300) {
				$player->close("", "БРО У ТЕБЯ ИНЕТ ХУЖЕ ЧЕМ БЛЯТЬ В СЕЛЕ, КУПИ НОРМАЛЬНЫЙ ПЖ!\nИЛИ ЗАПЛАЧУ ВАЩЕ");
			}
		}
	}
  
}
