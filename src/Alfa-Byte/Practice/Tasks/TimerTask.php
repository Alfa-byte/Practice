<?php
declare(strict_types=1);

namespace AlfaByte\Practice\Tasks;

use pocketmine\tile\Sign;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use TheNewManu\Practice\Main;

class TimerTask extends Task {

    /** @var Main $plugin */
    private $plugin;

    /**
     * @param Main $plugin
     */
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param int $tick
     * @return void
     */
    public function onRun(int $tick): void {
        foreach ($this->getPlugin()->getArenas() as $arena) {
            $arena->tick();
        }
        $playersInfo = $this->getPlugin()->getPlayersInfo()->getAll();
        $playerDuels = $this->getPlugin()->getPlayerDuels()->getAll();
        if(!isset($playerDuels["date"])) {
            $this->getPlugin()->getPlayerDuels()->set("date", date("d/m/Y"));
            $this->getPlugin()->getPlayerDuels()->save();
        }
        if(isset($playerDuels["date"]) and $playerDuels["date"] !== date("d/m/Y")) {
            foreach($playerDuels as $name => $info) {
                $this->getPlugin()->getPlayerDuels()->remove($name);
            }
            $this->getPlugin()->getPlayerDuels()->save();
            foreach($playersInfo as $name => $info) {
                $this->getPlugin()->getPlayersInfo()->setNested("$name.ranked-played", 0);
            }
            $this->getPlugin()->getPlayersInfo()->save();
        }
        $players = $this->getPlugin()->getPlayers()->getAll();
        $level = $this->getPlugin()->getServer()->getLevelByName($this->getPlugin()->getConfig()->get("floatingtext-level"));
        foreach($this->getPlugin()->floatingTexts as $kit => $ft) {
            $text = "";
            $tops = [];
            $number = 0;
            foreach($players as $name => $kits) {
                $tops[$name] = $kits[$kit];
            }
            if(is_array($tops)) arsort($tops);
            foreach(array_slice($tops, 0, 10) as $name => $points) {
                $number++;
                $text .= TextFormat::YELLOW . $number . ") " . $name . TextFormat::AQUA . " » " . TextFormat::YELLOW . $points . TextFormat::EOL;
            }
            $ft->setTitle(TextFormat::BOLD . TextFormat::BLUE . "Practice Top: " . $kit);
            $ft->setText($text);
            $level->addParticle($ft, $level->getPlayers());
        }
    }

    /**
     * @return Main
     */
    public function getPlugin(): Main {
        return $this->plugin;
    }
}