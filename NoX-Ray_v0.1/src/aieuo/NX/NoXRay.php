<?php

namespace aieuo\NX;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\utils\Utils;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

class NoXRay extends PluginBase implements Listener
{

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
        if(!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder(), 0755, true);
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, []);
        $this->b = new Config($this->getDataFolder() . "block.yml", Config::YAML, []);
        $this->checkConfig($this->config);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
    {
        switch($command->getName())
        {
            case "nx":
                if(!isset($args[0])) return false;
                switch($args[0])
                {
                    case "add":
                        if(!isset($args[3]))
                        {
                            $sender->sendMessage("/nx add <id> <警告を出すまでの数> <リセットする時間>");
                            return true;
                        }
                        $this->b->set($args[1],[
                            "count"=> (int)$args[2],
                            "time"=> (float)$args[3]
                        ]);
                        $this->b->save();
                        $sender->sendMessage("§b設定しました  id:".$args[1]);
                        return true;
                    case "del":
                        if(!isset($args[1]))
                        {
                            $sender->sendMessage("/nx del <id>");
                            return true;
                        }
                        if(!$this->b->exists($args[1]))
                        {
                            $sender->sendMessage("そのブロックは登録されていません");
                            return true;
                        }
                        $this->b->remove($args[1]);
                        $this->b->save();
                        $sender->sendMessage("§b削除しました  id:".$args[1]);
                        return true;
                    case "list":
                        $blocks = $this->b->getAll();
                        $sender->sendMessage("登録されているブロック");
                        foreach ($blocks as $key => $value)
                        {
                            $sender->sendMessage("§bid:".$key."  警告までの数:".$value["count"]."個  リセットする時間:".$value["time"]."分");
                        }
                        return true;
                    default:
                        return false;
                }
                break;
            case "setnx":
                if(!isset($args[0]))return false;
                switch($args[0])
                {
                    case "m":
                    case "message":
                        if(!isset($args[1]))
                        {
                            $sender->sendMessage("/setnx mes <メッセージ>");
                            return true;
                        }
                        $this->config->set("message",$args[1]);
                        $this->config->save();
                        $sender->sendMessage("§b設定しました  ".$args[1]);
                        return true;
                    case "p":
                    case "penalty":
                        if(!isset($args[1]) or ((int)$args[1] < 1 and (int)$args[1] > 4))
                        {
                            $sender->sendMessage("/setnx penalty <1~4>");
                            return true;
                        }
                        $this->config->set("penalty",(int)$args[1]);
                        $this->config->save();
                        $sender->sendMessage("§b設定しました  ".$args[1]);
                        return true;
                    case "help":
                        $sender->sendMessage("penalty\n 1: 警告\n 2: キック\n3: そのプレイヤーからコマンドを実行させる(権限のないコマンドはできない)\n 4: コンソールからコマンドを実行する(権限のないコマンドでもできる)\nmessage\n ペナルティーが1の時: 警告の文字\n 2の時: kickされたときに出る文字\n 3の時: コマンド(最初の/を外して)\n 4の時: コマンド(最初の/を外して @pにするとブロックを壊したプレイヤーの名前に変わる)");
                        return true;
                    default:
                        return false;
                }
                break;
        }
     }

    public function onBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $id = $block->getId();
        if($event->isCancelled()) return;
        var_dump($this->b->getAll());
        if($this->b->exists($id))
        {
            $data = $this->b->get($id);
            if(!isset($this->count[$name][$id])) $this->count[$name][$id] = ["start" => microtime(true), "count" => 0];
            $count = $this->count[$name][$id];
            if($data["time"] != 0 and microtime(true) - $count["start"] >= (float)20*(float)60*(float)$data["time"])
            {
                $count = ["start" => microtime(true), "count" => 0];
            }
            $count["count"] ++;
            if($count["count"] > 1 and $count["count"] > $data["count"]){
                $event->setCancelled();
                switch ($this->config->get("penalty")) {
                    case 1:
                        $player->sendMessage($this->config->get("message"));
                        break;
                    case 2:
                        $cmd = "kick ".$name." ".$this->config->get("message");
                        $this->getServer()->dispatchCommand(new ConsoleCommandSender,$cmd);
                        break;
                    case 3:
                        $cmd = $this->config->get("message");
                        $this->getServer()->dispatchCommand($player,$cmd);
                        break;
                    case 4:
                        $cmd = str_replace("@p", $name, $this->config->get("message"));
                        $this->getServer()->dispatchCommand(new ConsoleCommandSender,$cmd);
                        break;
                }
            }
            $this->count[$name][$id] = $count;
        }
    }

    public function checkConfig($config){
        if($config->exists("message") === false){
            $config->set("message","§c透視テクスチャの使用は禁止です");
        }
        if($config->exists("penalty") === false){
            $config->set("penalty",1);
        }
        $config->save();
    }
}