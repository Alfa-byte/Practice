<?php
declare(strict_types=1);

namespace AlfaByte\Practice;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\level\Location;
use pocketmine\utils\TextFormat;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use TheNewManu\Practice\Tasks\FTPSendTask;

class Listener implements \pocketmine\event\Listener {

    /** @var Main $plugin */
    private $plugin;
    
    /** @var array $setspawns */
    public $setspawns;
    
    /** @var array $duelStickPlayers */
    public $duelStickPlayers;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * @param PlayerJoinEvent $event
     */
    public function onJoin(PlayerJoinEvent $event): void {
        $name = $event->getPlayer()->getName();
        $this->getPlugin()->sendMenu($event->getPlayer());
        $players = $this->getPlugin()->getPlayers()->getAll();
        $playersInfo = $this->getPlugin()->getPlayersInfo()->getAll();
        foreach($this->getPlugin()->getKits()->getAll(true) as $kit) {
            if(!isset($players[$name][$kit])) $this->getPlugin()->getPlayers()->setNested("$name.$kit", (int)$this->getPlugin()->getConfig()->get("default-elo"));
            if(!isset($playersInfo[$name][$kit]["wins"])) $this->getPlugin()->getPlayersInfo()->setNested("$name.$kit.wins", 0);
            if(!isset($playersInfo[$name][$kit]["loses"])) $this->getPlugin()->getPlayersInfo()->setNested("$name.$kit.loses", 0);
        }
        if(!isset($playersInfo[$name]["ranked-played"])) $this->getPlugin()->getPlayersInfo()->setNested("$name.ranked-played", 0);
        $this->getPlugin()->getPlayers()->save();
        $this->getPlugin()->getPlayersInfo()->save();
        $this->getPlugin()->pureChat->setPrefix($this->getPlugin()->getRankPrefix($name), $event->getPlayer());
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onArenaSetting(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        $level = $player->getLevel();
        $block = $event->getBlock();
        if(isset($this->setspawns[$name])){
            $arena = $this->setspawns[$name][0];
            $spawns = $this->getPlugin()->getArenasConfig()->getNested("$arena.spawns");
            $spawns[] = [$block->getX(), $block->getFloorY() + 1, $block->getZ()];
            $this->getPlugin()->getArenasConfig()->setNested("$arena.spawns", $spawns);
            if($this->setspawns[$name][1] === 2) $player->sendMessage($this->getPlugin()->getPrefix() . TextFormat::YELLOW . "First spawn has been setted. Now set the second spawn!");
            $this->setspawns[$name][1]--;
            if($this->setspawns[$name][1] <= 0){
                $player->teleport($this->getPlugin()->getServer()->getDefaultLevel()->getSpawnLocation());
                unset($this->setspawns[$name]);
                $this->getPlugin()->getArenasConfig()->save();
                $this->getPlugin()->addArena($arena, $this->getPlugin()->getArenasConfig()->getNested("$arena.world"), $this->getPlugin()->getArenasConfig()->getNested("$arena.spawns"), $this->getPlugin()->getArenasConfig()->getNested("$arena.kit"), $this->getPlugin()->getArenasConfig()->getNested("$arena.type"));
                $player->sendMessage($this->getPlugin()->getPrefix() . TextFormat::YELLOW . "Arena " . TextFormat::RED . $arena . TextFormat::YELLOW . " have been setted!");
            }
        }
    }

    /**
     * @param PlayerItemHeldEvent $event
     */
    public function onItemHeld(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $duelRequestItem = explode(":", $this->getPlugin()->getConfig()->get("duel-stick-item"));
        if($player->getLevel() !== $this->getPlugin()->getServer()->getDefaultLevel()) {
            return;
        }
        if($event->getItem()->getId() !== (int)$duelRequestItem[1]) {
            return;
        }
        $player->sendMessage(TextFormat::RED . "Hit a player to send a duel request!");
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        $eloItem = explode(":", $this->getPlugin()->getConfig()->get("elo-item"));
        $statsItem = explode(":", $this->getPlugin()->getConfig()->get("stats-item"));
        $duelItem = explode(":", $this->getPlugin()->getConfig()->get("duel-item"));
        $duelHistoryItem = explode(":", $this->getPlugin()->getConfig()->get("duel-history-item"));  
        $duelRequestItem = explode(":", $this->getPlugin()->getConfig()->get("duel-request-item"));
        if($player->getLevel() !== $this->getPlugin()->getServer()->getDefaultLevel()) {
            return;
        }
        switch($item->getId()) {
            case (int)$eloItem[1]:
                $this->getPlugin()->getUI()->eloUI($player);
                break;
            case (int)$statsItem[1]:
                $this->getPlugin()->getUI()->statsUI($player);
                break;
            case (int)$duelItem[1]:
                $this->getPlugin()->getUI()->joinUI($player);
                break;
            case (int)$duelHistoryItem[1]:
                $this->getPlugin()->getUI()->duelHistoryUI($player);
                break;
            case (int)$duelRequestItem[1]:
                $this->getPlugin()->getUI()->seeDuelRequests($player);
                break;
        }
    }

    /**
     * @param PlayerMoveEvent $event
     */
    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $arena = $this->getPlugin()->getArenaByPlayer($player);
        if($arena and !$arena->isIdle() and $arena->getCountdown() > 0) {
            if($player->isSneaking()) $event->setCancelled();
            $event->setTo(Location::fromObject($event->getFrom()->setComponents($event->getFrom()->x, $event->getTo()->y, $event->getFrom()->z), $event->getFrom()->level, $event->getTo()->yaw, $event->getTo()->pitch));
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $arena = $this->getPlugin()->getArenaByPlayer($player);
        if($arena) {
            $arena->quit($player);
        }
    }

    /**
     * @param PlayerRespawnEvent $event
     */
    public function onRespawn(PlayerRespawnEvent $event): void {
        $this->getPlugin()->sendMenu($event->getPlayer());
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function onDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $arena = $this->getPlugin()->getArenaByPlayer($player);
        if($arena and $arena->isRunning()){
            $arena->quit($player, true);
            $event->setDrops([]);
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     */
    public function onHit(EntityDamageByEntityEvent $event): void {
        $player = $event->getEntity();
        $damager = $event->getDamager();
        $arena = $this->getPlugin()->getArenaByPlayer($player);
        $duelStickItem = explode(":", $this->getPlugin()->getConfig()->get("duel-stick-item"));
        if(!($player instanceof Player) or !($damager instanceof Player)) {
            return;
        }
        if($arena and $arena->isStarting()) {
            $event->setCancelled();
        }
        if($arena and $arena->isRunning()){
            $arena->addHit($damager);
        }
        if($damager->getInventory()->getItemInHand()->getId() !== (int)$duelStickItem[1]) {
            return;
        }
        $this->getPlugin()->getUI()->duelStickUI($damager, $player);
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onLevelChange(EntityLevelChangeEvent $event): void {
        $entity = $event->getEntity();
        if(!($entity instanceof Player)) {
            return;
        }
        $this->getPlugin()->getScheduler()->scheduleDelayedTask(new FTPSendTask($this->getPlugin(), $entity, $event->getOrigin(), $event->getTarget()), 20);
        $arena = $this->getPlugin()->getArenaByPlayer($entity);
        if($arena and $arena->isRunning() and $event->getTarget() !== $arena->getWorld()){
            $arena->quit($entity);
        }
        if($entity->isAlive() and $event->getTarget() === $entity->getServer()->getDefaultLevel()){
            $this->getPlugin()->sendMenu($entity);
        }
    }

    /**
     * @param BlockPlaceEvent $event
     */
    public function onPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $arena = $this->getPlugin()->getArenaByPlayer($player);
        if($arena and $arena->isRunning()) {
            $arena->addPlacedBlock($block->getX(), $block->getY(), $block->getZ());
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $arena = $this->getPlugin()->getArenaByPlayer($player);
        $vector3 = new Vector3($block->getX(), $block->getY(), $block->getZ());
        if($arena and $arena->isRunning()) {
            if(in_array($vector3, $arena->getPlacedBlocks())) {
                $arena->removePlacedBlock($block->getX(), $block->getY(), $block->getZ());
            }else{
                $event->setCancelled();
            }
        }
    }

    /**
     * @param BlockBurnEvent $event
     */
    public function onBlockBurn(BlockBurnEvent $event): void {
        $block = $event->getCausingBlock();
        $level = $block->getLevel()->getFolderName();
        $arena = $this->getPlugin()->getArenaByName($level);
        if($arena and $arena->isRunning()) {
            $event->setCancelled();
            $arena->addPlacedBlock($block->getX(), $block->getY(), $block->getZ());
        }
    }

    /**
     * @return Main
     */
    public function getPlugin(): Main {
        return $this->plugin;
    }
}