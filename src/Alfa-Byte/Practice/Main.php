<?php
declare(strict_types=1);

namespace AlfaByte\Practice;

use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\command\ConsoleCommandSender;
use muqsit\invmenu\InvMenuHandler;
use TheNewManu\Practice\Other\UI;
use TheNewManu\Practice\Tasks\TimerTask;
use TheNewManu\Practice\Commands\DuelCommand;
use TheNewManu\Practice\Commands\PracticeCommand;

class Main extends PluginBase {

    /** @var Config $config */
    private $config;
    /** @var Config $arenasCfg */
    private $arenasCfg;
    /** @var  Arena[] $arenas */
    private $arenas = [];
    /** @var Listener $listener */
    private $listener;
    /** @var FloatingTextParticle[] $floatingTexts */
    public $floatingTexts = [];

    public function onEnable(): void {
        if(!$this->getServer()->isLevelGenerated($this->getConfig()->get("floatingtext-level"))) {
            $this->getLogger()->error("FloatingText-Level setted in the config isn't generated");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        if(!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
        $this->getServer()->getPluginManager()->registerEvents($this->listener = new Listener($this), $this);
        $this->getServer()->getCommandMap()->register(strtolower($this->getName()), new DuelCommand($this));
        $this->getServer()->getCommandMap()->register(strtolower($this->getName()), new PracticeCommand($this));
        $this->ui = new UI($this);
        $this->saveDefaultConfig();
        $this->loadAllConfig();
        $this->loadFloatingTexts();
        $this->pureChat = $this->getServer()->getPluginManager()->getPlugin("PureChat");
        $this->purePerms = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        $this->preciseCpsCounter = $this->getServer()->getPluginManager()->getPlugin("PreciseCpsCounter");
        foreach($this->getArenasConfig()->getAll() as $name => $arena) {
            $this->getServer()->loadLevel($arena["world"]);
            $this->addArena($name, $arena["world"], $arena["spawns"], $arena["kit"], $arena["type"]);
        }
        $this->getScheduler()->scheduleRepeatingTask(new TimerTask($this), 20);
    }

    public function onDisable() {
        foreach ($this->getArenas() as $arena) {
            $arena->stop("");
        }
    }

    /**
     * @return void
     */
    public function loadAllConfig(): void {
        $this->kits = new Config($this->getDataFolder() . "kits.yml", Config::YAML);
        $this->players = new Config($this->getDataFolder() . "players.yml", Config::YAML);
        $this->playersInfo = new Config($this->getDataFolder() . "players-info.yml", Config::YAML);
        $this->playerDuels = new Config($this->getDataFolder() . "player-duels.yml", Config::YAML);
        $this->arenasCfg = new Config($this->getDataFolder() . "arenas.yml");
    }

    /**
     * @return void
     */
    public function loadFloatingTexts(): void {
        foreach($this->getKits()->getAll() as $kit => $info) {
            if(isset($info["floating-text-top"])) {
                $coord = explode(":", $info["floating-text-top"]);
                $this->floatingTexts[$kit] = new FloatingTextParticle(new Vector3($coord[0], $coord[1], $coord[2]), "");
            }
        }
    }

    /**
     * @param Player $player
     */
    public function addKit(Player $player, string $kit): void {
        $kits = $this->getKits()->getAll();
        $player->getInventory()->clearAll();
        foreach($kits[$kit]["commands"] as $cmd) {
            $this->getServer()->dispatchCommand(new ConsoleCommandSender, str_replace("{player}", $player->getName(), $cmd));
        }
        foreach($kits[$kit]["items"] as $items) {
            $item = explode(":", $items);
            $itemAdd = ItemFactory::get((int)$item[0], (int)$item[1], (int)$item[2]);
            if(isset($item[3])) {
                $itemAdd->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName($item[3]), (int)$item[4]));
            }
            $player->getInventory()->addItem($itemAdd);
        }
        if(is_array(explode(":", (string)$kits[$kit]["helmet"]))) {
            $armor = explode(":", (string)$kits[$kit]["helmet"]);
            $helmet = ItemFactory::get((int)$armor[0]);
            if(isset($armor[1])) {
                $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName($armor[1]), (int)$armor[2]));
            }
        }else{
            $armor = $kits[$kit]["helmet"];
            $helmet = ItemFactory::get((int)$armor);
        }
        $player->getArmorInventory()->setHelmet($helmet);
        if(is_array(explode(":", (string)$kits[$kit]["chestplate"]))) {
            $armor = explode(":", (string)$kits[$kit]["chestplate"]);
            $chestplate = ItemFactory::get((int)$armor[0]);
            if(isset($armor[1])) {
                $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName($armor[1]), (int)$armor[2]));
            }
        }else{
            $armor = $kits[$kit]["chestplate"];
            $chestplate = ItemFactory::get((int)$armor);
        }
        $player->getArmorInventory()->setChestplate($chestplate);
        if(is_array(explode(":", (string)$kits[$kit]["leggings"]))) {
            $armor = explode(":", (string)$kits[$kit]["leggings"]);
            $leggings = ItemFactory::get((int)$armor[0]);
            if(isset($armor[1])) {
                $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName($armor[1]), (int)$armor[2]));
            }
        }else{
            $armor = $kits[$kit]["leggings"];
            $leggings = ItemFactory::get((int)$armor);
        }
        $player->getArmorInventory()->setLeggings($leggings);
        if(is_array(explode(":", (string)$kits[$kit]["boots"]))) {
            $armor = explode(":", (string)$kits[$kit]["boots"]);
            $boots = ItemFactory::get((int)$armor[0]);
            if(isset($armor[1])) {
                $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantmentByName($armor[1]), (int)$armor[2]));
            }
        }else{
            $armor = $kits[$kit]["boots"];
            $boots = ItemFactory::get((int)$armor);
        }
        $player->getArmorInventory()->setBoots($boots);
    }

    /**
     * @param Player $player
     * @return void
     */
    public function sendMenu(Player $player): void {
        $eloItem = explode(":", $this->getConfig()->get("elo-item"));
        $statsItem = explode(":", $this->getConfig()->get("stats-item"));
        $duelItem = explode(":", $this->getConfig()->get("duel-item"));
        $duelStickItem = explode(":", $this->getConfig()->get("duel-stick-item"));
        $duelHistoryItem = explode(":", $this->getConfig()->get("duel-history-item"));
        $duelRequestItem = explode(":", $this->getConfig()->get("duel-request-item"));
        $player->getInventory()->clearAll();
        $player->getInventory()->setItem((int)$eloItem[0], (ItemFactory::get((int)$eloItem[1])->setCustomName($eloItem[2])));
        $player->getInventory()->setItem((int)$statsItem[0], (ItemFactory::get((int)$statsItem[1])->setCustomName($statsItem[2])));
        $player->getInventory()->setItem((int)$duelItem[0], (ItemFactory::get((int)$duelItem[1])->setCustomName($duelItem[2])));
        $player->getInventory()->setItem((int)$duelStickItem[0], (ItemFactory::get((int)$duelStickItem[1])->setCustomName($duelStickItem[2])));
        $player->getInventory()->setItem((int)$duelHistoryItem[0], (ItemFactory::get((int)$duelHistoryItem[1])->setCustomName($duelHistoryItem[2])));
        $player->getInventory()->setItem((int)$duelRequestItem[0], (ItemFactory::get((int)$duelRequestItem[1])->setCustomName($duelRequestItem[2])));
    }

    /**
     * @param string $name
     * @param string $world
     * @param array $spawns
     * @param string $kit
     * @param string $type
     * @return Arena
     */
    public function addArena(string $name, string $world, array $spawns, string $kit, string $type): Arena {
        return $this->arenas[$name] = new Arena($this, $name, $world, $spawns, $kit, $type);
    }

    /**
     * @param string $playerName
     * @return int|null
     */
    public function getGlobalElo(string $playerName) {
        $players = $this->getPlayers()->getAll();
        foreach($this->getKits()->getAll() as $kitName => $kitData) {
            $elo[] = $players[$playerName][$kitName];
            $finalElo = array_sum($elo);
        }
        return $finalElo ?? null;
    }

    /**
     * @param string $playerName
     * @return string|null
     */
    public function getRank(string $playerName) {
        $globalElo = $this->getGlobalElo($playerName);
        foreach($this->getRanks() as $rankName => $rankData) {
            if($globalElo >= $rankData["elo"]) $finalRank = $rankName;
        }
        return $finalRank ?? null;
    }

    /**
     * @param string $playerName
     * @return string
     */
    public function getRankPrefix(string $playerName): string {
        return $this->getRanks()[$this->getRank($playerName)]["prefix"];
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getMaxRankeds(Player $player): int {
        $group = $this->purePerms->getUserDataMgr()->getGroup($player);
        $finalGroup = (in_array($group, array_keys($this->getGroups()))) ? $group : "default";
        return $this->getGroups()[$finalGroup];
    }

    /**
     * @return Config
     */
    public function getKits(): Config {
        return $this->kits;
    }

    /**
     * @return Config
     */
    public function getPlayers(): Config {
        return $this->players;
    }

    /**
     * @return Config
     */
    public function getPlayersInfo(): Config {
        return $this->playersInfo;
    }
    
    /**
     * @return Config
     */
    public function getPlayerDuels(): Config {
        return $this->playerDuels;
    }
    
    /**
     * @return Config
     */
    public function getArenasConfig(): Config {
        return $this->arenasCfg;
    }
    
    /**
     * @return Arena[]
     */
    public function getArenas(): array {
        return $this->arenas;
    }

    /**
     * @param Player $player
     * @return Arena|null
     */
    public function getArenaByPlayer($player) {
        foreach ($this->getArenas() as $arena)
            if($arena->inArena($player)) {
                return $arena;
            }
        return null;
    }
    
    /**
     * @param string $name
     * @return Arena|null
     */
    public function getArenaByName(string $name) {
        if (isset($this->getArenas()[$name])){
            return $this->getArenas()[$name];
        }
        return null;
    }

    /**
     * @return string
     */
    public function getPrefix(): string {
        return $this->getConfig()->get("prefix");
    }
    
    /**
     * @return int
     */
    public function getCountdown(): int {
        return $this->getConfig()->get("countdown");
    }

    /**
     * @return string
     */
    public function getJoinMessage(): string {
        return $this->getConfig()->get("join-message");
    }

    /**
     * @return string
     */
    public function getQuitMessage(): string {
        return $this->getConfig()->get("quit-message");
    }

    /**
     * @return string
     */
    public function getFinishMessage(): string {
        return $this->getConfig()->get("finish-message");
    }
    
    /**
     * @return int
     */
    public function getEloToAdd(): int {
        return $this->getConfig()->get("elo-to-add");
    }
    
    /**
     * @return int
     */
    public function getEloToSub(): int {
        return $this->getConfig()->get("elo-to-sub");
    }

    /**
     * @return array
     */
    public function getRanks(): array {
        return $this->getConfig()->get("ranks");
    }

    /**
     * @return array
     */
    public function getGroups(): array {
        return $this->getConfig()->get("groups");
    }

    /**
     * @return UI
     */
    public function getUI(): UI {
        return $this->ui;
    }

    /**
     * @return Listener
     */
    public function getListener(): Listener {
        return $this->listener;
    }
}
