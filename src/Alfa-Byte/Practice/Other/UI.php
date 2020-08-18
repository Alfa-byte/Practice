<?php

namespace AlfaByte\Practice\Other;

use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use muqsit\invmenu\InvMenu;
use TheNewManu\Practice\Main;
use TheNewManu\Practice\Arena;

class UI {

    /** @var Main $plugin */
    private $plugin;

    /** @var array $type */
    private $type = [];

    /** @var array $targets */
    private $targets = [];

    /** @var array $sfidante */
    private $sfidante = [];

    /**
     * @param Main $plugin
     */
    public function __construct(Main $plugin){
        $this->plugin = $plugin;
        $this->formAPI = $this->getPlugin()->getServer()->getPluginManager()->getPlugin("FormAPI");
    }

    /**
     * @param Arena $arena
     * @return string
     */
    public function getArenaInfo(Arena $arena): string {
        return TextFormat::YELLOW . "Status: " . TextFormat::RED . $arena->getStatus() . TextFormat::BLACK . " | " . TextFormat::YELLOW . "Players: " . TextFormat::RED . count($arena->getPlayers());
    }

    /**
     * @param string $kit
     * @param string $type
     * @return string
     */
    public function getArenasInfo(string $kit, string $type): string {
        $inGameArenas = 0;
        $inQueueArenas = 0;
        foreach($this->getPlugin()->getArenas() as $arena) {
            if($kit === $arena->getKit() and $type === $arena->getType()) {
                if(count($arena->getPlayers()) === 2) $inGameArenas++;
                if(count($arena->getPlayers()) === 1) $inQueueArenas++;
            }
        }
        return TextFormat::YELLOW . "In Game: " . TextFormat::RED . $inGameArenas . TextFormat::BLACK . " | " . TextFormat::YELLOW . "In Queue: " . TextFormat::RED . $inQueueArenas;
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function joinUI(Player $player): void {
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data === null) return;
            $playerName = $event->getName();
            $rankedsPlayed = $this->getPlugin()->getPlayersInfo()->getNested("$playerName.ranked-played");
            switch($data) {
                case "ranked":
                    if($rankedsPlayed >= $this->getPlugin()->getMaxRankeds($event)) {
                        $event->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "You played the maximum number of ranked games for today");
                        return;
                    }
                    $this->kitJoinUI($event, $data);
                    break;
                 case "unranked":
                     $this->kitJoinUI($event, $data);
                     break;
                 case "quit":
                     $this->getPlugin()->getServer()->dispatchCommand($event, "practice quit");
                     break;
            }
        });
        $form->setTitle(TextFormat::YELLOW . "Practice");
        $form->setContent(TextFormat::BLUE . "Choose a type");
        $form->addButton(TextFormat::BOLD . TextFormat::BLUE . "Ranked", -1, "", "ranked");
        $form->addButton(TextFormat::BOLD . TextFormat::BLUE . "Unranked", -1, "", "unranked");
        if($arena = $this->getPlugin()->getArenaByPlayer($player)) $form->addButton(TextFormat::BOLD . TextFormat::RED . "Leave Queue", -1, "", "quit");
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @param string $type
     * @return void
     */
    public function kitJoinUI(Player $player, string $type): void {
        $this->type[$player->getName()] = $type;
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data === null) return;
            $arenas1 = [];
            $arenas0 = [];
            foreach($this->getPlugin()->getArenas() as $arena) {
                if($this->type[$event->getName()] === $arena->getType() and $data === $arena->getKit()) {
                    if(count($arena->getPlayers()) === 1) $arenas1[] = $arena->getName();
                    if(count($arena->getPlayers()) === 0) $arenas0[] = $arena->getName();
                }
            }
            $arenas = (!empty($arenas1)) ? $arenas1 : ((!empty($arenas0)) ? $arenas0 : []);
            if(empty($arenas)) {
                $event->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "There aren't empty arenas!");
                return;
            }
            $randomArena = $arenas[array_rand($arenas)];
            $this->getPlugin()->getServer()->dispatchCommand($event, "practice join " . $randomArena);
            unset($this->type[$event->getName()]);
        });
        $form->setTitle(TextFormat::YELLOW . "Practice Kits");
        $form->setContent(TextFormat::BLUE . "Choose a kit");
        foreach($this->getPlugin()->getKits()->getAll() as $kit => $info) {
            $form->addButton(TextFormat::RED . $kit . TextFormat::EOL . $this->getArenasInfo($kit, $type), 0, $info["image-path"], $kit);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @param Player $target
     * @return void
     */
    public function duelStickUI(Player $player, Player $target): void {
        $this->targets[$player->getName()] = $target;
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data === null) return;
            $target = $this->targets[$event->getName()];
            $this->getPlugin()->getListener()->duelStickPlayers[$event->getName()][$target->getName()] = $data;
            $event->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "You challenged " . TextFormat::YELLOW . $target->getName() . TextFormat::RED . " in a " . TextFormat::YELLOW . $data . TextFormat::RED . " duel.");
            $target->sendMessage($this->getPlugin()->getPrefix() . TextFormat::YELLOW . $event->getName() . TextFormat::RED . " challenged you in a " . TextFormat::YELLOW . $data . TextFormat::RED . " duel." . TextFormat::WHITE . " Use /duel accept " . $event->getName());
            unset($this->targets[$event->getName()]);
        });
        $form->setTitle(TextFormat::YELLOW . "Practice Kits");
        $form->setContent(TextFormat::BLUE . "Choose a kit for the duel");
        foreach($this->getPlugin()->getKits()->getAll() as $kit => $info) {
            $form->addButton(TextFormat::BOLD . TextFormat::RED . $kit, 0, $info["image-path"], $kit);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function seeDuelRequests(Player $player): void {
        $kits = $this->getPlugin()->getKits()->getAll();
        $duelStickPlayers = $this->getPlugin()->getListener()->duelStickPlayers;
        if(!is_array($duelStickPlayers)) {
            $player->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "You haven't any duel request!");
            return;
        }
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data !== null) $this->getPlugin()->getServer()->dispatchCommand($event, "duel accept " . $data);
        });
        $form->setTitle(TextFormat::YELLOW . "Your Duel Requests");
        $form->setContent(TextFormat::BLUE . "Click a request to accept it");
        foreach($duelStickPlayers as $sender => $array) {
            if(isset($array[$player->getName()])) $form->addButton(TextFormat::YELLOW . $sender . TextFormat::EOL . TextFormat::RED . "Kit: " . TextFormat::WHITE . $array[$player->getName()], 0, $kits[$array[$player->getName()]]["image-path"], $sender);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function statsUI(Player $player): void {
        $playerName = $player->getName();
        $kits = $this->getPlugin()->getKits()->getAll();
        $playersInfo = $this->getPlugin()->getPlayersInfo()->getAll();
        $playerElo = $this->getPlugin()->getPlayers()->get($player->getName());
        foreach($playerElo as $kit => $points) {
            $elo[] = $points;
        }
        $globalElo = array_sum($elo);
        foreach($playersInfo[$playerName] as $kit => $stats) {
            $wins[] = $stats["wins"];
            $loses[] = $stats["loses"];
        }
        $globalWins = array_sum($wins);
        $globalLoses = array_sum($loses);
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data !== null) $this->statsUI($event);
        });
        $form->setTitle(TextFormat::YELLOW . "Your Elo");
        $form->setContent(TextFormat::BLUE . "Click a button for the top");
        $form->addButton(TextFormat::YELLOW . "Global: " . TextFormat::RED . $globalElo . TextFormat::EOL . TextFormat::YELLOW . "Wins: " . TextFormat::RED . $globalWins . TextFormat::BLACK . " | " . TextFormat::YELLOW . "Loses: " . TextFormat::RED . $globalLoses, -1, "", $kit);
        foreach($playerElo as $kit => $points) {
            $form->addButton(TextFormat::YELLOW . $kit . ": " . TextFormat::RED . $points . TextFormat::EOL . TextFormat::YELLOW . "Wins: " . TextFormat::RED . $playersInfo[$playerName][$kit]["wins"] . TextFormat::BLACK . " | " . TextFormat::YELLOW . "Loses: " . TextFormat::RED . $playersInfo[$playerName][$kit]["loses"], 0, $kits[$kit]["image-path"], $kit);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function eloUI(Player $player): void {
        $playerName = $player->getName();
        $kits = $this->getPlugin()->getKits()->getAll();
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data === null) return;
            if($data === "global") {
                $this->topEloGlobalUI($event);
            }else{
                $this->topEloUI($event, $data);
            }
        });
        $form->setTitle(TextFormat::YELLOW . "Top Elo");
        $form->setContent(TextFormat::BLUE . "Select a kit for the top");
        $form->addButton(TextFormat::YELLOW . "Global" . TextFormat::EOL . TextFormat::BLUE . "*Click me*", -1, "", "global");
        foreach($kits as $kitName => $kitData) {
            $form->addButton(TextFormat::YELLOW . $kitName . TextFormat::EOL . TextFormat::BLUE . "*Click me*", 0, $kits[$kitName]["image-path"], $kitName);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function topEloGlobalUI(Player $player): void {
        $top = [];
        $text = "";
        $number = 0;
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?int $data) {
            if($data !== null) $this->eloUI($event);
        });
        foreach($this->getPlugin()->getPlayers()->getAll() as $name => $kits) {
            $top[$name] = $this->getPlugin()->getGlobalElo($name);
        }
        if(is_array($top)) arsort($top);
        foreach(array_slice($top, 0, 10) as $name => $points) {
            $number++;
            $text .= TextFormat::RED . $number . ") " . TextFormat::AQUA . $name . " » " . TextFormat::YELLOW . $points . TextFormat::EOL . TextFormat::EOL;
        }
        $form->setTitle(TextFormat::YELLOW . "Global Top Elo");
        $form->setContent($text);
        $form->addButton(TextFormat::BOLD . TextFormat::RED . "Back", 0, $this->getPlugin()->getConfig()->get("back-button-image"));
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @param string $kit
     * @return void
     */
    public function topEloUI(Player $player, string $kit): void {
        $top = [];
        $text = "";
        $number = 0;
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?int $data) {
            if($data !== null) $this->eloUI($event);
        });
        foreach($this->getPlugin()->getPlayers()->getAll() as $name => $kits) {
            $top[$name] = $kits[$kit];
        }
        if(is_array($top)) arsort($top);
        foreach(array_slice($top, 0, 10) as $name => $points) {
            $number++;
            $text .= TextFormat::RED . $number . ") " . TextFormat::AQUA . $name . " » " . TextFormat::YELLOW . $points . TextFormat::EOL . TextFormat::EOL;
        }
        $form->setTitle(TextFormat::YELLOW . $kit . " Top Elo");
        $form->setContent($text);
        $form->addButton(TextFormat::BOLD . TextFormat::RED . "Back", 0, $this->getPlugin()->getConfig()->get("back-button-image"));
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function duelHistoryUI(Player $player): void {
        $kits = $this->getPlugin()->getKits()->getAll();
        $duels = $this->getPlugin()->getPlayerDuels()->get($player->getName());
        if(!is_array($duels)) {
            $player->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "You haven't any duels");
            return;
        }
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data !== null) $this->duelHistoryInfoUI($event, $data);
        });
        $form->setTitle(TextFormat::YELLOW . "Your Duel History");
        $form->setContent(TextFormat::BLUE . "Choose a challenger");
        foreach($duels as $sfidante => $info) {
            $form->addButton(TextFormat::RED . $sfidante . TextFormat::EOL . TextFormat::GREEN . $info["date"], 0, $kits[$info["kit"]]["image-path"], $sfidante);
        }
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function duelHistoryInfoUI(Player $player, string $sfidante): void {
        $this->sfidante[$player->getName()] = $sfidante;
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data === null) return;
            switch($data) {
                case "my-stats":
                    $this->playerInventoryGUI($event, $this->sfidante[$event->getName()]);
                    break;
                case "his-stats":
                    $this->sfidanteInventoryGUI($event, $this->sfidante[$event->getName()]);
                    break;
                case "general-stats":
                    $this->duelInfoUI($event, $this->sfidante[$event->getName()]);
                    break;
                case "back":
                    $this->duelHistoryUI($event);
                    break;
            }
        });
        $form->setTitle(TextFormat::YELLOW . "Your duel versus " . $sfidante);
        $form->setContent(TextFormat::BLUE . "Choose a information type");
        $form->addButton(TextFormat::RED . "Watch your inventory", 0, "textures/blocks/chest_front", "my-stats");
        $form->addButton(TextFormat::RED . "Watch " . TextFormat::YELLOW . $sfidante . TextFormat::RED . " inventory", 0, "textures/blocks/chest_front", "his-stats");
        $form->addButton(TextFormat::RED . "Watch the game statistics", 0, "textures/items/paper", "general-stats");
        $form->addButton(TextFormat::BOLD . TextFormat::RED . "Back", 0, $this->getPlugin()->getConfig()->get("back-button-image"), "back");
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @return void
     */
    public function duelInfoUI(Player $player, string $sfidante): void {
        unset($this->sfidante[$player->getName()]);
        $informations = [];
        $name = $player->getName();
        $info = $this->getPlugin()->getPlayerDuels()->getNested("$name.$sfidante");
        $form = $this->formAPI->createSimpleForm(function(Player $event, ?string $data) {
            if($data !== null) $this->duelHistoryInfoUI($event, $data);
        });
        foreach($info["my-stats"] as $key => $value) {
            if(!is_array($value)) $informations["my-stats"][]= TextFormat::AQUA . "Your " . $key . " » " . TextFormat::YELLOW . $value;
        }
        foreach($info["his-stats"] as $key => $value) {
            if(!is_array($value)) $informations["his-stats"][] = TextFormat::AQUA . "His " . $key . " » " . TextFormat::YELLOW . $value;
        }
        $form->setTitle(TextFormat::YELLOW . "Your duel versus " . $sfidante);
        $form->setContent(TextFormat::AQUA . "Winner » " . TextFormat::YELLOW . $info["winner"] . TextFormat::EOL . TextFormat::EOL . implode(TextFormat::EOL, $informations["my-stats"]) . TextFormat::EOL . TextFormat::EOL . implode(TextFormat::EOL, $informations["his-stats"]));
        $form->addButton(TextFormat::BOLD . TextFormat::RED . "Back", 0, $this->getPlugin()->getConfig()->get("back-button-image"), $sfidante);
        $form->sendToPlayer($player);
    }
    
    /**
     * @param Player $player
     * @param string $sfidante
     * @return void
     */
    public function playerInventoryGUI(Player $player, string $sfidante): void {
        $number = 45;
        $name = $player->getName();
        $info = $this->getPlugin()->getPlayerDuels()->getNested("$name.$sfidante.my-stats");
        $specialItems = [
            (ItemFactory::get(Item::SKULL)->setCustomName(TextFormat::YELLOW . "Opponent: " . $sfidante)),
            (ItemFactory::get(Item::MELON)->setCustomName(TextFormat::YELLOW . "Life: " . $info["life"])),
            (ItemFactory::get(Item::STEAK)->setCustomName(TextFormat::YELLOW . "Food: " . $info["food"])),
            (ItemFactory::get(Item::EMERALD)->setCustomName(TextFormat::YELLOW . "Ping: " . $info["ping"])),
            (ItemFactory::get(Item::WOODEN_SWORD)->setCustomName(TextFormat::YELLOW . "CPS: " . $info["cps"])),
            (ItemFactory::get(Item::PAPER)->setCustomName(TextFormat::YELLOW . "Hits: " . $info["hits"]))
        ];
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)
        ->readonly()
        ->setName(TextFormat::RED . "Your inventory")
        ->setInventoryCloseListener(function(Player $player): void {
            unset($this->sfidante[$player->getName()]);
        });
        foreach($info["armor"] as $armor) {
            $menu->getInventory()->addItem(ItemFactory::get((int)$armor));
        }
        foreach($info["items"] as $items) {
            $item = explode(":", $items);
            $menu->getInventory()->addItem(ItemFactory::get((int)$item[0], (int)$item[1], (int)$item[2]));
        }
        foreach($specialItems as $specialItem) {
            $menu->getInventory()->setItem($number, $specialItem);
            $number++;
        }
        $menu->send($player);
    }
    
    /**
     * @param Player $player
     * @param string $sfidante
     * @return void
     */
    public function sfidanteInventoryGUI(Player $player, string $sfidante): void {
        $number = 45;
        $name = $player->getName();
        $info = $this->getPlugin()->getPlayerDuels()->getNested("$name.$sfidante.his-stats");
        $specialItems = [
            (ItemFactory::get(Item::SKULL)->setCustomName(TextFormat::YELLOW . "Opponent: Are you idiot")),
            (ItemFactory::get(Item::GLISTERING_MELON)->setCustomName(TextFormat::YELLOW . "Life: " . $info["life"])),
            (ItemFactory::get(Item::STEAK)->setCustomName(TextFormat::YELLOW . "Food: " . $info["food"])),
            (ItemFactory::get(Item::EMERALD)->setCustomName(TextFormat::YELLOW . "Ping: " . $info["ping"])),
            (ItemFactory::get(Item::WOODEN_SWORD)->setCustomName(TextFormat::YELLOW . "CPS: " . $info["cps"])),
            (ItemFactory::get(Item::PAPER)->setCustomName(TextFormat::YELLOW . "Hits: " . $info["hits"]))
        ];
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)
        ->readonly()
        ->setName(TextFormat::RED . $sfidante . " inventory")
        ->setInventoryCloseListener(function(Player $player): void {
            unset($this->sfidante[$player->getName()]);
        });
        foreach($info["armor"] as $armor) {
            $menu->getInventory()->addItem(ItemFactory::get((int)$armor));
        }
        foreach($info["items"] as $items) {
            $item = explode(":", $items);
            $menu->getInventory()->addItem(ItemFactory::get((int)$item[0], (int)$item[1], (int)$item[2]));
        }
        foreach($specialItems as $specialItem) {
            $menu->getInventory()->setItem($number, $specialItem);
            $number++;
        }
        $menu->send($player);
        unset($this->sfidante[$player->getName()]);
    }

    /**
     * @return Main
     */
    public function getPlugin(): Main {
        return $this->plugin;
    }
}