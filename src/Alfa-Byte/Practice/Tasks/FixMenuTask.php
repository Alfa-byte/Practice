<?php
declare(strict_types=1);

namespace AlfaByte\Practice\Tasks;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use TheNewManu\Practice\Main;

class FixMenuTask extends Task {

    /** @var Main */
    private $plugin;
    /** @var Player */
    private $player;

    /**
     * @param Main $plugin
     * @param Player $player
     */
    public function __construct(Main $plugin, Player $player) {
        $this->plugin = $plugin;
        $this->player = $player;
    }
    
    /**
     * @param int $tick
     */
    public function onRun(int $tick): void {
        $this->getPlugin()->sendMenu($this->player);
    }
    
    /**
     * @return Main
     */
    public function getPlugin(): Main {
        return $this->plugin;
    }
}
