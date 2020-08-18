<?php
declare(strict_types=1);

namespace AlfaByte\Practice\Tasks;

use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\scheduler\Task;
use TheNewManu\Practice\Main;

class FTPSendTask extends Task {

    /** @var Main $plugin */
    private $plugin;

    /**
     * @param Main $plugin
     * @param Player $player
     * @param Level $from
     * @param Level $to
     */
    public function __construct(Main $plugin, Player $player, Level $from, Level $to) {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * @param int $tick
     * @return void
     */
    public function onRun(int $tick): void {
        $level = $this->getPlugin()->getServer()->getLevelByName($this->getPlugin()->getConfig()->get("floatingtext-level"));
        if(!$this->player->isOnline()) return;
        if($this->to === $level) {
            foreach($this->getPlugin()->floatingTexts as $type => $ft) {
                $ft->setInvisible(false);
                $this->from->addParticle($ft, [$this->player]);
            }
        }else{
            foreach($this->getPlugin()->floatingTexts as $type => $ft) {
                $ft->setInvisible(true);
                $this->from->addParticle($ft, [$this->player]);
            }
        }
    }

    /**
     * @return Main
     */
    public function getPlugin(): Main {
        return $this->plugin;
    }
}