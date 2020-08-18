<?php
declare(strict_types=1);

namespace AlfaByte\Practice;

use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use TheNewManu\Practice\Main;
use TheNewManu\Practice\Tasks\FixMenuTask;

class Arena {

    const GAME_IDLE = 0;
    const GAME_STARTING = 1;
    const GAME_RUNNING = 2;

    /** @var Main $plugin */
    private $plugin;
    
    /** @var string $name */
    private $name;
    
    /** @var string $world */
    private $world;
    
    /** @var array $spawns */
    private $spawns;
    
    /** @var string $kit */
    private $kit;
    
    /** @var string $type */
    private $type;
    
    /** @var int $countdown */
    private $countdown;
    
    /** @var int $status */
    public $state = self::GAME_IDLE;
    
    /** @var Player[] $players */
    private $players = [];
    
    /** @var array $cps */
    private $cps = [];
    
    /** @var array $hits */
    private $hits = [];
    
    /** @var array $blockPlaces */
    private $blockPlaces = [];

    /**
     * @param Main $plugin
     * @param string $name
     * @param string $world
     * @param array $spawns
     * @param string $kit
     * @param string $type
     */
    public function __construct(Main $plugin, string $name, string $world, array $spawns, string $kit, string $type) {
        $this->plugin = $plugin;
        $this->name = $name;
        $this->world = $world;
        $this->spawns = $spawns;
        $this->kit = $kit;
        $this->type = $type;
        $this->countdown = $this->getPlugin()->getCountdown();
    }

    public function tick(): void {
        if($this->isIdle()) {
            $this->broadcastPopup(TextFormat::RED . "Players Needed: 2");
        }
        if($this->isStarting()) {
            if($this->countdown === 0) $this->start();
            $this->broadcastTitle(TextFormat::RED . $this->countdown);
            $this->countdown--;
        }
        if($this->isRunning()) {
            foreach($this->getPlayers() as $player) {
                $cps = $this->getPlugin()->preciseCpsCounter->getCps($player);
                $this->addCps($player, $cps);
            }
        }
    }

