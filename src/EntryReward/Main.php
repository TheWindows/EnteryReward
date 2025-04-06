<?php

namespace EntryReward;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\utils\Config;
use pocketmine\scheduler\ClosureTask;
use pocketmine\player\Player;
use onebone\economyapi\EconomyAPI;
use pocketmine\scheduler\TaskHandler;

class Main extends PluginBase implements Listener {

	private Config $playerData;
	/** @var array [uuid => ["task" => TaskHandler, "time" => int]] */
	private array $activeSessions = [];

	public function onEnable(): void {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->playerData = new Config($this->getDataFolder() . "players.yml", Config::YAML);
	}

	public function onDisable(): void {
		foreach($this->activeSessions as $data) {
			$data["task"]->cancel();
		}
		$this->playerData->save();
	}

	public function onJoin(PlayerJoinEvent $event): void {
		$player = $event->getPlayer();
		$uuid = $player->getUniqueId()->toString();

		$rewardCount = $this->playerData->get($uuid, 0);

		if($rewardCount < 5) {
			$this->startPlaytimeTracking($player, $uuid, $rewardCount);
		}
	}

	public function onQuit(PlayerQuitEvent $event): void {
		$uuid = $event->getPlayer()->getUniqueId()->toString();
		if(isset($this->activeSessions[$uuid])) {
			$this->activeSessions[$uuid]["task"]->cancel();
			unset($this->activeSessions[$uuid]);
		}
	}

	private function startPlaytimeTracking(Player $player, string $uuid, int $currentCount): void {
		if(isset($this->activeSessions[$uuid])) {
			$this->activeSessions[$uuid]["task"]->cancel();
		}

		$this->activeSessions[$uuid] = [
			"time" => 0,
			"task" => $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($player, $uuid, &$currentCount): void {
				if(!$player->isOnline()) {
					unset($this->activeSessions[$uuid]);
					return;
				}

				$this->activeSessions[$uuid]["time"]++;
				if($this->activeSessions[$uuid]["time"] >= 72000) {
					$this->activeSessions[$uuid]["time"] = 0;

					if($currentCount >= 5) {
						$this->activeSessions[$uuid]["task"]->cancel();
						unset($this->activeSessions[$uuid]);
						return;
					}

					$reward = mt_rand(10000, 20000);
					EconomyAPI::getInstance()->addMoney($player, $reward);

					$currentCount++;
					$this->playerData->set($uuid, $currentCount);
					$this->playerData->save();

					$player->sendMessage("§aYou Received $reward Money As Reward For Playing The Game For 1 Hour!");

					if($currentCount >= 5) {
						$this->activeSessions[$uuid]["task"]->cancel();
						unset($this->activeSessions[$uuid]);
						$player->sendMessage("§eMaximum rewards reached!");
					}
				}
			}), 1)
		];
	}
}