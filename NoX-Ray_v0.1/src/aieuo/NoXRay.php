<?php
namespace aieuo;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\utils\Utils;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

class NoXRay extends PluginBase implements Listener{

    public function onEnable(){
            $this->getServer()->getPluginManager()->registerEvents($this,$this);
            if(!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder(), 0721, true);
            $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, []);
            $this->b = new Config($this->getDataFolder() . "block.yml", Config::YAML, []);
            $this->CheckFile($this->config);
    }

    public function onCommand(CommandSender $sender, Command $command,string $label, array $args):bool{
        switch($command->getName()){
            case "nx":
                if(!isset($args[0]))return false;
                switch($args[0]){
                    case "add":
                        if(!isset($args[1]) or !isset($args[2]) or !isset($args[3])){
                            $sender->sendMessage("nx add <id> <警告を出すまでの数> <リセットする時間>");
                            return true;
                        }
                        $this->b->set($args[1],[
                            "count"=>(int)$args[2],
                            "time"=>(float)$args[3]
                        ]);
                        $this->b->save();
                        $sender->sendMessage("§b追加しました  id=".$args[1]);
                        return true;
                    break;
                    case "del":
                        if(!isset($args[1])){
                            $sender->sendMessage("/nx del <id>");
                            return true;
                        }
                        if($this->b->exists($args[1])){
                            $this->b->remove($args[1]);
                            $this->b->save();
                            $sender->sendMessage("§b削除しました  id=".$args[1]);
                        }else{
                            $sender->sendMessage("そのブロックは登録されていません");
                        }
                        return true;
                    break;
                    case "list":
                        $blocks = $this->b->getAll();
                        $sender->sendMessage("登録されているブロック");
                        foreach ($blocks as $key => $value) {
                            $sender->sendMessage("§bid:".(string)$key."  警告までの数:".(string)$value["count"]."個  リセットする時間:".(string)$value["time"]."分");
                        }
                        return true;
                    break;
                    default:
                        return false;
                    break;
                }            
            break;
            case "setnx":
                if(!isset($args[0]))return false;
                switch($args[0]){
                    case "m":
                    case "message":
                        if(!isset($args[1])){
                            $sender->sendMessage("setnx mes <メッセージ>");
                            return true;
                        }
                        $this->config->set("message",$args[1]);
                        $this->config->save();
                        $sender->sendMessage("§b設定しました  ".$args[1]);
                        return true;
                        break;
                    case "p":
                    case "penalty":
                        if(!isset($args[1]) or ((int)$args[1] < 1 and (int)$args[1] > 4)){
                            $sender->sendMessage("setnx penalty <1~4>");
                            return true;
                        }
                        $this->config->set("penalty",(int)$args[1]);
                        $this->config->save();
                        $sender->sendMessage("§b設定しました  ".$args[1]);
                        return true;
                        break;
                    case "help":
                        $sender->sendMessage("penalty\n1: 警告\n2: キック\n3: そのプレイヤーからコマンドを実行させる(権限のないコマンドはできない)\n4: コンソールからコマンドを実行する(権限のないコマンドでもできる)\nmessage\nペナルティーが1の時: 警告の文字\n2の時: kickされたときに出る文字\n3の時: コマンド(最初の/を外して)\n4の時: コマンド(最初の/を外して @pにするとブロックを壊したプレイヤーの名前に変わります)");
                        return true;
                    break;
                    default:
                        return false;
                    break;
                }             break;
        }
     }

    public function onBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $id = $block->getId();
        if($this->b->exists($id)){
            $data = $this->b->get($id);
            if(!isset($this->dc[$name][$id]))$this->dc[$name][$id] =0;
            $this->dc[$name][$id] ++;
            if($this->dc[$name][$id] == 1){
                if($data["time"] ==0)return;
                $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"RDC"],[$name,$id]), 20*60*$data["time"]);
            }elseif($this->dc[$name][$id] > $data["count"]){
                    $event->setCancelled();
                    if($data["time"] == 0) $this->dc[$name][$id] =0;
                    switch ($this->config->get("penalty")) {
                        case 1:
                            $player->sendMessage($this->config->get("message"));
                            break;
                        case 2:
			    $cmd = $this->config->get("message");
                            $cmd = "kick ".$player->getName()." ".$cmd;
                            $this->getServer()->dispatchCommand(new ConsoleCommandSender,$cmd);
                            break;
                        case 3:
                            $cmd = $this->config->get("message");
                            $this->getServer()->dispatchCommand($player,$cmd);
                            break;
                        case 4:
                            $cmd = $this->config->get("message");
                            $cmd = str_replace("@p",$player->getName(),$cmd);
                            $this->getServer()->dispatchCommand(new ConsoleCommandSender,$cmd);
                            break;
                    }
            }
        }
    }

    public function RDC($name,$id){
            $this->dc[$name][$id] =0;
    }

    public function CheckFile($config){
        if($config->exists("message") === false){
            $config->set("message","§c透視テクスチャの使用は禁止です");
        }
        if($config->exists("penalty") === false){
            $config->set("penalty",1);
        }
        $config->save();
    }
}