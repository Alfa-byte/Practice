<?php

namespace AlfaByte\Practice\Commands;

use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use TheNewManu\Practice\Main;

class PracticeCommand extends Command implements PluginIdentifiableCommand {

    /** @var Main */
    private $plugin;

    /**
     * @param Main $plugin
     */
    public function __construct(Main $plugin) {
        parent::__construct("practice", "Practice commands", "Usage: /practice help");
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
                    "" => "Practice Help Page",
                    "join" => "Join a duel",
                    "quit" => "Quit a duel",
                    "joinui" => "UI with all kits and arenas",
                    "eloui" => "UI with your elo and top elo",
                    "setarena" => "Set a new arena"
                ];
                foreach($help as $key => $value) {
                    $sender->sendMessage(TextFormat::RED . $key . TextFormat::AQUA . " » " . TextFormat::YELLOW . $value);
                }
                break;
            case "join":
                if(count($args) !== 1) {
                    $sender->sendMessage(TextFormat::WHITE . "Usage: /practice join {arena}");
                    return false;
                }
                if($arena = $this->getPlugin()->getArenaByName($args[0])) {
                    $this->getPlugin()->getServer()->loadLevel($arena->getWorldName());
                    $arena->join($sender);
                }else{
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "This arena not exists!");
                }
                break;
            case "quit":
                if($arena = $this->getPlugin()->getArenaByPlayer($sender)) {
                    $arena->quit($sender);
                }else{
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "You aren't in any arena!");
                }
                break;
            case "joinui":
                $this->getPlugin()->getUI()->joinUI($sender);
                break;
            case "eloui":
                $this->getPlugin()->getUI()->eloUI($sender);
                break;
            case "setarena":
                if(!$sender->hasPermission("practice.command.setarena")) {
                    $sender->sendMessage(TextFormat::RED . "You don't have permission to use this command!");
                    return false;
                }
                if(count($args) !== 3) {
                    $sender->sendMessage("Usage: /practice setarena {name} {type} {kit}");
                    return false;
                }
                if(!in_array(strtolower($args[1]), ["ranked", "unranked"])) {
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "Type must be ranked or unranked!");
                    return false;
                }
                if(!in_array($args[2], $this->getPlugin()->getKits()->getAll(true))) {
                    $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::RED . "Kit " . $args[2] . " not exists!");
                    return false;
                }
                $senderName = $sender->getName();
                $info = array(
                    "spawns" => [],
                    "kit" => $args[2],
                    "type" => strtolower($args[1]),
                    "world" => $sender->getLevel()->getFolderName()
                );
                $this->getPlugin()->getListener()->setspawns[$senderName] = [(string)$args[0], 2];
                $this->getPlugin()->getArenasConfig()->setNested($args[0], $info);
                $this->getPlugin()->getArenasConfig()->save();
                $sender->sendMessage($this->getPlugin()->getPrefix() . TextFormat::YELLOW . "Set the first spawn of arena " . TextFormat::RED . $args[0]);
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
