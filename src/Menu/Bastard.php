<?php

namespace Menu;
# Плагин был написан на отъе*ись, так что претензии на счет кода идут знаете куда)

use pocketmine\plugin\PluginBase;
use pocketmine\command\{Command, CommandSender};
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\MainLogger;
use pocketmine\item\Item;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;

use jojoe77777\FormAPI\CustomForm;
use Menu\Task\CpsTask;
use Menu\Task\PingTask;

use function array_unshift;
use function array_pop;
use function microtime;
use function round;
use function count;
use function array_filter;

class Bastard extends PluginBase implements Listener {

   private const ARRAY_MAX_SIZE = 100;
      
   private $clicksData = [];
   public function onEnable()
   {
      MainLogger::getLogger()->info("§7Загружаем плагин...\n");
      sleep(1);
      if (!is_dir($this->getDataFolder() . "database")) {
         @mkdir($this->getDataFolder() . "database");
         MainLogger::getLogger()->info("§7Создаем базу данных статистики...\n");
         sleep(2);
      }
      Server::getInstance()->getPluginManager()->registerEvents($this, $this);

      $this->getScheduler()->scheduleRepeatingTask(new CpsTask($this), 1); # да-да, кто-то мб скажет что таск в 0.05 секунду это пи*дец как глупо, но, кпс иначе нормально показать не получится... Или получится, хз короче)
      $this->getScheduler()->scheduleRepeatingTask(new PingTask($this), 20); #20 тиков = 1 секунда, тут можешь изменить время на показ пинга)
      # можно было конечно и на пинг и на кпс один таймер запустить, но я подумал что незачем) можете если что так и сделать)))

      $this->database = new \SQLite3($this->getDataFolder() . "database/valuess.db");
      $this->database->query("CREATE TABLE IF NOT EXISTS valuess(name TEXT NOT NULL, maxcps INTEGER NOT NULL, minping INTEGER NOT NULL, pingsetting TEXT NOT NULL, cpssetting TEXT NOT NULL);");
	
   }

   public function onCommand(CommandSender $player, Command $command, string $label, array $args): bool
   {
      if ($command->getName() == "stats") {
         if (!$player instanceof Player) {
            $player->sendMessage("Пиши эту команду в игре!");
         } else {
               $this->GiveBookStat($player);
         }
      }
      if ($command->getName() == "onoroff") {
         if (!$player instanceof Player) {
            $player->sendMessage("Пиши эту команду в игре!");
         } else {
            $this->Settings($player);
         }
      }
      return true;
   }

   public function GiveBookStat(Player $player)
   {
      $maxcps = $this->getValue($player, "maxcps");
      $minping= $this->getValue($player, "minping");
      $ping = $player->getPing();
      $scps = ($maxcps + 1) / 2; # 1 - минимальный кпс соответственно, можно было и указать 3, но, хз чет...
      $sping = ($ping + $minping) / 2;
      $name = $player->getName();
      $address = $player->getAddress(); 
      $xuid = $player->getXuid(); # хз зачем это, да и всем честно говоря поеабть на такую ху*ню...

      $item = Item::get(Item::WRITTEN_BOOK, 0, 1);
      $item->setTitle("Stats");
      $item->setPageText(0, "Мини-Профиль игрока $name\n\nТекущий пинг: $ping\nЛучший пинг: $minping\nСредний пинг: $sping\n\nМаксимальный CPS: $maxcps\nСредний CPS: $scps\n\nАйпи-Адрес: $address\nXUID: $xuid");
      $item->setAuthor("Bastard");
      $player->getInventory()->addItem($item);
   }

   public function Settings($player)
   {
      $form = new CustomForm(function (Player $player, array $data = null) {
         $result = $data;
         if ($result == null) {
            return true;
         }
         if ($data[1] == false) {
            if ($this->getValue($player, "cpssetting") == "on") {

               $this->addValue($player, "cpssetting", "off");
            }
         } else {
            $this->addValue($player, "cpssetting", "on");
         }
         if ($data[2] == false) {
            if ($this->getValue($player, "pingsetting") == "on") {

               $this->addValue($player, "pingsetting", "off");
            }
         } else {
            $this->addValue($player, "pingsetting", "on");
         }
      });
      $form->addLabel("Эххх, вот бы мне кунчика... Ой, тоесть тянку*");
      if ($this->getValue($player, "cpssetting") == "on") {
         $form->addToggle("CPS - Включен", true);
      } else {
         $form->addToggle("CPS - Отключен", false);
      }
      if ($this->getValue($player, "pingsetting") == "on") {
         $form->addToggle("PING - Включен", true);
      } else {
         $form->addToggle("PING - Отключен", false);
      }
      $form->sendToPlayer($player);
   }

