<?php

namespace AlfaByte\Practice\Commands;

use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use TheNewManu\Practice\Main;

class DuelCommand extends Command implements PluginIdentifiableCommand {

    /** @var Main */
    private $plugin;

    /**
     * @param Main $plugin
     */
    public function __construct(Main $plugin) {
        parent::__construct("duel", "Practice duel commands", "Usage: /duel help");
        $this->plugin = $plugin;
    }
    
    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        $duelStickPlayers = $this->getPlugin()->getListener()->duelStickPlayers;
        if(!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "You can use this command only in-game");
            return false;
        }
        if(!isset($args[0])) {
            $sender->sendMessage($this->getUsage());
            return false;
        }
        switch(array_shift($args)) {
            case "help":
                $help = [
                    "" => "Practice Duel Help Page",
                    "list" => "See your duel requests",
                    "accept" => "Accept a player's duel",
                    "history" => "See your duel history",
                    "send" => "Send a new duel request"
                ];
                foreach($help as $key => $value) {
                    $sender->sendMessage(TextFormat::RED . $key . TextFormat::AQUA . " » " . TextFormat::YELLOW . $value);
                }
                break;
            case "list":
                $this->getPlugin()->getUI()->seeDuelRequests($sender);
                break;
            case "history":
                $this->getPlugin()->getUI()->duelHistoryUI($sender);
                break;
            case "accept":
                if(count($args) !== 1) {
                    $sender->sendMessage("Usage: /duel accept {player}");
                    return false;
                }
                if(!isset($duelStickPlayers[$args[0]]) or !isset($duelStickPlayers[$args[0]][$sender->getName()])) {
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . $args[0] . " hasn't sent you any duel!");
                    return false;
                }
                $target = $this->getPlugin()->getServer()->getPlayer($args[0]);
                if(!($target instanceof Player)) {
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . $args[0] . " player is no longer online!");
                    return false;
                }
                $arenas = [];
                $kit = $duelStickPlayers[$args[0]][$sender->getName()];
                foreach($this->getPlugin()->getArenas() as $arena) {
                    if($arena->getType() === "unranked" and $arena->getKit() === $kit and count($arena->getPlayers()) === 0) $arenas[] = $arena->getName();
                }
                if(empty($arenas)) {
                    $arenas = [];
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "There aren't empty arenas!");
                    return false;
                }
                $randomArena = $arenas[array_rand($arenas)];
                $this->getPlugin()->getServer()->dispatchCommand($sender, "practice join " . $randomArena);
                $this->getPlugin()->getServer()->dispatchCommand($target, "practice join " . $randomArena);
                unset($duelStickPlayers[$args[0]][$sender->getName()]);
                break;
            case "send":
                if(count($args) !== 1) {
                    $sender->sendMessage("Usage: /duel send {player}");
                    return false;
                }
                $target = $this->getPlugin()->getServer()->getPlayer($args[0]);
                if(!($target instanceof Player)) {
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . $args[0] . " player isn't online!");
                    return false;
                }
                if($target->getName() === $sender->getName()) {
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . " You can't duel yourself!");
                    return false;
                }
                $this->getPlugin()->getUI()->duelStickUI($sender, $target);
                break;
            default:
                $sender->sendMessage($this->getUsage());
                break;
        }
        return true;
    }
    
    /**
     * @return Main
     */
    public function getPlugin() : Plugin {
        return $this->plugin;
    }
}
