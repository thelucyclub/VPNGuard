<?php
namespace basprohop\libraries;

use basprohop\VPNGuard;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class CommandFunctions {
    private $plugin;

    public function __construct(VPNGuard $plugin){
        $this->plugin = $plugin;
    }

    /**
     * Function that is executed when /vpnguard about is invoked.
     * Displays information regarding the plugin and shows commands available to the user.
     * @param CommandSender $sender - Command Sender
     */
    public function cmdEmpty(CommandSender $sender) {
        $sender->sendMessage($this->plugin->msg("VPNGuard Command List"));
        if($sender->hasPermission("vpnguard.command.vpnguard")) {
            if($sender->hasPermission("vpnguard.command.clearcache")) {
                $sender->sendMessage($this->plugin->msg("/vpnguard clearcache"));
            }
            if($sender->hasPermission("vpnguard.command.clearip")) {
                $sender->sendMessage($this->plugin->msg("/vpnguard clearip <ipv4 address>"));
            }
            if($sender->hasPermission("vpnguard.command.lookup")) {
                $sender->sendMessage($this->plugin->msg("/vpnguard lookup <ipv4 address>"));
            }
            if($sender->hasPermission("vpnguard.command.ban")) {
                $sender->sendMessage($this->plugin->msg("/vpnguard ban <ipv4 address/subnet>"));
            }
            if($sender->hasPermission("vpnguard.command.unban")) {
                $sender->sendMessage($this->plugin->msg("/vpnguard unban <ipv4 address/subnet>"));
            }
            if($sender->hasPermission("vpnguard.command.about")) {
                $sender->sendMessage($this->plugin->msg("/vpnguard about"));
            }
        } else {
            $sender->sendMessage($this->plugin->msg("You have no permissions to run any commands."));
        }
    }

    /**
     * Function that is executed when /vpnguard clearcache is invoked
     * Deletes all the Cached Files.
     * @param CommandSender $sender - Command Sender
     */
    public function cmdClearCache(CommandSender $sender) {
        $this->plugin->cache->remove_all_cache();
        $sender->sendMessage($this->plugin->msg("All Tasks Completed!"));
    }

    /**
     * Function that is executed when /vpnguard clearip <ip> is invoked
     * Deletes the specified IP address cache file.
     * @param CommandSender $sender - Command Sender
     * @param $ip - IP address whose cached file will be deleted
     */
    public function cmdClearIP(CommandSender $sender, $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP) === false) {
            if($this->plugin->cache->is_cached($ip)) {
                if($this->plugin->cache->remove_cache($ip)) {
                    $sender->sendMessage($this->plugin->msg("Successfully deleted the cached API file."));
                } else {
                    $sender->sendMessage($this->plugin->msg("Unable to delete the cached file. Sorry!"));
                }
            } else {
                $sender->sendMessage($this->plugin->msg("No cache file found matching the ipv4 address: " . $ip));
            }
        } else {
            $sender->sendMessage($this->plugin->msg($ip . " is not a valid IP address."));
        }
    }

    /**
     * Function that is executed when /vpnguard lookup <ip> is invoked
     * Looks up information regarding the specified IP address and displays it to user.
     * @param CommandSender $sender - Player that sent the command.
     * @param $ip - IP address that will be checked.
     */
    public function cmdLookup(CommandSender $sender, $ip) {
        if($sender instanceof Player) {
            if (!filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $this->plugin->getServer()->getScheduler()->scheduleAsyncTask(
                    new Async(2, $sender->getName(), $ip, $this->plugin->getUserAgent(),
                        $this->plugin->cfg, $this->plugin->cfgCommands, $this->plugin->cache));
            } else {
                $sender->sendMessage($this->plugin->msg($ip . " is not a valid IP address."));
            }
        } else {
            $sender->sendMessage($this->plugin->msg("This command can only be run in-game."));
        }
    }

    /**
     * Function that is executed when /vpnguard about is invoked
     * Displays information about the plugin to the sender.
     * @param CommandSender $sender - Command Sender
     */
    public function cmdAbout(CommandSender $sender) {
        $sender->sendMessage($this->plugin->msg("VPNGuard v" . $this->plugin->getDescription()->getVersion()));
        if(empty($this->plugin->cfg["api-key"])) {
            $sender->sendMessage($this->plugin->msg("Using API Key? " . TextFormat::GRAY . "NO"));
        } else {
            $sender->sendMessage($this->plugin->msg("Using API Key? " . TextFormat::GREEN . "YES"));
        }

        if($this->plugin->cache instanceof SimpleCache) {
            $total = $this->totalCached();
            $sender->sendMessage($this->plugin->msg("API Request Caching: " . TextFormat::GREEN . "ENABLED"));
            if($total > 0) {
                $sender->sendMessage($this->plugin->msg("API Requests Cached: " . TextFormat::AQUA . $total));
            }
        } else {
            $sender->sendMessage($this->plugin->msg("API Request Caching: " . TextFormat::GRAY . "DISABLED"));
        }
        $sender->sendMessage($this->plugin->msg("API Homepage: " . TextFormat::AQUA . "http://xioax.com/host-blocker/"));

    }


    /**
     * Function that is executed when /vpnguard ban <ip> is invoked
     * Bans the subnet from joining the server
     * @param CommandSender $sender - Command Sender
     * @param $ip - IP address that will be banned.
     */
    public function cmdBan(CommandSender $sender, $ip) {
        $split = explode("/", $ip);

        if( (count($split) != 2)) {
            $sender->sendMessage($this->plugin->msg("Enter a valid IP in CIDR format."));
            return;
        }

        if(!is_numeric($split[1])) {
            $sender->sendMessage($this->plugin->msg("Enter a valid IP subnet in CIDR format."));
            return;
        }

        if (!filter_var($split[0], FILTER_VALIDATE_IP) === false) {
            if (($split[1] <= 32) && ($split[1] >= 0)) {
                if(in_array($ip, $this->plugin->subnets)) {
                    $sender->sendMessage($this->plugin->msg($ip . " is already banned."));
                } else {
                    array_push($this->plugin->subnets, $ip);
                    $this->plugin->subnet_list->set("subnets", $this->plugin->subnets);
                    $this->plugin->subnet_list->save();
                    $sender->sendMessage($this->plugin->msg($ip . " has been banned."));
                }
            } else {
                $sender->sendMessage($this->plugin->msg($split[1] . " is not a valid IP subnet, it must be in CIDR format."));
            }
        } else {
            $sender->sendMessage($this->plugin->msg($split[0] . " is not a valid IP address."));
        }
    }

    /**
     * Function that is executed when /vpnguard unban <ip> is invoked
     * Unbans the subnet if currently banned.
     * @param CommandSender $sender - Command Sender
     * @param $ip - IP address that will be unbanned
     */
    public function cmdUnban(CommandSender $sender, $ip) {
        $split = explode("/", $ip);
        $param = $ip;

        if( (count($split) != 2)) {
            $sender->sendMessage($this->plugin->msg("Enter a valid IP in CIDR format."));
            return;
        }

        if(!is_numeric($split[1])) {
            $sender->sendMessage($this->plugin->msg("Enter a valid IP subnet in CIDR format."));
            return;
        }


        if (!filter_var($split[0], FILTER_VALIDATE_IP) === false) {
            if (($split[1] <= 32) && ($split[1] >= 0)) {
                if(in_array($ip, $this->plugin->subnets)) {
                    $key = array_search($ip,$this->plugin->subnets);
                    if($key!==false){
                        unset($ip,$this->plugin->subnets[$key]);
                        $this->plugin->subnet_list->set("subnets", $this->plugin->subnets);
                        $this->plugin->subnet_list->save();
                        $sender->sendMessage($this->plugin->msg($param . " has been unbanned."));
                    } else {
                        $sender->sendMessage($this->plugin->msg("Unable to unban " . $ip));
                    }
                } else {
                    $sender->sendMessage($this->plugin->msg($ip . " is not banned."));
                }
            } else {
                $sender->sendMessage($this->plugin->msg($split[1] . " is not a valid IP subnet, it must be in CIDR format."));
            }
        } else {
            $sender->sendMessage($this->plugin->msg($split[0] . " is not a valid IP address."));
        }
    }

    /**
     * Function that returns the number of cached files currently stored.
     * @return int - Number of Cached Files
     */
    private function totalCached() {
        $i = 0;
        foreach(glob($this->plugin->cache->cache_path . '*') as $file){
            if(is_file($file)) {
                $i++;
            }
        }
        return $i;
    }

}