   public function updateMaxCps($player)
   {
      $maxcps = $this->getCPS($player);
      $top = $this->getValue($player, "maxcps");
      if ($maxcps > $top) {
         $this->addValue($player, "maxcps", $maxcps);
      }
   }
   public function updateMinPing($player)
   {
      $ping = $player->getPing();
      $top = $this->getValue($player, "minping");
      if ($ping < $top) {
         $this->addValue($player, "minping", $ping);
      }
   }

   public function SetStartSettings(PlayerPreLoginEvent $event)
   {
		$name = mb_strtolower($event->getPlayer()->getName()); # вдруг какой-то пи*р зайдет с ненормальным ником. ну думаю поняли, кто понял, тот понял)
		if(!$this->database->query("SELECT * FROM valuess WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC)){
			$this->database->query("INSERT INTO valuess (name, maxcps, minping, pingsetting, cpssetting) VALUES ('$name', 0, 60, 'on', 'on');");
      }
   }

   public function addValue($player, $stat, $setting) # Может быть ты запутаешься когда захочешь что-нибудь редакнуть, но, поверь, я был в го*не и похуже....
	{
		$name = mb_strtolower($player->getName());
		$setting = strtolower($setting);
		$stat = strtolower($stat);
      $this->database->query("UPDATE `valuess` SET `" . $stat . "` = '" . $setting . "' WHERE `name` = '$name'");
    }

   public function getValue($player, $stat)
   {
      $name = mb_strtolower($player->getName());
      $stat = strtolower($stat);
      $result = $this->database->query("SELECT {$stat} FROM valuess WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
      return $result[$stat];
   }

   # Код кпс был спизжен, ой, тоесть заимствован*
   public function initPlayerClickData(Player $p): void
   {
      $this->clicksData[$p->getLowerCaseName()] = [];
   }

   public function addClick(Player $p): void
   {
      array_unshift($this->clicksData[$p->getLowerCaseName()], microtime(true));
      if (count($this->clicksData[$p->getLowerCaseName()]) >= self::ARRAY_MAX_SIZE) {
         array_pop($this->clicksData[$p->getLowerCaseName()]);
      }
   }

   public function getCps(Player $player, float $deltaTime = 1.0, int $roundPrecision = 1): float
   {
      if (!isset($this->clicksData[$player->getLowerCaseName()]) || empty($this->clicksData[$player->getLowerCaseName()])) {
         return 0.0;
      }
      $ct = microtime(true);
      return round(count(array_filter($this->clicksData[$player->getLowerCaseName()], static function (float $t) use ($deltaTime, $ct): bool {
         return ($ct - $t) <= $deltaTime;
      })) / $deltaTime, $roundPrecision);
   }

   public function removePlayerClickData(Player $p): void
   {
      unset($this->clicksData[$p->getLowerCaseName()]);
   }

   public function playerJoin(PlayerJoinEvent $e): void
   {
      $this->initPlayerClickData($e->getPlayer());
   }

   public function playerQuit(PlayerQuitEvent $e): void
   {
      $this->removePlayerClickData($e->getPlayer());
   }

   public function packetReceive(DataPacketReceiveEvent $e): void
   {
      if (
         isset($this->clicksData[$e->getPlayer()->getLowerCaseName()]) &&
         (
            ($e->getPacket()::NETWORK_ID === InventoryTransactionPacket::NETWORK_ID && $e->getPacket()->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) ||
            ($e->getPacket()::NETWORK_ID === LevelSoundEventPacket::NETWORK_ID && $e->getPacket()->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE) ||
            ($e->getPacket()::NETWORK_ID === PlayerActionPacket::NETWORK_ID && $e->getPacket()->action === PlayerActionPacket::ACTION_START_BREAK))
      ) {
         $this->addClick($e->getPlayer());
      }
   }

}