    /**
     * @param Player $player
     */
    public function join(Player $player): void {
        if(!$this->isIdle()) {
            $player->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "The duel is on going!");
            return;
        }
        if($this->getPlugin()->getArenaByPlayer($player)) {
            $player->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "You alredy are in a match!");
            return;
        }
        $this->players[] = $player;
        $this->broadcastMessage(str_replace(["{player}", "{players}"], [$player->getName(), count($this->getPlayers())], $this->getPlugin()->getJoinMessage()));
        if(count($this->getPlayers()) === 2 && $this->isIdle()) $this->preStart();
    }

    /**
     * @param Player $player
     * @param $silent
     */
    public function quit(Player $player, $silent = false): void {
        if(!$this->inArena($player)) {
            return;
        }
        if(!$silent) $this->broadcastMessage(str_replace(["{player}", "{players}"], [$player->getName(), count($this->getPlayers()) - 1], $this->getPlugin()->getQuitMessage()));
        unset($this->players[array_search($player, $this->getPlayers(), true)]);
        if(!$this->isIdle() and count($this->getPlayers()) === 1) {
            $winner = reset($this->players);
            $this->eloUpdate($winner, $player);
            $this->statsUpdate($winner, $player);
            $this->saveDuelHistory($winner, $player);
            $this->stop(str_replace(["{winner}", "{loser}", "{elo_won}", "{elo_lost}"], [$winner->getName(), $player->getName(), $this->getPlugin()->getEloToAdd(), $this->getPlugin()->getEloToSub()], $this->getPlugin()->getFinishMessage()));
        }elseif(count($this->getPlayers()) < 2) {
            $this->state = self::GAME_IDLE;
        }
    }

    /**
     * @param Player $player
     */
    public function closePlayer(Player $player): void {
        if(!$this->inArena($player)) {
            return;
        }
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->setHealth($player->getMaxHealth());
        $player->setFood($player->getMaxFood());
        $player->removeAllEffects();
        unset($this->players[array_search($player, $this->getPlayers(), true)]);
        $player->teleport($this->getPlugin()->getServer()->getDefaultLevel()->getSpawnLocation());
        $this->getPlugin()->getScheduler()->scheduleDelayedTask(new FixMenuTask($this->getPlugin(), $player), 20);
    }

    public function preStart(): void {
        $players = $this->getPlayers();
        foreach($this->getPlayers() as $player) {
            $this->occupiedSpawns[$player->getLowerCaseName()] = $spawn = array_shift($this->spawns);
            $player->teleport(new Position($spawn[0], $spawn[1], $spawn[2], $this->getWorld()));
            $player->setGamemode($player::SURVIVAL);
            $player->setHealth($player->getMaxHealth());
            $player->setFood($player->getMaxFood());
            $player->removeAllEffects();
            $player->getInventory()->clearAll();
        }
        $this->state = self::GAME_STARTING;
    }

    public function start(): void {
        foreach($this->getPlayers() as $player) {
            $this->hits[$player->getName()] = 0;
            $this->getPlugin()->addKit($player, $this->getKit());
            $player->addTitle(TextFormat::YELLOW . "Match started", TextFormat::RED . "Enjoy!");
        }
        $this->state = self::GAME_RUNNING;
    }

    /**
     * @param string $message
     */
    public function stop(string $message): void {
        if(!$this->isRunning()) {
            return;
        }
        foreach($this->getPlayers() as $player) {
            $this->closePlayer($player);
        }
        foreach($this->blockPlaces as $vector3) {
            $this->getWorld()->setBlock($vector3, BlockFactory::get(Block::AIR));
        }
        $this->getPlugin()->getServer()->broadcastMessage($this->getPlugin()->getPrefix() . $message);
        $this->players = [];
        $this->cps = [];
        $this->hits = [];
        $this->blockPlaces = [];
        $this->spawns += array_values($this->occupiedSpawns);
        $this->occupiedSpawns = [];
        $this->countdown = $this->getPlugin()->getCountdown();
        $this->state = self::GAME_IDLE;
    }

    /**
     * @param Player $winner
     * @param Player $loser
     * @return void
     */
    public function eloUpdate(Player $winner, Player $loser): void {
        if($this->getType() !== "ranked") {
            return;
        }
        $kit = $this->getKit();
        $winnerName = $winner->getName();
        $loserName = $loser->getName();
        $winnerRankBefore = $this->getPlugin()->getRank($winnerName);
        $loserRankBefore = $this->getPlugin()->getRank($loserName);
        $eloToAdd = (int)$this->getPlugin()->getEloToAdd();
        $eloToSub = (int)$this->getPlugin()->getEloToSub();
        $winnerElo = (int)$this->getPlugin()->getPlayers()->getNested("$winnerName.$kit");
        $loserElo = (int)$this->getPlugin()->getPlayers()->getNested("$loserName.$kit");
        $loserEloNew = (($loserElo - $eloToSub) >= 0) ? $loserElo - $eloToSub : 0;
        $this->getPlugin()->getPlayers()->setNested("$winnerName.$kit", $winnerElo + $eloToAdd);
        $this->getPlugin()->getPlayers()->setNested("$loserName.$kit", $loserEloNew);
        $this->getPlugin()->getPlayers()->save();
        $winnerRankAfter = $this->getPlugin()->getRank($winnerName);
        $loserRankAfter = $this->getPlugin()->getRank($loserName);
        if($winnerRankAfter !== $winnerRankBefore) $this->getPlugin()->pureChat->setPrefix($this->getPlugin()->getRankPrefix($winnerName), $winner);
        if($loserRankAfter !== $loserRankBefore) $this->getPlugin()->pureChat->setPrefix($this->getPlugin()->getRankPrefix($loserName), $loser);
    }

    /**
     * @param Player $winner
     * @param Player $loser
     * @return void
     */
    public function statsUpdate(Player $winner, Player $loser): void {
        $kit = $this->getKit();
        $winnerName = $winner->getName();
        $loserName = $loser->getName();
        $wins = (int)$this->getPlugin()->getPlayersInfo()->getNested("$winnerName.wins");
        $loses= (int)$this->getPlugin()->getPlayersInfo()->getNested("$loserName.loses");
        $this->getPlugin()->getPlayersInfo()->setNested("$winnerName.$kit.wins", $wins + 1);
        $this->getPlugin()->getPlayersInfo()->setNested("$loserName.$kit.loses", $loses + 1);
        if($this->getType() === "ranked") {
            $winnerRankeds = (int)$this->getPlugin()->getPlayersInfo()->getNested("$winnerName.ranked-played");
            $loserRankeds = (int)$this->getPlugin()->getPlayersInfo()->getNested("$loserName.ranked-played");
            $this->getPlugin()->getPlayersInfo()->setNested("$winnerName.ranked-played", $winnerRankeds + 1);
            $this->getPlugin()->getPlayersInfo()->setNested("$loserName.ranked-played", $loserRankeds + 1);
        }
        $this->getPlugin()->getPlayersInfo()->save();
    }

    /**
     * @param Player $winner
     * @param Player $loser
     * @return void
     */
    public function saveDuelHistory(Player $winner, Player $loser): void {
        $winnerName = $winner->getName();
        $loserName = $loser->getName();
        $winnerItems = ["0:0:0"];
        $loserItems = ["0:0:0"];
        $players = $this->getPlugin()->getPlayerDuels()->getAll();
        $winnerArmor = [
            $winner->getArmorInventory()->getHelmet()->getId(),
            $winner->getArmorInventory()->getChestplate()->getId(),
            $winner->getArmorInventory()->getLeggings()->getId(),
            $winner->getArmorInventory()->getBoots()->getId(),
        ];
        $loserArmor = [
            $loser->getArmorInventory()->getHelmet()->getId(),
            $loser->getArmorInventory()->getChestplate()->getId(),
            $loser->getArmorInventory()->getLeggings()->getId(),
            $loser->getArmorInventory()->getBoots()->getId(),
        ];
        foreach($winner->getInventory()->getContents() as $item) {
            $winnerItems = [implode(":", [$item->getId(), $item->getDamage(), $item->getCount()])];
        }
        foreach($loser->getInventory()->getContents() as $item) {
            $loserItems = [implode(":", [$item->getId(), $item->getDamage(), $item->getCount()])];
        }
        if(isset($players[$winnerName][$loserName])) {
            $this->getPlugin()->getPlayerDuels()->removeNested("$winnerName.$loserName");
        }
        if(isset($players[$loserName][$winnerName])) {
            $this->getPlugin()->getPlayerDuels()->removeNested("$loserName.$winnerName");
        }
        $winnerInformations = [
            "kit" => $this->getKit(),
            "winner" => $winnerName,
            "date" => date("d/m/Y | H:i:s"),
            "my-stats" => [
                "items" => $winnerItems,
                "armor" => $winnerArmor,
                "life" => intval($winner->getHealth()),
                "food" => intval($winner->getFood()),
                "ping" => $winner->getPing(),
                "cps" => $this->getCpsAverage($winnerName),
                "hits" => $this->hits[$winnerName]
            ],
            "his-stats" => [
                "items" => $loserItems,
                "armor" => $loserArmor,
                "life" => intval($loser->getHealth()),
                "food" => intval($loser->getFood()),
                "ping" => $loser->getPing(),
                "cps" => $this->getCpsAverage($loserName),
                "hits" => $this->hits[$loserName]
            ]
        ];
        $loserInformations = [
            "kit" => $this->getKit(),
            "winner" => $winnerName,
            "date" => date("d/m/Y | H:i:s"),
            "my-stats" => [
                "items" => $loserItems,
                "armor" => $loserArmor,
                "life" => intval($loser->getHealth()),
                "food" => intval($loser->getFood()),
                "ping" => $loser->getPing(),
                "cps" => $this->getCpsAverage($loserName),
                "hits" => $this->hits[$loserName]
            ],
            "his-stats" => [
                "items" => $winnerItems,
                "armor" => $winnerArmor,
                "life" => intval($winner->getHealth()),
                "food" => intval($winner->getFood()),
                "ping" => $winner->getPing(),
                "cps" => $this->getCpsAverage($winnerName),
                "hits" => $this->hits[$winnerName]
            ]
        ];
        $this->getPlugin()->getPlayerDuels()->setNested("$winnerName.$loserName", $winnerInformations);
        $this->getPlugin()->getPlayerDuels()->setNested("$loserName.$winnerName", $loserInformations);
        $this->getPlugin()->getPlayerDuels()->save();
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array {
        return $this->players;
    }

    /**
     * @return bool
     */
    public function isIdle(): bool {
        return $this->state == self::GAME_IDLE;
    }

    /**
     * @return bool
     */
    public function isStarting(): bool {
        return $this->state == self::GAME_STARTING;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool {
        return $this->state == self::GAME_RUNNING;
    }
    
    /**
     * @return string
     */
    public function getStatus(): string {
        if($this->isIdle()) return "Idle";
        if($this->isStarting()) return "Starting";
        if($this->isRunning()) return "Running";
    }

    /**
     * @param Player $player
     */
    public function inArena(Player $player) {
        return in_array($player, $this->getPlayers(), true);
    }

    /**
     * @return Level
     */
    public function getWorld(): Level {
        return $this->getPlugin()->getServer()->getLevelByName($this->getWorldName());
    }

    /**
     * @return string
     */
    public function getWorldName(): string {
        return $this->world;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getKit(): string {
        return $this->kit;
    }

    /**
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getCountdown(): int {
        return $this->countdown;
    }
    
    /**
     * @param Player $player
     * @param float $cps
     */
    public function addCps(Player $player, float $cps) {
        $this->cps[$player->getName()][] = $cps;
    }
    
    /**
     * @param string $name
     * @return float
     */
    public function getCpsAverage(string $name): float {
        return (array_sum($this->cps[$name]) / count($this->cps[$name]));
    }
    
    /**
     * @param Player $player
     */
    public function addHit(Player $player) {
        $this->hits[$player->getName()]++;
    }
    
    /**
     * @return array
     */
    public function getPlacedBlocks(): array {
        return $this->blockPlaces;
    }
    
    /**
     * @param $x
     * @param $y
     * $param $z
     */
    public function addPlacedBlock($x, $y, $z) {
        $this->blockPlaces[] = new Vector3($x, $y, $z);
    }
    
    /**
     * @param $x
     * @param $y
     * @param $z
     */
    public function removePlacedBlock($x, $y, $z) {
        $vector3 = new Vector3($x, $y, $z);
        unset($this->blockPlaces[array_search($vector3, $this->blockPlaces)]);
    }

    /**
     * @param string $msg
     */
    public function broadcastMessage(string $msg) {
        foreach($this->getPlayers() as $player) {
            $player->sendMessage($this->getPlugin()->getPrefix() . $msg);
        }
    }

    /**
     * @param string $msg
     */
    public function broadcastPopup(string $msg) {
        foreach($this->getPlayers() as $player) {
            $player->sendPopup($msg);
        }
    }

    /**
     * @param string $msg
     */
    public function broadcastTitle(string $msg) {
        foreach($this->getPlayers() as $player) {
            $player->addTitle($msg);
        }
    }

    /**
     * @return Main
     */
    public function getPlugin(): Main {
        return $this->plugin;
    }
}
