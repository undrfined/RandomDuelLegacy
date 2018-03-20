<?php

namespace VS;


use InvalidStateException;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\Fire;
use pocketmine\block\Glass;
use pocketmine\block\Redstone;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityEatEvent;
use pocketmine\event\entity\EntityEatItemEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryPickupArrowEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\level\LevelInitEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\InventoryType;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\level\Explosion;
use pocketmine\level\format\FullChunk;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\particle\BubbleParticle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\EntityFlameParticle;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\ItemBreakParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\WaterDripParticle;
use pocketmine\level\particle\WaterParticle;
use pocketmine\level\particle\WhiteSmokeParticle;
use pocketmine\level\Position;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\DoorBumpSound;
use pocketmine\level\sound\DoorCrashSound;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\level\sound\ExpPickupSound;
use pocketmine\level\sound\GhastSound;
use pocketmine\level\sound\SplashSound;
use pocketmine\level\sound\TNTPrimeSound;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\BlockEntityDataPacket;
use pocketmine\network\protocol\ChunkRadiusUpdatedPacket;
use pocketmine\network\protocol\ContainerClosePacket;
use pocketmine\network\protocol\ContainerOpenPacket;
use pocketmine\network\protocol\ContainerSetContentPacket;
use pocketmine\network\protocol\ContainerSetSlotPacket;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\MobSpawner;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\utils\UUID;
use TCapi\DatabaseEvent;
use TCapi\TCapi;
use TCapi\TickTask;
use TCAuth\TCAuth;
use TCHub\TCHub;
use TCPermissions\TCPermissions;

/**
 * Class VS
 * @package VS
 */
class VS extends PluginBase implements Listener
{
    const ITEMS1 = [385, 294, 262, 388, 291]; // 369, 283, 325, 290
    const ITEMS2 = [288, 289, 353, 351, 382, 396];
    const ITEMS3 = [20, 145, 46, 347, 120, 90];

    const CHANCES = [369 => 20, 283 => 30, 247 => 40, 325 => 50,
        290 => 10, 384 => 50, 259 => 60, 357 => 10, 341 => 30,
        378 => 30, 175 => 170, 41 => 10, 266 => 120];

    private $caseEid;
    /** @var Config */
    private $config;
    /** @var Config */
    private $top;
    /** @var Config */
    private $keys;
    /** @var Config */
    private $items;
    /** @var Config */
    private $levels;
    /** @var array */
    private $npcs = [];
    /** @var TCapi */
    private $api;
    /**
     * @var array
     */
    public $arenas = [];
    private $i = 0;
    private $water = 0;
    /** @var TCAuth */
    private $auth;


    private $step = 0;

    private $col = [];
    /** @var Config */
    private $translations;

    /**
     *
     */
    function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->api = $this->getServer()->getPluginManager()->getPlugin("TCapi");
        $this->api->registerPlugin($this);
        $this->getServer()->getDefaultLevel()->setTime(Level::TIME_SUNSET);
        $this->getServer()->getDefaultLevel()->stopTime();
        $this->config = new Config($this->getDataFolder() . "config.json", Config::JSON, ["itemFrames" => [[-15.5, 62, -3, TextFormat::RED . "Ключ от кейса"], [-16.5, 62, -3, TextFormat::DARK_AQUA . "="], [-17.5, 62, -3, TextFormat::GOLD . "500 золота"]], "case" => [0, 50, 0], "npc" => [], "arenas" => [["time" => 600, "world" => "world", "spawn1" => [-10, 5, 0, 90, 0], "spawn2" => [10, 5, 0, 90, 0]]], "join" => [0, 0, 0]]);
        $tile = $this->getServer()->getDefaultLevel()->getTile(new Vector3($this->config->get("case")[0], $this->config->get("case")[1], $this->config->get("case")[2]));
        if ($tile instanceof MobSpawner) {
            $tile->setEntityId(0);
        }
        $this->saveResource("translations.yml");
        $this->top = new Config($this->getDataFolder() . "top.json", Config::JSON, []);
        $this->keys = new Config($this->getDataFolder() . "keys.json", Config::JSON, []);
        $this->items = new Config($this->getDataFolder() . "items.json", Config::JSON, []);
        $this->levels = new Config($this->getDataFolder() . "levels.json", Config::JSON, []);
        $this->translations = new Config($this->getDataFolder() . "translations.yml", Config::YAML);
        $this->auth = $this->getServer()->getPluginManager()->getPlugin("TCAuth");
        //for($i = 22; $i < 30; $i++)
        //    $this->getServer()->generateLevel("arena" . $i);
        foreach ($this->config->get("arenas") as $arena) {
            $this->getServer()->generateLevel($arena["world"]);
            $this->arenas[] = ["stopped" => false, "toEnd" => $arena["time"], "body" => [], "players" => [], "free" => true, "wait" => 0, "world" => $arena["world"], "spawn1" => $arena["spawn1"], "spawn2" => $arena["spawn2"]];
            $this->getServer()->loadLevel($arena["world"]);
            $this->getServer()->getLevelByName($arena["world"])->setAutoSave(false);
            $this->getServer()->getLevelByName($arena["world"])->setTime(Level::TIME_SUNSET);
            $this->getServer()->getLevelByName($arena["world"])->stopTime();
        }
        foreach ($this->config->get("npc") as $npc) {
            $this->npcs[] = $npc;
        }
        $this->getServer()->getPluginManager()->addPermission(new Permission("vs.top.create", "Access to create top npcs.", "op"));
        $this->getServer()->getPluginManager()->addPermission(new Permission("vs.reroll", "Access to reroll.", "op"));
        $this->getServer()->getPluginManager()->addPermission(new Permission("vs.npc.create", "Access to create npcs.", "op"));
        for ($i = 0; $i < 30; $i++) {
            $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new TickTask($this, function ($task, $tick) {
                if ($this->i >= 1) {
                    $this->i++;
                    $case = $this->config->get("case");
                    $hh = 30 * 4;
                    $a = sin(deg2rad($this->i / 2)) * 1;
                    $b = cos(deg2rad($this->i / 2)) * 1;
                    $pos = new Vector3($case[0] + $a, $case[1] + ($this->i / $hh), $case[2] + $b);
                    $particle = new FlameParticle($pos);
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    $a = sin(deg2rad($this->i / 2)) * 1;
                    $b = cos(deg2rad($this->i / 2)) * 1;
                    $pos = new Vector3($case[0] - $a, $case[1] + ($this->i / $hh), $case[2] - $b);
                    $particle = new FlameParticle($pos);
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    $particle = new ExplodeParticle(new Vector3($case[0], $case[1] + ($this->i / $hh), $case[2]));
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    if ($this->i % 10 == 0) {
                        $this->getServer()->getDefaultLevel()->addSound(new BlazeShootSound(new Vector3($case[0], $case[1] + ($this->i / $hh), $case[2])));
                    }
                    if ($this->i >= 1500) {
                        $particle = new HugeExplodeParticle(new Vector3($case[0], $case[1] + ($this->i / $hh), $case[2]));
                        $this->getServer()->getDefaultLevel()->addParticle($particle);
                        $this->getServer()->getDefaultLevel()->addSound(new ExplodeSound(new Vector3($case[0], $case[1] + ($this->i / $hh), $case[2])));
                        $this->i = 0;
                    }
                }
                unset($tick);
                unset($task);
            }, $i), $i, 1);
        }
        for ($c = 0; $c < 3; $c++) {
            $this->col[$c] = mt_rand(0, 255);
            if ($this->col[$c] === 255)
                $this->col[$c + 3] = true;
            else
                $this->col[$c + 3] = false;
        }
        $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick) {
            /*$im2 = imagecreatefrompng($this->getServer()->getDataPath() . "image.png");
            list($width, $height) = getimagesize("image.png");
            $im = imagecreatetruecolor(32, 32);
            imagecopyresized($im, $im2, 0, 0, 0, 0, 32, 32, $width, $height);
            for ($x = 0; $x < imagesx($im); $x++) {
                for ($y = 0; $y < imagesy($im); $y++) {
                    $rgb = imagecolorat($im, $x, $y);
                    $colors = imagecolorsforindex($im, $rgb);
                    if ($colors["alpha"] != 127)
                        $this->getServer()->getDefaultLevel()->addParticle(new DustParticle(new Vector3(13, 70 - $y / 10, -19 + $x / 10), $colors["red"], $colors["green"], $colors["blue"]));
                }
            }*/
            /* $a = $this->top->getAll();
             $array = [];
             foreach ($a as $player => $values) {
                 $array[$player] = $values[0] - $values[1];
             }
             $c = mt_rand(0, 2);
             $this->col[$c] += $this->col[$c + 3] ? -6 : 6;
             if($this->col[$c] <= 0) {
                 $this->col[$c + 3] = false;
                 $this->col[$c] = 0;
             }
             if($this->col[$c] >= 255) {
                 $this->col[$c + 3] = true;
                 $this->col[$c] = 255;
             }
             $value = max($array);
             $key = array_search($value, $array);
             $im = imagecreatetruecolor(300, 60);
             $black = imagecolorallocate($im, 255, 255, 255);
             $spaces = "";
             if(strlen($key) < 16) {
                 $spaces = str_repeat(" ", 16 - (strlen($key) / 2));
             }
             imagettftext($im, 20, 0, 0, 30, $black, $this->getServer()->getDataPath() . "arial", "SL");
             $a = "";
             //imagettftext($im, 20, 0, 0, 60, $black, $this->getServer()->getDataPath() . "font.ttf", $spaces . $key . $spaces);

                 for ($y = 0; $y < 60; $y++) {
                     for ($x = 0; $x < 300; $x++) {
                     $rgb = imagecolorat($im, $x, $y);
                     $colors = imagecolorsforindex($im, $rgb);
                     if ($colors["red"] != 0 && $colors["green"] != 0 && $colors["blue"] != 0) {
                         $a .= "X";
                         $this->getServer()->getDefaultLevel()->addParticle(new DustParticle(new Vector3(13, 70 - $y / 10, -19 + $x / 10), $this->col[0], $this->col[1], $this->col[2]));
                     } else $a .= " ";
                 }
                     $a .= "n";
             }
             new Config("lol.yaml", Config::YAML, [$a]);*/
        }, $i), 0);
        for ($i = 0; $i < 25; $i++) {
            $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new TickTask($this, function ($task, $tick) {
                if ($this->water >= 1) {
                    $this->water++;
                    $case = $this->config->get("case");
                    $this->getServer()->getDefaultLevel()->addParticle(new WaterParticle(new Vector3($case[0] + ($a = sin(deg2rad($this->water / 2))), $case[1] + ($this->water / ($hh = 30 * 4)), $case[2] + ($b = cos(deg2rad($this->water / 2))))));
                    $this->getServer()->getDefaultLevel()->addParticle(new WaterParticle(new Vector3($case[0] - $a, $case[1] + ($this->water / $hh), $case[2] - $b)));
                    $this->getServer()->getDefaultLevel()->addParticle(new WaterParticle(new Vector3($case[0] - ($a = cos(deg2rad($this->water / 2))), $case[1] + ($this->water / $hh), $case[2] - ($b = sin(deg2rad($this->water / 2))))));
                    $this->getServer()->getDefaultLevel()->addParticle(new WaterParticle(new Vector3($case[0] + $a, $case[1] + ($this->water / $hh), $case[2] + $b)));
                    $h = 4 / 6;
                    $r = 9 / 6;
                    $R = 2 / 6;
                    //$x = $R * ($m + 1) * sin($m * deg2rad($i)) - $h * cos(($m + 1) * deg2rad($i));
                    //$z = $R * ($m + 1) * cos($m * deg2rad($i)) - $h * sin(($m + 1) * deg2rad($i));
                    $x = ($R - $r) * cos(rad2deg($this->water)) + $h * cos((($R - $r) / $r) * rad2deg($this->water));
                    $z = ($R - $r) * sin(rad2deg($this->water)) + $h * sin((($R - $r) / $r) * rad2deg($this->water));
                    $pos = new Vector3($case[0] + $x, $case[1] + 0.1, $case[2] + $z);
                    $particle = new WaterDripParticle($pos);
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    if ($this->water % 10 == 0) {
                        $this->getServer()->getDefaultLevel()->addSound(new SplashSound(new Vector3($case[0], $case[1] + ($this->water / $hh), $case[2])));
                    }
                    if ($this->water >= 1500) {
                        $this->water = 0;
                    }
                }
                unset($tick);
                unset($task);
            }, $i), $i, 1);
        }
        /*for($i = 0; $i < 20; $i++){
            $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new TickTask($this, function ($task, $tick, $i) {
                if ($this->i >= 1) {
                    $this->i++;
                    $case = $this->config->get("case");
                    $hh = 30 * 4;
                    $a = sin(deg2rad($this->i / 2)) * 1;
                    $b = cos(deg2rad($this->i / 2)) * 1;
                    $pos = new Vector3($case[0] + $a, $case[1] + ($this->i / $hh), $case[2] + $b);
                    $particle = new RedstoneParticle($pos);
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    $a = sin(deg2rad($this->i / 2)) * 1;
                    $b = cos(deg2rad($this->i / 2)) * 1;
                    $pos = new Vector3($case[0] - $a, $case[1] + ($this->i / $hh), $case[2] - $b);
                    $particle = new RedstoneParticle($pos);
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    $a = cos(deg2rad($this->i / 2)) * 1;
                    $b = sin(deg2rad($this->i / 2)) * 1;
                    $pos = new Vector3($case[0] - $a, $case[1] + ($this->i / $hh), $case[2] - $b);
                    $particle = new RedstoneParticle($pos);
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    $a = cos(deg2rad($this->i / 2)) * 1;
                    $b = sin(deg2rad($this->i / 2)) * 1;
                    $pos = new Vector3($case[0] + $a, $case[1] + ($this->i / $hh), $case[2] + $b);
                    $particle = new RedstoneParticle($pos);
                    $this->getServer()->getDefaultLevel()->addParticle($particle);
                    if($this->i >= 2500)
                        $this->i = 0;
                }
            }, $i), $i, 1);
        }*/
        //for($i = 0; $i < 20; $i++){
        /*$this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function ($task, $tick) {
            for ($i = 0; $i < 1800; $i++) {
                $case = $this->config->get("join");
                $h = 4 / 6;
                $r = 9 / 6;
                $R = 2 / 6;
                $m = $R / $r;
                //$x = $R * ($m + 1) * sin($m * deg2rad($i)) - $h * cos(($m + 1) * deg2rad($i));
                //$z = $R * ($m + 1) * cos($m * deg2rad($i)) - $h * sin(($m + 1) * deg2rad($i));
                $x = ($R - $r) * cos(rad2deg($i)) + $h * cos((($R - $r) / $r) * rad2deg($i));
                $z = ($R - $r) * sin(rad2deg($i)) + $h * sin((($R - $r) / $r) * rad2deg($i));
                $pos = new Vector3($case[0] + 0.5 + $x, $case[1] + 0.15, $case[2] + 0.5 + $z);
                $particle = new FlameParticle($pos);
                $this->getServer()->getDefaultLevel()->addParticle($particle);
            }
        }), 20);*/
        //}
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function ($task, $tick) {
            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                if ($player->getInventory()->getItemInHand()->getId() == 369 && !$player->isOnFire()) {
                    $player->fireTicks = 20;
                }
            }
            /*  $text2 = "Открытие кейса";
          for ($i = 0; $i < mb_strlen($text2); $i++) {
              $text .= mb$text2
          }
              foreach ($this->caseEid as $name => $eid) {
                  $pk = new SetEntityDataPacket;
                  $pk->eid = $eid;
                  $pk->metadata = [
                      Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE],
                      Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text],
                      Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1],
                      Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1],
                      Entity::DATA_LEAD_HOLDER => [Entity::DATA_TYPE_LONG, -1],
                      Entity::DATA_LEAD => [Entity::DATA_TYPE_BYTE, 0]
                  ];
                  $this->getServer()->getPlayerExact($name)->dataPacket($pk);
              }*/
            unset($task);
            unset($tick);
        }), 1);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function ($task, $tick) {
            $drawParticle = function (Location $location, Vector3 $v, int $type = 0) {
                $cos = cos(($location->getPitch() + 90) * M_PI / 180);
                $sin = sin(($location->getPitch() + 90) * M_PI / 180);
                $y = $v->getY() * $cos - $v->getZ() * $sin;
                $z = $v->getY() * $sin + $v->getZ() * $cos;
                $v->y = $y;
                $v->z = $z;
                $cos = cos(-$location->getYaw() * M_PI / 180);
                $sin = sin(-$location->getYaw() * M_PI / 180);
                $x = $v->getX() * $cos + $v->getZ() * $sin;
                $z = $v->getX() * -$sin + $v->getZ() * $cos;
                $v->x = $x;
                $v->z = $z;
                $this->getServer()->getDefaultLevel()->addParticle($type == 0 ? new FlameParticle($location->add($v->x, $v->y, $v->z)) : ($type == 1 ? new RedstoneParticle($location->add($v->x, $v->y, $v->z)) : new WaterParticle($location->add($v->x, $v->y, $v->z))));
            };
            $location = $this->location;
            if ($location == null)
                return;
            $grow = 0.2;
            $length = 15;
            $particlesBase = 15;
            $radials = M_PI / 30;
            $radius = 1.5;
            $particlesHelix = 6;
            $baseInterval = 10;
            for ($j = 0; $j < $particlesHelix; $j++) {
                if ($this->step * $grow > $length) {
                    $this->step = 0;
                }
                for ($i = 0; $i < 2; $i++) {
                    $angle = $this->step * $radials + M_PI * $i;
                    $v = new Vector3(cos($angle) * $radius, $this->step * $grow, sin($angle) * $radius);
                    $drawParticle($location, $v);
                }
                if ($this->step % $baseInterval == 0) {
                    for ($i = -$particlesBase; $i <= $particlesBase; $i++) {
                        if ($i == 0) {
                            continue;
                        }
                        $type = 1;
                        if ($i < 0) {
                            $type = 2;
                        }
                        $angle = $this->step * $radials;
                        $v = (new Vector3(cos($angle), 0, sin($angle)))->multiply($radius * $i / $particlesBase);
                        $v->y = $this->step * $grow;
                        $drawParticle($location, $v, $type);
                    }
                }
                $this->step++;
            }
            unset($tick, $task);
        }), 1);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function ($task, $tick) {
            foreach ($this->arenas as $num => $arena) {
                if ($arena["stopped"])
                    continue;
                if ($arena["wait"] > 0) {
                    $this->arenas[$num]["wait"]--;
                    foreach ($this->arenas[$num]["players"] as $player) {
                        $hub = $this->getServer()->getPluginManager()->getPlugin("TCHub");
                        if ($hub instanceof TCHub) {
                            $hub->remove($player);
                        }
                        if ($player instanceof Player && $player->isOnline())
                            $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE_LEVEL)->setValue($arena["wait"] - 1);
                    }
                    if ($arena["wait"] == 8) {
                        foreach ($arena["players"] as $number => $player) {
                            if ($player instanceof Player) {
                                $v = new Vector3($arena["spawn" . ($number + 1)][5], $arena["spawn" . ($number + 1)][6], $arena["spawn" . ($number + 1)][7]);
                                $arr = array_merge(self::ITEMS1, ($this->items->exists(strtolower($player->getName())) && isset($this->items->get(strtolower($player->getName()))[0]) ? $this->items->get(strtolower($player->getName()))[0] : []));
                                if (!isset($arena["reroll"][$number])) {
                                    $this->lighting($player, $v);
                                    $this->arenas[$num]["items"][$number][0] = $arr[array_rand($arr)];
                                    if ($this->arenas[$num]["items"][$number][0] == 351) {
                                        $this->arenas[$num]["meta"][$number][0] = $meta = 1;
                                    } else
                                        if ($this->arenas[$num]["items"][$number][0] == 325) {
                                            $this->arenas[$num]["meta"][$number][0] = $meta = 8;
                                        } else $meta = 0;
                                    $level = $this->getItemLevel($player, $this->arenas[$num]["items"][$number][0]);
                                    $this->arenas[$num]["toremove" . $number][] = $this->dropItem($v->add(0.5, 0, 0.5)->x, $v->add(0.5, 0, 0.5)->y, $v->add(0.5, 0, 0.5)->z, Item::get($this->arenas[$num]["items"][$number][0], $meta), $this->idToName($this->arenas[$num]["items"][$number][0], $meta) . TextFormat::GRAY . "(ур. $level)", $player);
                                }
                            }
                        }
                    }
                    if ($arena["wait"] == 6) {
                        foreach ($arena["players"] as $number => $player) {
                            if ($player instanceof Player) {
                                $v = new Vector3($arena["spawn" . ($number + 1)][8], $arena["spawn" . ($number + 1)][9], $arena["spawn" . ($number + 1)][10]);
                                $arr = array_merge(self::ITEMS2, ($this->items->exists(strtolower($player->getName())) && isset($this->items->get(strtolower($player->getName()))[1]) ? $this->items->get(strtolower($player->getName()))[1] : []));
                                if (!isset($arena["reroll"][$number])) {
                                    $this->lighting($player, $v);
                                    $this->arenas[$num]["items"][$number][1] = $arr[array_rand($arr)];
                                    if ($this->arenas[$num]["items"][$number][1] == 351) {
                                        $this->arenas[$num]["meta"][$number][1] = $meta = 1;
                                    } else
                                        if ($this->arenas[$num]["items"][$number][1] == 325) {
                                            $this->arenas[$num]["meta"][$number][1] = $meta = 8;
                                        } else $meta = 0;
                                    $level = $this->getItemLevel($player, $this->arenas[$num]["items"][$number][1]);
                                    $this->arenas[$num]["toremove" . $number][] = $this->dropItem($v->add(0.5, 0, 0.5)->x, $v->add(0.5, 0, 0.5)->y, $v->add(0.5, 0, 0.5)->z, Item::get($this->arenas[$num]["items"][$number][1], $meta), $this->idToName($this->arenas[$num]["items"][$number][1], $meta) . TextFormat::GRAY . "(ур. $level)", $player);
                                }
                            }
                        }
                    }
                    if ($arena["wait"] == 4) {
                        foreach ($arena["players"] as $number => $player) {
                            if ($player instanceof Player) {
                                $v = new Vector3($arena["spawn" . ($number + 1)][11], $arena["spawn" . ($number + 1)][12], $arena["spawn" . ($number + 1)][13]);
                                $arr = array_merge(self::ITEMS3, ($this->items->exists(strtolower($player->getName())) && isset($this->items->get(strtolower($player->getName()))[2]) ? $this->items->get(strtolower($player->getName()))[2] : []));
                                if (!isset($arena["reroll"][$number])) {
                                    $this->lighting($player, $v);
                                    $this->arenas[$num]["items"][$number][2] = $arr[array_rand($arr)];
                                    if ($this->arenas[$num]["items"][$number][2] == 351) {
                                        $this->arenas[$num]["meta"][$number][2] = $meta = 1;
                                    } else
                                        if ($this->arenas[$num]["items"][$number][2] == 325) {
                                            $this->arenas[$num]["meta"][$number][2] = $meta = 8;
                                        } else $meta = 0;
                                    $level = $this->getItemLevel($player, $this->arenas[$num]["items"][$number][2]);
                                    $this->arenas[$num]["toremove" . $number][] = $this->dropItem($v->add(0.5, 0, 0.5)->x, $v->add(0.5, 0, 0.5)->y, $v->add(0.5, 0, 0.5)->z, Item::get($this->arenas[$num]["items"][$number][2], $meta), $this->idToName($this->arenas[$num]["items"][$number][2], $meta) . TextFormat::GRAY . "(ур. $level)", $player);
                                }
                            }
                        }
                    }
                    if ($arena["wait"] == 1) {
                        foreach ($this->arenas[$num]["players"] as $nump => $player) {
                            if ($player instanceof Player) {
                                $this->hide[$player->getName()] = false;
                                foreach ($player->getLevel()->getPlayers() as $pl) {
                                    $player->showPlayer($pl);
                                }
                            }
                            foreach ($arena["toremove" . $nump] as $eid) {
                                $pk = new ExplodePacket;
                                $pk->x = $arena["spawn" . ($nump + 1)][8];
                                $pk->y = $arena["spawn" . ($nump + 1)][9];
                                $pk->z = $arena["spawn" . ($nump + 1)][10];
                                $pk->radius = 7;
                                $pk->records = [];
                                if ($player instanceof Player)
                                    $player->dataPacket($pk);
                                foreach ($eid as $id) {
                                    $pk0 = new RemoveEntityPacket;
                                    $pk0->eid = $id;
                                    $player->dataPacket($pk0);
                                }
                            }
                            if ($player instanceof Player) {
                                $player->setSneaking(false);
                                $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player) {
                                    $player->sendTip(TextFormat::GREEN . str_repeat("› ", $tick / 2) . "Игра началась!" . str_repeat(" ‹", $tick / 2));
                                    $player->setAllowFlight(false);
                                    $player->setMotion(new Vector3(0, -2, 0));
                                    if ($tick >= 6) {
                                        $task->cancel();
                                    }
                                }, $player), 2);
                                $player->setGamemode(2);
                                $player->getInventory()->clearAll();
                                $player->setNameTag(null);
                                $player->setMaxHealth(40);
                                foreach ($arena["items"][$nump] as $numi => $item) {
                                    if ($item == 351 && $arena["meta"][$nump][$numi] == 1) {
                                        $level = $this->getItemLevel($player, $item);
                                        if ($level === 1) {
                                            $h = 6;
                                        } elseif ($level === 2) {
                                            $h = 10;
                                        } elseif ($level === 3) {
                                            $h = 14;
                                        } elseif ($level === 4) {
                                            $h = 16;
                                        } elseif ($level === 6) {
                                            $h = 18;
                                        } else {
                                            $h = 20;
                                        }
                                        $player->setMaxHealth($player->getMaxHealth() + $h);
                                        continue;
                                    }
                                    $i = Item::get($item, isset($arena["meta"][$nump][$numi]) ? $arena["meta"][$nump][$numi] : 0);
                                    $i->setCustomName($this->idToName($item, isset($arena["meta"][$nump][$numi]) ? $arena["meta"][$nump][$numi] : 0));
                                    $player->getInventory()->addItem($i);
                                }
                                $player->setHealth($player->getMaxHealth());
                            }
                        }
                    }
                } elseif (count($arena["players"]) == 2 && !$arena["stopped"]) {
                    if (--$this->arenas[$num]["toEnd"] == 0) {
                        $this->reset($num, 1);
                    } else {
                        foreach ($this->arenas[$num]["players"] as $player) {
                            if ($player instanceof Player && $player->isOnline())
                                $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE_LEVEL)->setValue($this->arenas[$num]["toEnd"]);
                        }
                    }
                }
            }
            unset($tick);
            unset($task);
        }), 20);
    }


    public function rotate(Location $location, Vector3 $v)
    {
        $cos = cos(($location->getPitch() + 90) * M_PI / 180);
        $sin = sin(($location->getPitch() + 90) * M_PI / 180);
        $y = $v->getY() * $cos - $v->getZ() * $sin;
        $z = $v->getY() * $sin + $v->getZ() * $cos;
        $v->y = $y;
        $v->z = $z;
        $cos = cos(-$location->getYaw() * M_PI / 180);
        $sin = sin(-$location->getYaw() * M_PI / 180);
        $x = $v->getX() * $cos + $v->getZ() * $sin;
        $z = $v->getX() * -$sin + $v->getZ() * $cos;
        $v->x = $x;
        $v->z = $z;
        return $location->add($v->x, $v->y, $v->z);
    }

    private $location = null;

    function onChat(PlayerChatEvent $event)
    {
    }

    /**
     * @param $id
     * @param int $meta
     * @return string
     */
    function idToName($id, $meta = 0)
    {
        switch ($id) {
            case 385:
                return TextFormat::DARK_RED . "Огненный шар";
            case 396:
                return TextFormat::YELLOW . "Невидимая морковка";
            case 351:
                switch ($meta) {
                    case 1:
                        return TextFormat::RED . "Кусочек сердца";
                    default:
                        return "Undefined item";
                }
            case 347:
                return TextFormat::GOLD . "Маховик времени";
            case 325:
                switch ($meta) {
                    case 8:
                        return TextFormat::BLUE . "Сила воды";
                    default:
                        return "Undefined item";
                }
            case 20:
                return TextFormat::YELLOW . "Стеклянный купол";
            case 291:
                return TextFormat::RED . "Снайперская винтовка";
            case 384:
                return TextFormat::GOLD . "Бутыль с ядом";
            case 290:
                return TextFormat::RED . "Базука";
            case 90:
                return TextFormat::DARK_PURPLE . "Случайный портал";
            case 341:
                return TextFormat::DARK_GREEN . "Слизнешар";
            case 378:
                return TextFormat::GOLD . "Огнешар";
            case 247:
                return TextFormat::DARK_RED . "Бомба моментального действия";
            case 120:
                return TextFormat::LIGHT_PURPLE . "Запускатор жемчугов края";
            case 369:
                return TextFormat::RED . "Огненный жезл";
            case 357:
                return TextFormat::RED . "Чёрствое печенье";
            case 280:
                return TextFormat::RED . "Потухший огненный жезл";
            case 388:
                return TextFormat::DARK_GREEN . "Носатая граната";
            case 262:
                return TextFormat::YELLOW . "Стрела света";
            case 353:
                return TextFormat::GREEN . "Скоростной сахарок";
            case 145:
                return TextFormat::BLACK . "Наковальня смерти";
            case 283:
                return TextFormat::DARK_PURPLE . "Меч ангела";
            case 294:
                return TextFormat::GOLD . "Desert Eagle";
            case 288:
                return TextFormat::AQUA . "Магическое перо";
            case 289:
                return TextFormat::DARK_GRAY . "Огненый порошок";
            case 259:
                return TextFormat::RED . "Огнемёт";
            case 382:
                return TextFormat::GOLD . "Блестящий ломтик арбуза";
            case 46:
                return TextFormat::DARK_RED . "Тротил";
            case 41:
                return TextFormat::GOLD . "Куча золота!";
            case 266:
                return TextFormat::GOLD . "Слиток золота";
            case 175:
                return TextFormat::YELLOW . "Немного монет";
            default:
                return "Undefined item";
        }
    }

    /**
     * @param $x
     * @param $y
     * @param $z
     * @param $item
     * @param $text
     * @param Player $player
     * @param int $i
     * @param bool $noItem
     * @return array
     */
    function dropItem($x, $y, $z, $item, $text, Player $player, $i = 0, $noItem = false)
    {
        $pk = null;
        if (!$noItem) {
            $pk = new AddItemEntityPacket();
            $pk->eid = bcadd("1095216660480", mt_rand(0, 0x7fffffff));
            $pk->type = 64;
            $pk->x = $x;
            $pk->y = $y + $i;
            $pk->z = $z;
            $pk->speedX = 0;
            $pk->speedY = 0;
            $pk->speedZ = 0;
            $pk->item = $item;
            $player->dataPacket($pk);
        }
        $pk2 = new AddPlayerPacket();
        $pk2->eid = bcadd("1095216660480", mt_rand(0, 0x7fffffff));
        $pk2->uuid = UUID::fromRandom();
        $pk2->x = $x;
        $pk2->y = $y - 1.62;
        $pk2->z = $z;
        $pk2->speedX = 0;
        $pk2->speedY = 0;
        $pk2->speedZ = 0;
        $pk2->yaw = 0;
        $pk2->pitch = 0;
        $pk2->item = Item::get(0);
        $pk2->metadata = [
            Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE],
            Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text],
            Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1],
            Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1],
            Entity::DATA_LEAD_HOLDER => [Entity::DATA_TYPE_LONG, -1],
            Entity::DATA_LEAD => [Entity::DATA_TYPE_BYTE, 0]
        ];
        $player->dataPacket($pk2);
        return $noItem ? $pk2->eid : [$pk->eid, $pk2->eid];
    }

    /**
     * @param Player $player
     * @param $v
     * @param int $y
     * @param int $z
     */
    function lighting(Player $player, $v, $y = 0, $z = 0)
    {
        if ($v instanceof Vector3) {
            $x = $v->x;
            $y = $v->y;
            $z = $v->z;
        } else $x = $v;
        $pk = new AddEntityPacket();
        $pk->eid = Entity::$entityCount++;
        $pk->type = 93;
        $pk->x = $x + 0.5;
        $pk->y = $y;
        $pk->z = $z + 0.5;
        $pk->speedX = 0;
        $pk->speedY = 0;
        $pk->speedZ = 0;
        $pk->yaw = 0;
        $pk->pitch = 0;
        $pk->metadata = [];
        $player->dataPacket($pk);
    }

    function onPickup(InventoryPickupItemEvent $e)
    {
        $e->setCancelled();
        $item = $e->getItem();
        if ($item->getItem()->getId() === Item::COOKIE && $e->getInventory() instanceof PlayerInventory) {
            $item->close();
            $player = $e->getInventory()->getHolder();
            if ($player instanceof Player) {
                $player->getLevel()->addSound(new DoorBumpSound($item));
                for ($i = 0; $i < 16; $i++)
                    $player->getLevel()->addParticle(new ItemBreakParticle($item, Item::get(Item::COOKIE)));
                $player->attack(1, new EntityDamageEvent($player, EntityDamageEvent::CAUSE_MAGIC, 1));
            }
        }
    }

    /**
     * @param BlockBreakEvent $e
     */
    function onBreak(BlockBreakEvent $e)
    {
        $e->setCancelled(true);
    }

    /**
     * @param BlockPlaceEvent $e
     */
    function onPlace(BlockPlaceEvent $e)
    {
        $e->setCancelled(true);
    }

    function onDrop(PlayerDropItemEvent $event)
    {
        $event->setCancelled();
    }

    private $duels = [];

    function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if ($sender instanceof Player && strtolower($command) == "duel") {
            if (isset($args[0]) && ($player = $this->getServer()->getPlayer($args[0])) instanceof Player && $player->getName() != $sender->getName()) {
                $ingame = false;
                foreach ($this->duels as $number => $duel) {
                    if ($duel[0] == $sender || $duel[1] == $sender) {
                        if ($duel[2] == false) {
                            $arenas = $this->arenas;
                            foreach ($arenas as $n => $a) {
                                if (count($a["players"]) > 0) {
                                    unset($arenas[$number]);
                                }
                            }
                            if (empty($arenas)) {
                                $player->sendTip(TextFormat::RED . "Нет свободных арен!");
                                return true;
                            }
                            shuffle($arenas);
                            $arena = array_search($arenas[0], $this->arenas);
                            $this->addToWaitRoom($duel[0], $arena);
                            $this->addToWaitRoom($duel[1], $arena);
                            $this->duels[$number][2] = true;
                            return true;
                        } else
                            $ingame = true;
                    }
                }
                if ($ingame || $this->isInGame($sender)) {
                    $sender->sendMessage(TextFormat::DARK_RED . " › Вы уже в игре!");
                    return true;
                }
                $player->sendMessage(TextFormat::DARK_RED . " › " . TextFormat::GOLD . $sender->getName() . TextFormat::DARK_RED . " предлагает вам дружественную дуель!\n" . TextFormat::DARK_RED . " › Напишите " . TextFormat::RED . "\"/duel " . $sender->getName() . "\"" . TextFormat::DARK_RED . " для дуели!");
                $sender->sendMessage(TextFormat::DARK_GREEN . " › Запрос на дружественную дуель с игроком " . TextFormat::GOLD . $player->getName() . TextFormat::DARK_GREEN . " успешно отправлен.");
                $this->duels[] = [$sender, $player, false];
                return true;
            } else {
                $sender->sendMessage(TextFormat::DARK_RED . " › Невозможно отправить запрос на дружественную дуель игроку " . TextFormat::GOLD . (isset($player) ? $player->getName() : isset($args[0]) ? $args[0] : "") . "!");
                return true;
            }
        }
        return false;
    }

    function onItemHeld(PlayerItemHeldEvent $event)
    {
        $player = $event->getPlayer();
        if ($event->getItem()->getId() == 0 ||
            ($event->getItem()->getId() == 262 && (!isset($this->cooldown[$player->getName()]["arrow"]) || $this->cooldown[$player->getName()]["arrow"] <= 0)) ||
            ($event->getItem()->getId() == 357 && (!isset($this->cooldown[$player->getName()]["cookie"]) || $this->cooldown[$player->getName()]["cookie"] <= 0)) ||
            ($event->getItem()->getId() == 388 && (!isset($this->cooldown[$player->getName()]["emerald"]) || $this->cooldown[$player->getName()]["emerald"] <= 0)) ||
            ($event->getItem()->getId() == 347 && (!isset($this->cooldown[$player->getName()]["clock"]) || $this->cooldown[$player->getName()]["clock"] <= 0)) ||
            ($event->getItem()->getId() == 382 && (!isset($this->cooldown[$player->getName()]["glisteringMelon"]) || $this->cooldown[$player->getName()]["glisteringMelon"] <= 0)) ||
            ($event->getItem()->getId() == 289 && (!isset($this->cooldown[$player->getName()]["gunpowder"]) || $this->cooldown[$player->getName()]["gunpowder"] <= 0)) ||
            ($event->getItem()->getId() == 385 && (!isset($this->cooldown[$player->getName()]["fireCharge"]) || $this->cooldown[$player->getName()]["fireCharge"] <= 0)) ||
            ($event->getItem()->getId() == 46 && (!isset($this->cooldown[$player->getName()]["tnt"]) || $this->cooldown[$player->getName()]["tnt"] <= 0)) ||
            ($event->getItem()->getId() == 20 && (!isset($this->cooldown[$player->getName()]["glassBlock"]) || $this->cooldown[$player->getName()]["glassBlock"] <= 0)) ||
            ($event->getItem()->getId() == 369 && (!isset($this->cooldown[$player->getName()]["blazeRod"]) || $this->cooldown[$player->getName()]["blazeRod"] <= 0)) ||
            ($event->getItem()->getId() == 353 && (!isset($this->cooldown[$player->getName()]["sugar"]) || $this->cooldown[$player->getName()]["sugar"] <= 0)) ||
            ($event->getItem()->getId() == 396 && (!isset($this->cooldown[$player->getName()]["invisibleCarrot"]) || $this->cooldown[$player->getName()]["invisibleCarrot"] <= 0)) ||
            ($event->getItem()->getId() == 145 && (!isset($this->cooldown[$player->getName()]["anvil"]) || $this->cooldown[$player->getName()]["anvil"] <= 0)) ||
            ($event->getItem()->getId() == 384 && (!isset($this->cooldown[$player->getName()]["xp"]) || $this->cooldown[$player->getName()]["xp"] <= 0)) ||
            ($event->getItem()->getId() == 288 && (!isset($this->cooldown[$player->getName()]["feather"]) || $this->cooldown[$player->getName()]["feather"] <= 0)) ||
            ($event->getItem()->getId() == 290 && (!isset($this->cooldown[$player->getName()]["woodenHoe"]) || $this->cooldown[$player->getName()]["woodenHoe"] <= 0)) ||
            ($event->getItem()->getId() == 283 && (!isset($this->cooldown[$player->getName()]["goldenSword"]) || $this->cooldown[$player->getName()]["goldenSword"] <= 0)) ||
            ($event->getItem()->getId() == 291 && (!isset($this->cooldown[$player->getName()]["stoneHoe"]) || $this->cooldown[$player->getName()]["stoneHoe"] <= 0)) ||
            ($event->getItem()->getId() == 259 && (!isset($this->cooldown[$player->getName()]["flamethrower"]) || $this->cooldown[$player->getName()]["flamethrower"] <= 0)) ||
            ($event->getItem()->getId() == 120 && (!isset($this->cooldown[$player->getName()]["enderpearl"]) || $this->cooldown[$player->getName()]["enderpearl"] <= 0)) ||
            ($event->getItem()->getId() == 90 && (!isset($this->cooldown[$player->getName()]["randomPortal"]) || $this->cooldown[$player->getName()]["randomPortal"] <= 0)) ||
            ($event->getItem()->getId() == 341 && (!isset($this->cooldown[$player->getName()]["slimepearl"]) || $this->cooldown[$player->getName()]["slimepearl"] <= 0)) ||
            ($event->getItem()->getId() == 378 && (!isset($this->cooldown[$player->getName()]["magmapearl"]) || $this->cooldown[$player->getName()]["magmapearl"] <= 0)) ||
            ($event->getItem()->getId() == 247 && (!isset($this->cooldown[$player->getName()]["momentalBomb"]) || $this->cooldown[$player->getName()]["momentalBomb"] <= 0)) ||
            ($event->getItem()->getId() == 325 && (!isset($this->cooldown[$player->getName()]["waterBucket"]) || $this->cooldown[$player->getName()]["waterBucket"] <= 0)) ||
            ($event->getItem()->getId() == 294 && (!isset($this->cooldown[$player->getName()]["goldenHoe"]) || $this->cooldown[$player->getName()]["goldenHoe"] <= 0))
        ) {
            $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE)->setValue(1);
        }
    }

    /**
     * @param PlayerJoinEvent $e
     */
    function onJoin(PlayerJoinEvent $e)
    {
        /* if (in_array(strtolower($e->getPlayer()->getName()), $this->win10warn)) {
             $key = array_search(strtolower($e->getPlayer()->getName()), $this->win10warn);
             unset($this->win10warn[$key]);
             $e->getPlayer()->sendMessage(TextFormat::DARK_RED . "Мы заметили что в предыдущий раз вы вылетели с игры при открытии сундука.\n" . TextFormat::RED . "Если вы играете с Windows 10 Edition напишите " . TextFormat::DARK_RED . "\"/p w10\"" . TextFormat::RED . " что бы избежать дальнейших крашей.");
         }*/
        foreach ($this->config->get("itemFrames") as $num => $frame) {
            $this->eid[$e->getPlayer()->getName()] = $this->dropItem($frame[0], $frame[1], $frame[2], null, $frame[3], $e->getPlayer(), 0, true);
        }
        $frame = $this->config->get("case");
        $this->caseEid[$e->getPlayer()->getName()] = $this->dropItem($frame[0], $frame[1] + 0.5, $frame[2], null, TextFormat::GOLD . "Открытие кейса", $e->getPlayer(), 0, true);
        $e->setJoinMessage(null);
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            if (isset($this->hide[$player->getName()]) && $this->hide[$player->getName()]) {
                $player->hidePlayer($e->getPlayer());
            }
        }
        if (!$this->keys->exists(strtolower($e->getPlayer()->getName()))) {
            $this->keys->set(strtolower($e->getPlayer()->getName()), 0);
            $this->keys->save();
        }
        $e->getPlayer()->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
        /*foreach ($e->getPlayer()->getLevel()->getEntities() as $entity) {
            if ($entity instanceof Human && !($entity instanceof Player)) {
                $pk = new PlayerListPacket();
                $pk->type = PlayerListPacket::TYPE_REMOVE;
                $pk->entries[] = [$entity->getUniqueId()];
                $e->getPlayer()->dataPacket($pk);
            }
        }*/
        $e->getPlayer()->getInventory()->clearAll();
        $e->getPlayer()->setGamemode(2);
        $e->getPlayer()->getAttributeMap()->getAttribute(Attribute::EXPERIENCE_LEVEL)->setValue(0);
        $e->getPlayer()->setMaxHealth(20);
        $this->updateHotbar($e->getPlayer());
        if (!$this->top->exists(strtolower($e->getPlayer()->getName()))) {
            $this->top->set(strtolower($e->getPlayer()->getName()), [0, 0]);
            $this->top->save();
        }
    }


    function updateHotbar(Player $player)
    {
        if ($player->getInventory() != null) {
            $player->getInventory()->clearAll();
            $item = Item::get(Item::MOB_HEAD, 3);
            $item->setCustomName(TextFormat::RESET . TextFormat::DARK_AQUA . "Профиль");
            $player->getInventory()->setItem(0, $item);
            $item = Item::get(54);
            $item->setCustomName(TextFormat::RESET . TextFormat::DARK_PURPLE . "Эффекты");
            $player->getInventory()->setItem(2, $item);
            $item = Item::get(175);
            $item->setCustomName(TextFormat::RESET . TextFormat::GOLD . "У вас " . $this->api->getMoney($player->getName()) . " монет");
            $player->getInventory()->setItem(3, $item);
            $item = Item::get(267);
            $item->setCustomName(TextFormat::RESET . TextFormat::GRAY . "В бой!");
            $player->getInventory()->setItem(4, $item);
            $item = Item::get(340); // 340
            //$item->addEnchantment(Enchantment::getEnchantment(Enchantment::TYPE_INVALID));
            $item->setCustomName(TextFormat::RESET . TextFormat::YELLOW . "Туториал");
            $player->getInventory()->setItem(5, $item);
            $item = Item::get(498);
            $item->setCustomName(TextFormat::RESET . (!isset($this->hide[$player->getName()]) || !$this->hide[$player->getName()] ? (TextFormat::RED . "Скрыть игроков") : (TextFormat::DARK_GREEN . "Показать игроков")));
            $player->getInventory()->setItem(6, $item);
            $item = Item::get(270, 0, $this->keys->get(strtolower($player->getName())));
            $item->setCustomName(TextFormat::RESET . TextFormat::DARK_BLUE . "Ключ от клетки с призом");
            $player->getInventory()->setItem(7, $item);
            /*$item = Item::get(358);
            $colors = [];
            $i = 0;
            for($x = 0; $x < 128; $x++) {
                for ($z = 0; $z < 128; $z++) {
                    $colors[$i++] = "a";
                }
            }
            $tag = new CompoundTag("tag", ["map_uuid" => new StringTag("map_uuid", 24234)]);
            $item->setCompoundTag($tag);
            $player->getInventory()->setItem(8, $item);
            $pk = new ClientboundMapItemPacket();
            $pk->x = 0;
            $pk->z = 0;
            $pk->updateType = 0x06;
            $pk->zOffset = 0;
            $pk->xOffset = 0;
            $pk->mapId = 24234;
            $pk->direction = 0;
            $pk->data = $colors;
            $pk->row = 1;
            $pk->col = 1;
            //$player->dataPacket($pk);*/
        }
    }

    private $eid = [];

    /**
     * @param Player $player
     * @param int|null $arena
     */
    function addToWaitRoom(Player $player, $arena = null)
    {
        $arr = false;
        foreach ($this->duels as $duel) {
            if ($duel[0] == $player || $duel[1] == $player)
                $arr = true;
        }
        if ($this->isInGame($player) || $arr) {
            $player->sendTip(TextFormat::RED . "Вы уже в очереди!");
            return;
        }
        if (empty($arena)) {
            $arenas = $this->arenas;
            if (empty($arenas)) {
                $player->sendTip(TextFormat::RED . "Не создано ни одной арены!");
                return;
            }
            $ar = [];
            foreach ($arenas as $a) {
                if (count($a["players"]) == 1) {
                    $ar[] = $a;
                }
            }
            if (empty($ar))
                $ar = $arenas;
            shuffle($ar);
            $arena = null;
            foreach ($ar as $a) {
                if (!$a["free"])
                    continue;
                $arena = $a;
            }
            if ($arena == null) {
                $player->sendTip(TextFormat::RED . "Нет свободных арен!");
                return;
            }
            $r = 0;
            foreach ($this->arenas as $number => $a) {
                if ($a["world"] == $arena["world"]) {
                    $r = $number;
                }
            }
            $this->arenas[$r]["players"][] = $player;
            $player->sendTip(TextFormat::GREEN . "Вы присоединились к очереди!");
            if (count($this->arenas[$r]["players"]) == 2) {
                $this->arenas[$r]["free"] = false;
                foreach ($this->arenas[$r]["players"] as $n => $p) {
                    if ($p instanceof Player) {
                        $p->setMaxHealth(40);
                        if (isset($this->eid[$p->getName()])) {
                            $eid = $this->eid[$p->getName()];
                            unset($this->eid[$p->getName()]);
                            $pk = new RemoveEntityPacket;
                            $pk->eid = $eid;
                            $p->dataPacket($pk);
                        }
                        if (isset($this->caseEid[$p->getName()])) {
                            $pk = new RemoveEntityPacket;
                            $pk->eid = $this->caseEid[$p->getName()];
                            $p->dataPacket($pk);
                            unset($this->caseEid[$p->getName()]);
                        }
                        if (isset($this->case[$p->getName()])) {
                            unset($this->case[$p->getName()]);
                        }
                        $p->setHealth($p->getMaxHealth());
                        $hub = $this->getServer()->getPluginManager()->getPlugin("TCHub");
                        if ($hub instanceof TCHub) {
                            $hub->hide($p);
                        }
                        $p->sendTip(TextFormat::GREEN . "Игра будет начата через 10 секунд!");
                        $p->getInventory()->clearAll();
                        $p->teleport(new Location($this->arenas[$r]["spawn" . ($n + 1)][0] + 0.5, $this->arenas[$r]["spawn" . ($n + 1)][1], $this->arenas[$r]["spawn" . ($n + 1)][2] + 0.5, $this->arenas[$r]["spawn" . ($n + 1)][3], $this->arenas[$r]["spawn" . ($n + 1)][4], $this->getServer()->getLevelByName($this->arenas[$r]["world"])));
                    }
                }
                $this->arenas[$r]["wait"] = 10;
            }
        } else {
            $r = $arena;
            $this->arenas[$r]["players"][] = $player;
            if (count($this->arenas[$r]["players"]) == 2) {
                $this->arenas[$r]["free"] = false;
                $this->arenas[$r]["friend"] = true;
                foreach ($this->arenas[$r]["players"] as $n => $p) {
                    if ($p instanceof Player) {
                        $p->setMaxHealth(40);
                        if (isset($this->eid[$p->getName()])) {
                            $eid = $this->eid[$p->getName()];
                            unset($this->eid[$p->getName()]);
                            $pk = new RemoveEntityPacket;
                            $pk->eid = $eid;
                            $p->dataPacket($pk);
                        }
                        if (isset($this->caseEid[$p->getName()])) {
                            $pk = new RemoveEntityPacket;
                            $pk->eid = $this->caseEid[$p->getName()];
                            $p->dataPacket($pk);
                            unset($this->caseEid[$p->getName()]);
                        }
                        if (isset($this->case[$p->getName()])) {
                            unset($this->case[$p->getName()]);
                        }
                        $p->setHealth($p->getMaxHealth());
                        $hub = $this->getServer()->getPluginManager()->getPlugin("TCHub");
                        if ($hub instanceof TCHub) {
                            $hub->hide($p);
                        }
                        $p->sendTip(TextFormat::GREEN . "Игра будет начата через 10 секунд!");
                        $p->getInventory()->clearAll();
                        $p->teleport(new Location($this->arenas[$r]["spawn" . ($n + 1)][0] + 0.5, $this->arenas[$r]["spawn" . ($n + 1)][1], $this->arenas[$r]["spawn" . ($n + 1)][2] + 0.5, $this->arenas[$r]["spawn" . ($n + 1)][3], $this->arenas[$r]["spawn" . ($n + 1)][4], $this->getServer()->getLevelByName($this->arenas[$r]["world"])));
                    }
                }
                $this->arenas[$r]["wait"] = 10;
            }
        }
    }

    /**
     * @param int $number
     * @param int $reason
     * @throws \InvalidStateException
     */
    function reset($number, $reason = 0)
    {
        foreach ($this->arenas[$number]["players"] as $player) {
            if ($player instanceof Player) {
                foreach ($this->duels as $num => $duel) {
                    if ($duel[0] == $player || $duel[1] == $player) {
                        unset($this->duels[$num]);
                    }
                }
                $hub = $this->getServer()->getPluginManager()->getPlugin("TCHub");
                if ($hub instanceof TCHub) {
                    $hub->show($player);
                }
                unset($this->lastMove[$player->getName()]);
                if ($player->isOnline()) {
                    $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE_LEVEL)->setValue(0);
                    $player->sendTip($reason == 1 ? (TextFormat::RED . "Время игры вышло! Телепортация в хаб...") : TextFormat::GREEN . "Телепортация в хаб...");
                    $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn()->add(0.5, 0, 0.5));
                    if ($player->hasPermission("tchub.fly")) {
                        $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, Player $player) {
                            $player->setAllowFlight(true);
                            unset($tick, $task);
                        }, $player), 20);

                    }
                    $player->setGamemode(2);
                    $frame = $this->config->get("case");
                    $this->caseEid[$player->getName()] = $this->dropItem($frame[0], $frame[1] + 0.5, $frame[2], null, TextFormat::GOLD . "Открытие кейса", $player, 0, true);
                }
                if ($player->getInventory() != null)
                    $player->getInventory()->clearAll();
                if ($player->isOnline()) {
                    $this->updateHotbar($player);
                    $player->setMaxHealth(20);
                    $player->setHealth($player->getMaxHealth());
                }
            }
        }
        $arena = $this->config->get("arenas")[$number];
        unset($this->arenas[$number]);
        $this->arenas[$number] = ["stopped" => false, "toEnd" => $arena["time"], "body" => [], "players" => [], "free" => true, "wait" => 0, "world" => $arena["world"], "spawn1" => $arena["spawn1"], "spawn2" => $arena["spawn2"]];
        $this->getServer()->unloadLevel($this->getServer()->getLevelByName($arena["world"]));
        try {
            $this->getServer()->loadLevel($arena["world"]);
            $this->getServer()->getLevelByName($arena["world"])->setAutoSave(false);
            $this->getServer()->getLevelByName($arena["world"])->setTime(Level::TIME_SUNSET);
            $this->getServer()->getLevelByName($arena["world"])->stopTime();
        } catch (InvalidStateException $exception) {
            $this->getLogger()->critical("Failed loading arena " . $arena["world"] . "!");
        }
    }

    /**
     * @var array
     */
    private $lastMove;

    function onShift(PlayerToggleSneakEvent $event)
    {
        $player = $event->getPlayer();
        if (($arena = $this->getArena($player)) && $arena["wait"] > 0) {
            if ($player->hasPermission("vs.reroll")) {
                $num = $arena["players"][0] == $player ? 0 : 1;
                if (!isset($arena["reroll"][$num])) {
                    $player->sendTip(TextFormat::GREEN . "Re-rolled!");
                    $arenaNum = $this->getArenaNumber($player);
                    $this->arenas[$arenaNum]["reroll"][$num] = true;
                    $v1 = new Vector3($arena["spawn" . ($num + 1)][5], $arena["spawn" . ($num + 1)][6], $arena["spawn" . ($num + 1)][7]);
                    $v2 = new Vector3($arena["spawn" . ($num + 1)][8], $arena["spawn" . ($num + 1)][9], $arena["spawn" . ($num + 1)][10]);
                    $v3 = new Vector3($arena["spawn" . ($num + 1)][11], $arena["spawn" . ($num + 1)][12], $arena["spawn" . ($num + 1)][13]);
                    $arr = array_merge(self::ITEMS1, ($this->items->exists(strtolower($player->getName())) && isset($this->items->get(strtolower($player->getName()))[0]) ? $this->items->get(strtolower($player->getName()))[0] : []));
                    $this->arenas[$arenaNum]["items"][$num][0] = $arr[array_rand($arr)];
                    $arr = array_merge(self::ITEMS2, ($this->items->exists(strtolower($player->getName())) && isset($this->items->get(strtolower($player->getName()))[1]) ? $this->items->get(strtolower($player->getName()))[1] : []));
                    $this->arenas[$arenaNum]["items"][$num][1] = $arr[array_rand($arr)];
                    $arr = array_merge(self::ITEMS3, ($this->items->exists(strtolower($player->getName())) && isset($this->items->get(strtolower($player->getName()))[2]) ? $this->items->get(strtolower($player->getName()))[2] : []));
                    $this->arenas[$arenaNum]["items"][$num][2] = $arr[array_rand($arr)];
                    $this->lighting($player, $v1);
                    $this->lighting($player, $v2);
                    $this->lighting($player, $v3);
                    if (isset($arena["toremove" . $num])) {
                        foreach ($arena["toremove" . $num] as $eid) {
                            foreach ($eid as $eid0) {
                                $pk = new RemoveEntityPacket;
                                $pk->eid = $eid0;
                                $player->dataPacket($pk);
                            }
                        }
                    }
                    unset($this->arenas[$arenaNum]["toremove" . $num]);
                    unset($this->arenas[$arenaNum]["meta"][$num][0]);
                    unset($this->arenas[$arenaNum]["meta"][$num][1]);
                    unset($this->arenas[$arenaNum]["meta"][$num][2]);
                    $this->arenas[$arenaNum]["toremove" . $num] = [];
                    if ($this->arenas[$arenaNum]["items"][$num][0] == 351) {
                        $meta = 1;
                        $this->arenas[$arenaNum]["meta"][$num][0] = $meta;
                    } else
                        if ($this->arenas[$arenaNum]["items"][$num][0] == 325) {
                            $this->arenas[$arenaNum]["meta"][$num][0] = 8;
                            $meta = 8;
                        } else $meta = 0;
                    $level = $this->getItemLevel($player, $this->arenas[$arenaNum]["items"][$num][0]);
                    $this->arenas[$arenaNum]["toremove" . $num][] = $this->dropItem($v1->add(0.5, 0, 0.5)->x, $v1->add(0.5, 0, 0.5)->y, $v1->add(0.5, 0, 0.5)->z, Item::get($this->arenas[$arenaNum]["items"][$num][0], $meta), $this->idToName($this->arenas[$arenaNum]["items"][$num][0], $meta) . TextFormat::GRAY . "(ур. $level)", $player);
                    if ($this->arenas[$arenaNum]["items"][$num][1] == 351) {
                        $meta = 1;
                        $this->arenas[$arenaNum]["meta"][$num][1] = $meta;
                    } else
                        if ($this->arenas[$arenaNum]["items"][$num][1] == 325) {
                            $this->arenas[$arenaNum]["meta"][$num][1] = 8;
                            $meta = 8;
                        } else $meta = 0;
                    $level = $this->getItemLevel($player, $this->arenas[$arenaNum]["items"][$num][1]);
                    $this->arenas[$arenaNum]["toremove" . $num][] = $this->dropItem($v2->add(0.5, 0, 0.5)->x, $v2->add(0.5, 0, 0.5)->y, $v2->add(0.5, 0, 0.5)->z, Item::get($this->arenas[$arenaNum]["items"][$num][1], $meta), $this->idToName($this->arenas[$arenaNum]["items"][$num][1], $meta) . TextFormat::GRAY . "(ур. $level)", $player);
                    if ($this->arenas[$arenaNum]["items"][$num][2] == 351) {
                        $meta = 1;
                        $this->arenas[$arenaNum]["meta"][$num][2] = $meta;
                    } else
                        if ($this->arenas[$arenaNum]["items"][$num][2] == 325) {
                            $this->arenas[$arenaNum]["meta"][$num][2] = 8;
                            $meta = 8;
                        } else $meta = 0;
                    $level = $this->getItemLevel($player, $this->arenas[$arenaNum]["items"][$num][2]);
                    $this->arenas[$arenaNum]["toremove" . $num][] = $this->dropItem($v3->add(0.5, 0, 0.5)->x, $v3->add(0.5, 0, 0.5)->y, $v3->add(0.5, 0, 0.5)->z, Item::get($this->arenas[$arenaNum]["items"][$num][2], $meta), $this->idToName($this->arenas[$arenaNum]["items"][$num][2], $meta) . TextFormat::GRAY . "(ур. $level)", $player);
                } else {
                    $player->sendTip(TextFormat::DARK_RED . "Вы уже использовали re-roll!");
                }
            } else
                $player->sendTip(TextFormat::RED . "Для получения другого случайного лута вы должны иметь привилегию VIP или выше!\n" . TextFormat::DARK_GREEN . "Купить привилегию вы можете на сайте trappedchest.ru");
        }
    }


    /**
     * @param PlayerMoveEvent $e
     */
    function onMove(PlayerMoveEvent $e)
    {
        if ($e->isCancelled())
            return;
        $player = $e->getPlayer();
        /*$bytes = $e->getPlayer()->getSkinData();
        $x = 8;
        $y = 7;
        $c = 456 * 4;
        while ($y < 16 && $y > 6) {
            $cid = substr($bytes, $c, 3);
            $e->getPlayer()->getLevel()->addParticle(new DustParticle($e->getPlayer()->add($x / 10, -($y / 10) + $player->getEyeHeight() * 2), ord($cid{0}), ord($cid{1}), ord($cid{2})));
            $x++;
            $c += 4;
            if ($x === 16) {
                $x = 8;
                $c += 224;
                $y++;
            }
        }*/
        /*$x = 0;
        $y = 7;
        $c = 448 * 4;
        while ($y < 16 && $y > 6) {
            $cid = substr($bytes, $c, 3);
            $e->getPlayer()->getLevel()->addParticle(new DustParticle($e->getPlayer()->add(0, $y / 10 + $player->getEyeHeight(), $x / 10), ord($cid{0}), ord($cid{1}), ord($cid{2})));
            $x++;
            $c += 4;
            if ($x === 8) {
                $x = 0;
                $c += 216;
                $y++;
            }
        }*/
        if (($arena = $this->getArena($player)) && $arena["wait"] > 0 && new Vector3($e->getFrom()->x, $e->getFrom()->y, $e->getFrom()->z) != new Vector3($e->getTo()->x, $e->getTo()->y, $e->getTo()->z)) {
            $e->setCancelled();
            return;
        }
        if ($this->isPlaying($player) && $player->getInventory()->getItemInHand()->getId() == 369 && ($player->getLevel()->getBlock($player)->getId() == Block::WATER || $player->getLevel()->getBlock($player)->getId() == Block::STILL_WATER)) {
            $i = Item::get(280);
            $i->setCustomName($this->idToName(280));
            $player->getInventory()->setItemInHand($i);
        }
        if ($player->getLevel() == $this->getServer()->getDefaultLevel()) {
            foreach ($this->npcs as $npc) {
                if ((new Vector3($npc[0], $npc[1], $npc[2]))->distance($player) < 25) {
                    foreach ($player->getLevel()->getEntities() as $entity) {
                        if ($entity->distance(new Vector3($npc[0], $npc[1], $npc[2])) <= 0.1) {
                            $pk = new MovePlayerPacket;
                            $pk->eid = $entity->getId();
                            $pk->x = $entity->x;
                            $pk->y = $entity->y + $entity->getEyeHeight();
                            $pk->z = $entity->z;
                            $pk->bodyYaw = $player->getMyYaw($player->x - $entity->x, $player->z - $entity->z);
                            $pk->pitch = $player->getMyPitch($entity, $player);
                            $pk->yaw = $player->getMyYaw($player->x - $entity->x, $player->z - $entity->z);
                            $pk->mode = MovePlayerPacket::MODE_NORMAL;
                            $player->dataPacket($pk);
                        }
                    }
                }
            }
        }
        if (!isset($this->teleporting[$player->getName()]) || $this->teleporting[$player->getName()] == false) {
            $this->lastMove[$player->getName()][] = [time(), $e->getTo(), $e->getPlayer()];
            foreach ($this->lastMove[$player->getName()] as $num => $move) {
                if ($move[0] < (time() - 10)) {
                    unset($this->lastMove[$player->getName()][$num]);
                }
            }
        }
    }

    /**
     * @param Player $player
     * @return bool
     */
    function isInGame(Player $player)
    {
        foreach ($this->arenas as $arena) {
            if (in_array($player, $arena["players"]))
                return true;
        }
        return false;
    }

    /**
     * @param Player $player
     * @return bool
     */
    function isPlaying(Player $player)
    {
        foreach ($this->arenas as $arena) {
            if (in_array($player, $arena["players"]) && !$arena["free"])
                return true;
        }
        return false;
    }

    /**
     * @param Player $player
     * @return bool|mixed
     */
    function getArena(Player $player)
    {
        foreach ($this->arenas as $number => $arena) {
            if (in_array($player, $arena["players"]))
                return $this->arenas[$number];
        }
        return false;
    }

    /**
     * @param Player $player
     * @return bool|int|string
     */
    function getArenaNumber(Player $player)
    {
        foreach ($this->arenas as $number => $arena) {
            if (in_array($player, $arena["players"]))
                return $number;
        }
        return false;
    }

    /**
     *
     */
    function updateTop()
    {
        $a = $this->top->getAll();
        $array = [];
        foreach ($a as $player => $values) {
            $array[$player] = $values[0] - $values[1];
        }
        foreach ($this->npcs as $npc) {
            foreach ($this->getServer()->getDefaultLevel()->getEntities() as $entity) {
                if ((new Vector3($npc[0], $npc[1], $npc[2]))->distance($entity) <= 0.1) {
                    if ($entity instanceof Human) {
                        $value = max($array);
                        $key = array_search($value, $array);
                        $entity->setNameTag($key);
                        $entity->setSkin($this->api->loadSkin($key), $this->api->loadSkinName($key));
                        $permissions = $this->getServer()->getPluginManager()->getPlugin("TCPermissions");
                        if ($permissions instanceof TCPermissions) {
                            $entity->setNameTag($permissions->getGroupColor($key) . $key);
                        }
                        unset($array[$key]);
                    }
                }
            }
        }
    }

    /**
     * @var array
     */
    private
        $teleporting = [];

    /**
     * @param EntityDamageEvent $e
     */
    function onAttack(EntityDamageEvent $e)
    {
        if ($e->isCancelled())
            return;
        $player = $e->getEntity();
        if ($player instanceof Player && $e->getCause() == EntityDamageEvent::CAUSE_VOID && !$this->isPlaying($player)) {
            if (!isset($this->upgrading[$player->getId()])) {
                $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn()->add(0.5, 0, 0.5));
                $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, Player $player) {
                    $player->getLevel()->addParticle(new HugeExplodeParticle($player), [$player]);
                    $player->getLevel()->addSound(new ExplodeSound($player), [$player]);
                    unset($tick);
                    unset($task);
                }, $player), 2);
            } else {
                $rand = mt_rand(0, 360);
                $pedestal = new Vector3(979.5, 16.5, 704.5);
                $x = sin(rad2deg($rand)) * 11 + 979.5;
                $z = cos(rad2deg($rand)) * 11 + 704.5;
                $yaw = $player->getMyYaw($pedestal->x - $x, $pedestal->z - $z);
                $pitch = $player->getMyPitch($player, $pedestal->add(0, $player->getEyeHeight()));
                $player->teleport(new Vector3($x, 14, $z), $yaw, $pitch);
            }
            if ($player->hasPermission("tchub.fly"))
                $player->setAllowFlight(true);
            $e->setCancelled();
            return;
        }
        if ($player instanceof Player && !$this->isPlaying($player)) {
            $e->setCancelled();
            if ($player->isOnFire())
                $player->extinguish();
            return;
        }
        foreach ($this->npcs as $npc) {
            if ((new Vector3($npc[0], $npc[1], $npc[2]))->equals($player)) {
                if (!$e instanceof EntityDamageByEntityEvent) {
                    $e->setCancelled();
                    return;
                } else {
                    $damager = $e->getDamager();
                    if ($damager instanceof Player) {
                        $nametag = explode(" ", $player->getNameTag());
                        if (isset($nametag[1]))
                            $nametag = $nametag[1];
                        else
                            $nametag = $player->getNameTag();
                        $nametag = TCapi::uncolorize($nametag);
                        $damager->sendMessage(TextFormat::BLACK . str_repeat("-", 5) . TextFormat::GOLD . " $nametag " . TextFormat::BLACK . str_repeat("-", 5) . "\n" . (($pl = $this->getServer()->getPlayerExact($nametag)) instanceof Player ? TextFormat::GREEN . "Онлайн, " . ($this->isInGame($pl) ? "на арене" : "не на арене") : TextFormat::RED . "Оффлайн") . "\n" . TextFormat::DARK_GREEN . "Побед: " . $this->top->get($nametag)[0] . "\n" . TextFormat::DARK_RED . "Поражений: " . $this->top->get($nametag)[1] . "\n" . TextFormat::BLACK . str_repeat("-", 11 + strlen($nametag)));
                        $e->setCancelled();
                        return;
                    }
                }
            }
        }
        if ($player instanceof Human && !($player instanceof Player) && $e instanceof EntityDamageByEntityEvent) {
            if ($e->getDamager() instanceof Player) {
                $this->addToWaitRoom($e->getDamager());
                $e->setCancelled();
                return;
            }
        }
        if (!$player instanceof Player)
            return;
        $arena = $this->getArena($player);
        if ($arena["stopped"]) {
            $e->setCancelled();
            return;
        }
        $damager = null;
        if ($e instanceof EntityDamageByEntityEvent)
            $damager = $e->getDamager();
        if ($damager instanceof Player && ($damager->getInventory()->getItemInHand()->getId() == 256 || $damager->getInventory()->getItemInHand()->getId() == 294 || $damager->getInventory()->getItemInHand()->getId() == 290 || $damager->getInventory()->getItemInHand()->getId() == 291)) {
            $e->setCancelled();
            return;
        }

        if ($damager instanceof Player && $damager->getInventory()->getItemInHand()->getId() == 283) {
            if (!$this->isOnCooldown($damager, "goldenSword")) {
                $e->setKnockBack(1.1);
                $this->addReloadTask($damager, 20 * 4, 283, "goldenSword");
            } else {
                $e->setCancelled();
                return;
            }
        }
        if ($damager instanceof Player && $damager->getInventory()->getItemInHand()->getId() == 145) {
            $e->setDamage($e->getFinalDamage() + mt_rand(7, 12));
        }
        if ($this->isPlaying($player)) {
            $level = $player->getLevel();
            $level->addParticle(new DestroyBlockParticle($player, new Redstone));
            if ($player->getHealth() - $e->getFinalDamage() <= 0) {
                $e->setCancelled();
                $health = $max = 0;
                foreach ($arena["players"] as $p) {
                    if ($p instanceof Player) {
                        if ($player != $p) {
                            $name = $p->getName();
                            $max = $p->getMaxHealth();
                            $health = $p->getHealth();
                        }
                    }
                }
                $player->sendTip(TextFormat::DARK_RED . TextFormat::ITALIC . "Вы проиграли!");
                $player->getLevel()->addSound(new GhastSound($player), [$player]);
                if (isset($name)) {
                    $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player, $health, $max, $name) {
                        if ($tick === 1) {
                            $player->sendTip(TextFormat::DARK_RED . "У противника осталось " . ($health / 2) . " из " . ($max / 2) . " сердец.");
                            $player->getLevel()->addSound(new GhastSound($player), [$player]);
                        } elseif ($tick === 2) {
                            $player->sendTip(TextFormat::DARK_RED . "Если ваш противник читер, напишите в чате \"/report $name\"!");
                            $player->getLevel()->addSound(new GhastSound($player), [$player]);
                        } else {
                            $task->cancel();
                        }
                    }, $player, $health, $max, $name), 20 * 2, 20 * 2);
                }
                $player->setGamemode(3);
                foreach ($arena["players"] as $p) {
                    if ($p instanceof Player) {
                        $p->setNameTag($p->getDisplayName());
                        if ($player != $p) {
                            $p->getLevel()->addSound(new ExpPickupSound($p), [$p]);
                            $p->sendTip(TextFormat::GREEN . TextFormat::ITALIC . "Вы победили!");
                            $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player, $arena, $name) {
                                if ($tick === 1) {
                                    $player->getLevel()->addSound(new ExpPickupSound($player), [$player]);
                                    $player->sendTip((isset($arena["friend"]) ? TextFormat::RED . "Вы не получили денег так как эта дуель была дружеская." : TextFormat::GOLD . " Вы получили " . ($money = mt_rand(3, 8)) . " монет."));
                                    if (!isset($arena["friend"]) && isset($money))
                                        $this->api->addMoney($player->getName(), $money);
                                } elseif ($tick === 2) {
                                    $player->sendTip(TextFormat::DARK_RED . "Если ваш противник читер, напишите в чате \"/report $name\"!");
                                    $player->getLevel()->addSound(new ExpPickupSound($player), [$player]);
                                } else {
                                    $task->cancel();
                                }
                            }, $p, $arena, $player->getName()), 20 * 2, 20 * 2);
                            $a = $this->top->get(strtolower($p->getName()));
                            $a[0] += 1;
                        } else {
                            $a = $this->top->get(strtolower($p->getName()));
                            $a[1]++;
                        }
                        $this->top->set(strtolower($p->getName()), $a);
                        unset($this->cooldown[$p->getName()]);
                        unset($this->lastMove[$p->getName()]);
                        $p->getInventory()->clearAll();
                        $p->getAttributeMap()->getAttribute(Attribute::EXPERIENCE_LEVEL)->setValue(0);
                        $p->getAttributeMap()->getAttribute(Attribute::EXPERIENCE)->setValue(0);
                    }
                }
                $this->top->save();
                $this->arenas[$this->getArenaNumber($player)]["stopped"] = true;
                $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, $player) {
                    $this->updateTop();
                    $this->reset($this->getArenaNumber($player), 0);
                    unset($tick);
                    unset($task);
                }, $player), 20 * 8);
            }
        }
    }


    /**
     * @param SignChangeEvent $e
     */
    function onSignChange(SignChangeEvent $e)
    {
        if (strtolower($e->getLine(0)) == "tcvs") {
            $e->setLine(0, TextFormat::DARK_GRAY . str_repeat("-", 15));
            $e->setLine(1, TextFormat::AQUA . "Trapped" . TextFormat::BLUE . "Chest");
            $e->setLine(2, TextFormat::DARK_RED . TextFormat::BOLD . "Random Duel");
            $e->setLine(3, TextFormat::DARK_GRAY . str_repeat("-", 15));
        }
        if (strtolower($e->getLine(0)) == "vs") {
            $player = $e->getPlayer();
            if ($e->getLine(1) == "" && $player->hasPermission("vs.npc.create")) {
                $level = $player->getLevel();
                $block = $e->getBlock();
                $level->setBlock($block, new Air);
                $player->sendTip(TextFormat::GREEN . "NPC to random arena successfully created.");
                $npc = new Human($player->chunk,
                    new CompoundTag("", [
                            "Pos" => new ListTag("Pos", [
                                new DoubleTag("", $block->getX() + 0.5),
                                new DoubleTag("", $block->getY()),
                                new DoubleTag("", $block->getZ() + 0.5)
                            ]),
                            "Motion" => new ListTag("Motion", [
                                new DoubleTag("", 0),
                                new DoubleTag("", 0),
                                new DoubleTag("", 0)
                            ]),
                            "Rotation" => new ListTag("Rotation", [
                                new FloatTag("", $player->getYaw()),
                                new FloatTag("", $player->getPitch())
                            ]),
                            "Skin" => new CompoundTag("Skin", [
                                "Data" => new StringTag("Data", $player->getSkinData())
                            ])
                        ]
                    ));
                $npc->spawnToAll();
            } elseif (strtolower($e->getLine(1)) == "top" && $player->hasPermission("vs.top.create")) {
                $level = $player->getLevel();
                $block = $e->getBlock();
                $level->setBlock($block, new Air);
                $player->sendTip(TextFormat::GREEN . "NPC to top successfully created.");
                $npc = new Human($player->chunk,
                    new CompoundTag("", [
                            "Pos" => new ListTag("Pos", [
                                new DoubleTag("", $block->getX() + 0.5),
                                new DoubleTag("", $block->getY()),
                                new DoubleTag("", $block->getZ() + 0.5)
                            ]),
                            "Motion" => new ListTag("Motion", [
                                new DoubleTag("", 0),
                                new DoubleTag("", 0),
                                new DoubleTag("", 0)
                            ]),
                            "Rotation" => new ListTag("Rotation", [
                                new FloatTag("", $player->getYaw()),
                                new FloatTag("", $player->getPitch())
                            ]),
                            "Skin" => new CompoundTag("Skin", [
                                "Data" => new StringTag("Data", $player->getSkinData())
                            ])
                        ]
                    ));
                $npc->spawnToAll();
                $npc->setDataProperty(Entity::DATA_NAMETAG, Entity::DATA_TYPE_STRING, $player->getName());
                $npc->setDataProperty(Entity::DATA_SHOW_NAMETAG, Entity::DATA_TYPE_BYTE, 1);
                $this->npcs[] = [$npc->x, $npc->y, $npc->z];
                $array = $this->config->getNested("npc");
                $array[] = [$npc->x, $npc->y, $npc->z];
                $this->config->set("npc", $array);
                $this->config->save();
            }
        }
    }

    /**
     * @var array
     */
    public
        $hoes = [];
    /**
     * @var array
     */
    public
        $shovels = [];
    /**
     * @var array
     */
    public
        $cooldown = [];

    /**
     * @param PlayerQuitEvent $e
     */
    function onLeave(PlayerQuitEvent $e)
    {
        if (isset($this->inUpgradeChest[$e->getPlayer()->getId()])) {
            $this->win10warn[] = strtolower($e->getPlayer()->getName());
        }
        unset($this->upgrading[$e->getPlayer()->getId()]);
        unset($this->inUpgradeChest[$e->getPlayer()->getId()]);
        foreach ($this->duels as $number => $duel) {
            if ($duel[0] == $e->getPlayer() || $duel[1] == $e->getPlayer()) {
                unset($this->duels[$number]);
            }
        }
        unset($this->eid[$e->getPlayer()->getName()]);
        unset($this->caseEid[$e->getPlayer()->getName()]);
        $e->setQuitMessage(null);
        if (isset($this->hide[$e->getPlayer()->getName()])) {
            unset($this->hide[$e->getPlayer()->getName()]);
        }
        if (isset($this->case[$e->getPlayer()->getName()])) {
            unset($this->case[$e->getPlayer()->getName()]);
        }
        $arena = $this->getArena($e->getPlayer());
        $arenaNum = $this->getArenaNumber($e->getPlayer());
        if ($arena && count($arena["players"]) == 2 && !$arena["stopped"]) {
            $this->arenas[$arenaNum]["stopped"] = true;
            foreach ($arena["players"] as $player) {
                if ($player instanceof Player) {
                    $player->setNameTag($player->getDisplayName());
                    if ($player != $e->getPlayer()) {
                        $player->getLevel()->addSound(new ExpPickupSound($player), [$player]);
                        $player->sendTip(TextFormat::GREEN . TextFormat::ITALIC . "Вы победили!");
                        $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player, $arena) {
                            if ($tick === 1) {
                                $player->getLevel()->addSound(new ExpPickupSound($player), [$player]);
                                $player->sendTip((isset($arena["friend"]) ? TextFormat::RED . "Вы не получили денег так как эта дуель была дружеская." : TextFormat::GOLD . " Вы получили " . ($money = mt_rand(3, 8)) . " монет."));
                                if (!isset($arena["friend"]))
                                    if (isset($money)) {
                                        $this->api->addMoney($player->getName(), $money);
                                    }
                            } elseif ($tick === 2) {
                                $task->cancel();
                            }
                        }, $player, $arena), 20 * 2, 20 * 2);
                        $player->getInventory()->clearAll();
                        unset($this->cooldown[$player->getName()]);
                        unset($this->lastMove[$player->getName()]);
                        $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE_LEVEL)->setValue(0);
                        $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE)->setValue(0);
                        $a = $this->top->get(strtolower($player->getName()));
                        $a[0]++;
                        $this->top->set(strtolower($player->getName()), $a);
                        $this->top->save();
                    } else {
                        unset($this->cooldown[$player->getName()]);
                        unset($this->lastMove[$player->getName()]);
                        $a = $this->top->get(strtolower($player->getName()));
                        $a[1]++;
                        $this->top->set(strtolower($player->getName()), $a);
                        $this->top->save();
                    }
                }
            }
            $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, $p) {
                $this->reset($this->getArenaNumber($p));
                unset($tick);
                unset($task);
            }, $e->getPlayer()), 20 * 8);
        } elseif ($arena && count($arena["players"]) == 1) {
            $a = $this->getArenaNumber($e->getPlayer());
            $this->arenas[$a]["players"] = [];
        } elseif ($arena && count($arena["players"]) == 2 && $arena["stopped"]) {
            $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, $player) {
                $this->reset($this->getArenaNumber($player));
                unset($tick);
                unset($task);
            }, $e->getPlayer()), 20);
        }
    }

    /**
     * @var array
     */
    private
        $fire = [];

    private
        $case = [];

    private
        $hide = [];

    function isOnCooldown(Player $player, string $name)
    {
        return !(!isset($this->cooldown[$player->getName()][$name]) || $this->cooldown[$player->getName()][$name] <= 0);
    }

    function onBucket(PlayerBucketEmptyEvent $event)
    {
        $event->setCancelled();
    }

    function onArrowPickup(InventoryPickupArrowEvent $event)
    {
        $event->setCancelled();
    }

    function addReloadTask(Player $player, int $time, int $id, string $name)
    {
        $this->cooldown[$player->getName()][$name] = $time;
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player, int $time, int $id, string $name) {
            if ($player->isOnline() && isset($this->cooldown[$player->getName()][$name])) {
                if ($player->getInventory()->getItemInHand()->getId() == $id) {
                    $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE)->setValue($tick / $time);
                    if ($tick >= $time)
                        $player->getAttributeMap()->getAttribute(Attribute::EXPERIENCE)->setValue(1);
                }
                $this->cooldown[$player->getName()][$name]--;
                if ($tick >= $time) {
                    $player->sendPopup($this->idToName($id) . TextFormat::GREEN . " перезаряжен!");
                    $task->cancel();
                }
            } else {
                $task->cancel();
                unset($this->cooldown[$player->getName()][$name]);
            }
        }, $player, $time, $id, $name), 1);
    }

    private $upgrading = [];
    private $inUpgradeChest = [];
    private $win10warn = [];
    const UPGRADE = [
        283 => [40, 80],
        259 => [35, 70],
        384 => [60, 120],
        369 => [55, 110],
        120 => [15, 30, 60],
        262 => [10, 20, 40, 80, 160],
        388 => [30, 60],
        382 => [15, 30],
        396 => [20, 40],
        347 => [10, 20],
        90 => [20, 40],
        289 => [40, 80],
        385 => [30, 60],
        46 => [80, 160],
        20 => [20, 40],
        353 => [25, 50, 100],
        325 => [100, 200],
        145 => [10, 20],
        288 => [20, 40, 80, 160],
        290 => [100, 200, 400],
        291 => [40, 80],
        294 => [15, 30, 60, 120],
        357 => [1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048],
        341 => [15, 30, 60],
        378 => [15, 30, 60],
        351 => [15, 30, 60, 120, 240]
    ];

    const PAID_ITEMS = [369, 283, 247, 325, 290, 384, 259, 357, 341, 378];
    const PAID_ITEMS_SLOTS = [369 => 0, 283 => 0, 247 => 2, 325 => 0, 290 => 0, 384 => 0, 259 => 0, 357 => 2, 341 => 2, 378 => 2];

    function getUpgradeCost(Player $player, $item)
    {
        $level = $this->getItemLevel($player, $item);
        return self::UPGRADE[$item][$level - 1];
    }

    function onItemEaten(EntityEatEvent $event)
    {
        $player = $event->getEntity();
        if ($event instanceof EntityEatItemEvent and $player instanceof Player and $event->getFoodSource() instanceof Item and $event->getFoodSource()->getId() === Item::GOLDEN_APPLE and isset($this->upgrading[$player->getId()])) {
            $this->api->safeReduceMoney($player->getName(), $this->getUpgradeCost($player, $this->upgrading[$player->getId()]->getId()));
            $level =   $this->getItemLevel($player, $this->upgrading[$player->getId()]->getId());
            $this->levels->setNested(strtolower($player->getName()) . "." . $this->upgrading[$player->getId()]->getId(), $level);
            $this->levels->save();
            $player->sendMessage($this->idToName($this->upgrading[$player->getId()]->getId(), $this->upgrading[$player->getId()]->getDamage()) . TextFormat::GOLD . " улучшен до уровня $level!");
            if ($level >= count(self::UPGRADE[$this->upgrading[$player->getId()]->getId()]) + 1) {
                $up = Item::get(Item::APPLE)
                    ->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Предмет улучшен до последнего уровня!");
            } elseif (($current = $this->api->getMoney($player->getName())) < ($cost = $this->getUpgradeCost($player, $this->upgrading[$player->getId()]->getId()))) {
                $up = Item::get(Item::APPLE)
                    ->setCustomName(TextFormat::RESET . TextFormat::DARK_RED . "Улучшение стоит $cost монет, у вас есть только $current");
            } else {
                $up = Item::get(Item::GOLDEN_APPLE)
                    ->setCustomName(TextFormat::RESET . TextFormat::GOLD . "Улучшить предмет до уровня " . ($level + 1));
            }
            $event->setResidue($up);
            $event->setAdditionalEffects([]);
            $item = $this->upgrading[$player->getId()];
            $item->setCount($level);
            $player->getInventory()->setItem(3, $item
                ->setCustomName(TextFormat::RESET . TextFormat::DARK_AQUA . "Текущий предмет: " . $this->idToName($item->getId(), $item->getDamage())));
        }
    }

    function onTransaction(DataPacketReceiveEvent $event)
    {
        $packet = $event->getPacket();
        $player = $event->getPlayer();
        if ($packet instanceof ContainerSetSlotPacket) {
            $id = intval(explode(":", explode("(", $packet->item)[1])[0]);
            $item = Item::get($id, intval(explode(":", explode("(", $packet->item)[1])[1]),   $this->getItemLevel($player, $id));
            if ($packet->windowid === 0
                and !isset($this->upgrading[$player->getId()])
                and isset($this->inUpgradeChest[$player->getId()])
                and $this->inUpgradeChest[$player->getId()] == true
                and in_array($id, array_keys(self::UPGRADE))
            ) {
                if ($this->isUnlocked($player, $item->getId())) {
                    $this->updateChest($player);
                    $player->sendPopup(TextFormat::DARK_RED . "Этот предмет ещё не открыт.");
                    return;
                }
                $this->upgrading[$player->getId()] = $item;
                $pk = new ContainerClosePacket;
                $pk->windowid = 1;
                $player->dataPacket($pk);
                $rand = mt_rand(0, 360);
                $pedestal = new Vector3(979.5, 16.5, 704.5);
                $x = sin(rad2deg($rand)) * 11 + 979.5;
                $z = cos(rad2deg($rand)) * 11 + 704.5;
                $yaw = $player->getMyYaw($pedestal->x - $x, $pedestal->z - $z);
                $pitch = $player->getMyPitch($player, $pedestal->add(0, $player->getEyeHeight()));
                $player->teleport(new Vector3($x, 14, $z), $yaw, $pitch);
                $player->getInventory()->clearAll();
                $last = null;
                $next = null;
                foreach (self::UPGRADE as $upgrade => $cost) {
                    if ($upgrade === $id) {
                        $next = true;
                        continue;
                    }
                    if ($next === true) {
                        $next = $upgrade;
                        break;
                    }
                    $last = $upgrade;
                }
                if ($next === true) {
                    $keys = array_keys(self::UPGRADE);
                    $next = array_shift($keys);
                }
                $a = self::UPGRADE;
                end($a);
                $key = key($a);
                reset($a);
                $idLast = $last === null ? $key : $last;
                $metaLast = 0;
                if ($idLast === 325)
                    $metaLast = 8;
                elseif ($idLast === 351)
                    $metaLast = 1;
                $metaNext = 0;
                if ($next === 325)
                    $metaNext = 8;
                elseif ($next === 351)
                    $metaNext = 1;
                $level =   $this->getItemLevel($player, $id);
                $levelLast =  $this->getItemLevel($player, $idLast);
                $levelNext =  $this->getItemLevel($player, $next);
                if ($level >= count(self::UPGRADE[$id]) + 1) {
                    $up = Item::get(Item::APPLE)
                        ->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Предмет улучшен до последнего уровня!");
                } elseif (($current = $this->api->getMoney($player->getName())) < ($cost = $this->getUpgradeCost($player, $id))) {
                    $up = Item::get(Item::APPLE)
                        ->setCustomName(TextFormat::RESET . TextFormat::DARK_RED . "Улучшение стоит $cost монет, у вас есть только $current");
                } else {
                    $up = Item::get(Item::GOLDEN_APPLE)
                        ->setCustomName(TextFormat::RESET . TextFormat::GOLD . "Улучшить предмет до уровня " . ($level + 1));
                }
                $player->sendMessage(TCapi::colorize($this->translations->getNested("weaponDescriptions." . $this->idToShortName($id, $item->getDamage()), "{RED}Описание не завезли :c")));
                $player->getInventory()->setItem(0, Item::get($idLast, $metaLast, $levelLast)
                    ->setCustomName(TextFormat::RESET . TextFormat::AQUA . "Предыдущий предмет: " . $this->idToName($idLast, $metaLast)));
                $player->getInventory()->setItem(2, Item::get(Item::BOOK)
                    ->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Информация"));
                $player->getInventory()->setItem(3, $item
                    ->setCustomName(TextFormat::RESET . TextFormat::DARK_AQUA . "Текущий предмет: " . $this->idToName($item->getId(), $item->getDamage())));
                $player->getInventory()->setItem(4, $up);
                $player->getInventory()->setItem(6, Item::get($next, $metaNext, $levelNext)
                    ->setCustomName(TextFormat::RESET . TextFormat::AQUA . "Следующий предмет: " . $this->idToName($next, $metaNext)));
                $player->getInventory()->setItem(8, Item::get(Item::SPRUCE_DOOR)
                    ->setCustomName(TextFormat::RESET . TextFormat::RED . "В хаб"));
                $player->getInventory()->setHeldItemIndex(3);
                /*
                if (isset($this->upgrading[$player->getId()][1])) {
                    $i = $this->upgrading[$player->getId()][1];
                }
                if ($item->getCount() == 0) {
                    $this->updateChest($player);
                    unset($this->upgrading[$player->getId()]);
                    $player->sendPopup(TextFormat::DARK_RED . "Этот предмет ещё не открыт.");
                } else if (isset($i) && $i instanceof Item && $i->getId() === $item->getId()) {
                    $player->sendPopup($this->idToName($i->getId(), $i->getDamage()) . TextFormat::DARK_GREEN . " улучшен до уровня " . ($i->getCount() + 1) . "!");
                    $this->api->safeReduceMoney($player->getName(), $this->getUpgradeCost($player, $item->getId()));
                    $this->levels->setNested(strtolower($player->getName()) . "." . $item->getId(), $i->getCount() + 1);
                    $this->levels->save();
                    $this->updateChest($player);
                    unset($this->upgrading[$player->getId()]);
                } else {
                    if (key_exists($item->getId(), self::UPGRADE) && $item->getCount() >= count(self::UPGRADE[$item->getId()])) {
                        unset($this->upgrading[$player->getId()]);
                        $player->sendPopup($this->idToName($item->getId(), $item->getDamage()) . TextFormat::DARK_RED . " уже улучшен до максимального уровня!");
                    } else {
                        if (($money = $this->api->getMoney($player->getName())) < ($need = $this->getUpgradeCost($player, $item->getId()))) {
                            $player->sendPopup(TextFormat::DARK_RED . "Улучшение этого предмета стоит " . TextFormat::GOLD . $need . " монет" . TextFormat::DARK_RED . ", у вас есть только " . TextFormat::GOLD . $money . " монет" . TextFormat::DARK_RED . ".");
                        } else
                            $this->upgrading[$player->getId()] = [$player, $item];
                    }
                }*/
            }
        } elseif ($packet instanceof ContainerClosePacket) {
            if ($packet->windowid === 1 and !isset($this->upgrading[$player->getId()])) {
                unset($this->inUpgradeChest[$event->getPlayer()->getId()]);
                $event->getPlayer()->getInventory()->clearAll();
                unset($this->upgrading[$event->getPlayer()->getId()]);
                $this->updateHotbar($event->getPlayer());
                $event->getPlayer()->getInventory()->sendContents($event->getPlayer());
            }

        }
    }

    function updateChest(Player $player)
    {
        $pk = new ContainerSetContentPacket();
        $pk->slots = [];
        $items = array_keys(self::UPGRADE);
        //$items = [120, 262, 388, 347, 382, 396, 259, 90, 289, 357, 385, 46, 20, 369, 325, 353, 145, 288, 290, 291, 294, 247, 283, 384, 341, 378, 351, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        $playerItems = $this->items->exists(strtolower($player->getName())) ? $this->items->get(strtolower($player->getName())) : [];
        foreach ($items as $item) {
            $meta = 0;
            $itemExists = false;
            if (!in_array($item, self::PAID_ITEMS)) {
                $itemExists = true;
            } else {
                foreach ($playerItems as $pitems) {
                    if (in_array($item, $pitems))
                        $itemExists = true;
                }
            }
            if ($item === 325)
                $meta = 8;
            elseif ($item === 351)
                $meta = 1;
            $pk->slots[] = Item::get($item, $meta,
                $itemExists ? $this->getItemLevel($player, $item) : 0);
        }
        $pk->windowid = 1;
        $player->dataPacket($pk);
    }

    function idToShortName(int $id, int $meta = 0)
    {
        switch ($id) {
            case 283:
                return "goldenSword";
            case 351:
                return "heart";
            case 120:
                return "enderpearl";
            case 262:
                return "arrow";
            case 388:
                return "emerald";
            case 347:
                return "clock";
            case 382:
                return "glisteringMelon";
            case 396:
                return "invisibleCarrot";
            case 90:
                return "randomPortal";
            case 289:
                return "gunpowder";
            case 385:
                return "fireCharge";
            case 46:
                return "tnt";
            case 357:
                return "cookies";
            case 20:
                return "glassBlock";
            case 369:
                return "blazeRod";
            case 325:
                return $meta === 8 ? "waterBucket" : "undefined";
            case 353:
                return "sugar";
            case 145:
                return "anvil";
            case 341:
                return "slimepearl";
            case 378:
                return "magmapearl";
            case 384:
                return "xpBottle";
            case 294:
                return "goldenHoe";
            case 288:
                return "feather";
            case 290:
                return "woodenHoe";
            case 291:
                return "stoneHoe";
            case 259:
                return "flamethrower";
            default:
                return "undefined";
        }
    }

    function processItem(Player $player, Item $item)
    {
        $id = $item->getId();
        $meta = $item->getDamage();
        $name = $this->idToShortName($id, $meta);
        if ($this->isOnCooldown($player, $name))
            return false;
        $level =   $this->getItemLevel($player, $id);
        $motion = TCapi::getThrowMotion($player);
        switch ($id) {
            case 120: // Enderpearl
                $time = 20 * 10;

                $pearl = new Enderpearl(
                    $player->chunk,
                    TCapi::nbt(
                        $player->x,
                        $player->y + $player->getEyeHeight(),
                        $player->z,
                        $motion[0],
                        $motion[1],
                        $motion[2],
                        $player->yaw,
                        $player->pitch
                    ),
                    $player,
                    8 - ($level * 2)
                );
                $pearl->setMotion($pearl->getMotion()->multiply(1.5));
                $pearl->spawnToAll();
                break;
            case 262: // Arrow
                $time = 20 * 2;
                $arrow = new MyArrow(
                    $player->chunk,
                    TCapi::nbt(
                        $player->x,
                        $player->y + $player->getEyeHeight(),
                        $player->z,
                        $motion[0],
                        $motion[1],
                        $motion[2],
                        $player->yaw,
                        $player->pitch
                    ),
                    $player,
                    $level >= 4 ? true : false
                );
                $arrow->addDamage($level - 1);
                if ($level >= 5) {
                    $arrow->setPotionId(34);
                    $time = 20 * 4;
                }
                if ($level >= 6) {
                    $arrow->setExplosive();
                    $time = 20 * 5;
                }
                $arrow->setMotion($arrow->getMotion()->multiply(1.5));
                $arrow->spawnToAll();
                break;
            case 388: // Emerald
                $time = 20 * 20;
                $villager = new AIVillager($player->chunk, $player, $level * 2);
                $villager->spawnToAll();
                $villager->setMotion($villager->getMotion()->multiply(1.5));
                break;
            case 347: // Clock
                // todo bug
                $time = 20 * 19;
                var_dump($this->teleporting);
                $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player) {
                    if ($player instanceof Player && isset($this->teleporting[$player->getName()])) {
                        $this->teleporting[$player->getName()] = true;
                        if ($this->lastMove[$player->getName()] != null) {
                            end($this->lastMove[$player->getName()]);
                            $key = key($this->lastMove[$player->getName()]);
                            $player->teleport($this->lastMove[$player->getName()][$key][1]);
                            unset($this->lastMove[$player->getName()][$key]);
                            reset($this->lastMove[$player->getName()]);
                        }
                    }
                    if ($tick >= (9 * 20) / 3 || empty($this->lastMove[$player->getName()])) {
                        $task->cancel();
                        $player->getLevel()->addSound(new DoorCrashSound($player));
                        $this->teleporting[$player->getName()] = false;
                    }
                }, $player, $time, $id, $name), 1);
                break;
            case 382: // Glistering melon
                $time = 20 * 20;
                $player->addEffect(Effect::getEffect(Effect::REGENERATION)->setDuration($level * 20 + 2 * 20)->setAmplifier(2)->setVisible(false));
                break;
            case 396: // Invisible carrot
                $time = 20 * 35;
                $player->addEffect(Effect::getEffect(Effect::INVISIBILITY)->setDuration($level * 20 + 2 * 20)->setVisible(false));
                break;
            case 90: // Random portal
                $time = 20 * 18;
                $v = $player->add(mt_rand(-10, 10), mt_rand(5, 10), mt_rand(-10, 10));
                if ($level === 2) {
                    while (!$player->getLevel()->getBlock($v)->isTransparent()) {
                        $v = $v->add(0, 1, 0);
                    }
                } elseif ($level === 3) {
                    $attemps = 0;
                    while ($player->getLevel()->getHighestBlockAt($v->x, $v->z) === 0) {
                        $v = $player->add(mt_rand(-10, 10), mt_rand(5, 10), mt_rand(-10, 10));
                        $attemps++;
                        if ($attemps >= 10) {
                            $v = $player;
                            break;
                        }
                    }
                    while (!$player->getLevel()->getBlock($v)->isTransparent()) {
                        $v = $v->add(0, 1, 0);
                    }
                }
                $player->heal(6, new EntityRegainHealthEvent($player, 6, EntityRegainHealthEvent::CAUSE_MAGIC));
                $player->teleport($v);
                break;
            case 289: // Gunpowder
                $time = 20 * 30;
                $this->fire[$player->getName()] = [];
                $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player, int $level) {
                    $player->getLevel()->addParticle(new DustParticle($player->add(mt_rand(-1000, 1000) / 1000, 0.4, mt_rand(-1000, 1000) / 1000), 128, 128, 128));
                    if (isset($this->fire[$player->getName()]) && !in_array($player->round(), $this->fire[$player->getName()], true) && $player->getLevel()->getBlock($player->round())->getId() === 0)
                        $this->fire[$player->getName()][] = $player->round();
                    if ($tick >= $level * 20 + 4 * 20)
                        $task->cancel();
                }, $player, $level), 1);
                $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, $player, Level $level) {
                    foreach ($this->fire[$player] as $pos) {
                        $level->setBlock($pos, new Fire);
                    }
                    unset($tick);
                    unset($task);
                }, $player->getName(), $player->getLevel()), $level * 20 + 4 * 20);
                $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, $player, Level $level) {
                    foreach ($this->fire[$player] as $pos) {
                        $level->setBlock($pos, new Air);
                    }
                    unset($this->fire[$player]);
                    unset($tick);
                    unset($task);
                }, $player->getName(), $player->getLevel()), 20 * 16);
                break;
            case 385: // Fire charge
                $time = 20 * 5;
                $fireball = new Fireball(
                    $player->chunk,
                    TCapi::nbt(
                        $player->x + $player->getDirectionVector()->x,
                        $player->y + $player->getEyeHeight() + $player->getDirectionVector()->y,
                        $player->z + $player->getDirectionVector()->z,
                        $motion[0],
                        $motion[1],
                        $motion[2],
                        $player->yaw,
                        $player->pitch
                    ),
                    $player,
                    $level <= 2 ? false : $level - 2,
                    $level > 1 ? true : false
                );
                $fireball->setMotion($fireball->getMotion()->multiply(2.3));
                $fireball->spawnToAll();
                break;
            case 46: // TNT
                $time = 20 * 6;
                $level =   $this->getItemLevel($player, $id);
                $fireball = new FakePrimedTNT(
                    $player->chunk,
                    TCapi::nbt(
                        $player->x + $player->getDirectionVector()->x,
                        $player->y + $player->getEyeHeight() + $player->getDirectionVector()->y,
                        $player->z + $player->getDirectionVector()->z,
                        $motion[0],
                        $motion[1],
                        $motion[2],
                        $player->yaw,
                        $player->pitch,
                        new IntTag("Fuse", mt_rand(2, 4) * 20)
                    ),
                    $level >= 3
                );
                $fireball->setMotion($fireball->getMotion()->multiply(1.3));
                $fireball->spawnToAll();
                break;
            case 357: // Cookies!
                $time = 20 * 15;
                $cookie = new Item(357);
                $cookie->addEnchantment(Enchantment::getEnchantment(Enchantment::TYPE_INVALID));
                $itemTag = NBT::putItemHelper($cookie);
                $itemTag->setName("Item");
                for ($i = 0; $i < mt_rand($level * 3, $level * 8); $i++) {
                    $x = $player->x + (mt_rand(-1000, 1000) * 3);
                    $z = $player->z + (mt_rand(-1000, 1000) * 3);
                    $y = $player->y + mt_rand(1, 4);
                    $fireball = new \pocketmine\entity\Item(
                        $player->chunk,
                        TCapi::nbt(
                            $x,
                            $y,
                            $z,
                            0,
                            0,
                            0,
                            lcg_value() * 360,
                            0,
                            new ShortTag("Health", 5),
                            new ShortTag("PickupDelay", 0),
                            $itemTag)
                    );
                    $fireball->spawnToAll();
                }
                break;
            case 20: // Glass block
                $time = 20 * 30;
                $floor = $player->round();
                $world = $player->getLevel();
                $blocks = [$floor->subtract(0, 1), $floor->add(0, 2, 0), $floor->add(0, 0, 1), $floor->add(0, 0, -1), $floor->add(1, 0, 0), $floor->add(-1, 0, 0), $floor->add(0, 1, 1), $floor->add(0, 1, -1), $floor->add(1, 1, 0), $floor->add(-1, 1, 0)];
                foreach ($blocks as $block)
                    if ($world->getBlockIdAt($block->x, $block->y, $block->z) === 0)
                        $world->setBlock($block, new Glass);
                $player->addEffect(Effect::getEffect(Effect::REGENERATION)->setDuration($level * 40 + 8 * 20)->setAmplifier(1)->setVisible(false));
                $player->teleport($floor->add(0.5, 0, 0.5));
                $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, array $blocks, Level $level) {
                    foreach ($blocks as $block)
                        if ($level->getBlockIdAt($block->x, $block->y, $block->z) === 20)
                            $level->setBlock($block, new Air);
                    unset($task);
                    unset($tick);
                }, $blocks, $world), 20 * 10);
                break;
            case 369: // Blaze rod
                $time = 20 * 6;
                if ($level === 1) {
                    for ($z = 0; $z < 600; $z++) {
                        $nearEntity = null;
                        $movingObjectPosition = null;
                        $components = $player->add($player->getDirectionVector()->getX() * ($z / 10), $player->getDirectionVector()->getY() * ($z / 10) + $player->getEyeHeight() + sin($z / 10), $player->getDirectionVector()->getZ() * ($z / 10));
                        $particle = new FlameParticle($components);
                        $player->getLevel()->addParticle($particle);
                        $boundingBox = new AxisAlignedBB($components->x - 1, $components->y - 1, $components->z - 1, $components->x + 1, $components->y + 1, $components->z + 1);
                        $list = $player->getLevel()->getCollidingEntities($boundingBox);
                        foreach ($list as $entity) {
                            if ($entity == $player)
                                continue;
                            $damage = mt_rand(2, 4);
                            $entity->attack($damage, new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $damage));
                            if ($entity instanceof Entity)
                                $entity->setOnFire(mt_rand(2, 4));
                        }
                    }
                } else if ($level === 2) {
                    $time = 20 * 8;
                    $nearEntity = null;
                    $movingObjectPosition = null;
                    $grow = 0.1;
                    $length = 15;
                    $radials = M_PI / 30;
                    $radius = 1.5;
                    $particlesHelix = 200;
                    $a = 0;
                    for ($j = 0; $j < $particlesHelix; $j++) {
                        if ($a * $grow > $length) {
                            $a = 0;
                        }
                        for ($i = 0; $i < 1; $i++) {
                            $angle = $a * $radials + M_PI * $i;
                            $v = new Vector3(cos($angle) * $radius, $a * $grow, sin($angle) * $radius);
                            $player->getLevel()->addParticle($components = new FlameParticle($this->rotate($player->add(0, $player->getEyeHeight()), $v)));
                            $boundingBox = new AxisAlignedBB($components->x - 1, $components->y - 1, $components->z - 1, $components->x + 1, $components->y + 1, $components->z + 1);
                            $list = $player->getLevel()->getCollidingEntities($boundingBox);
                            foreach ($list as $entity) {
                                if ($entity == $player)
                                    continue;
                                $damage = mt_rand(4, 7);
                                $event = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $damage);
                                $entity->attack($damage, $event);
                                if ($entity instanceof Entity)
                                    $entity->setOnFire(mt_rand(3, 4));
                            }
                        }
                        $a++;
                    }
                } elseif ($level === 3) {
                    $nearEntity = null;
                    $movingObjectPosition = null;
                    $time = 20 * 10;
                    $grow = 0.2;
                    $length = 15;
                    $particlesBase = 15;
                    $radials = M_PI / 30;
                    $radius = 1.5;
                    $particlesHelix = 200;
                    $a = 0;
                    $last = null;
                    for ($j = 0; $j < $particlesHelix; $j++) {
                        if ($a * $grow > $length) {
                            $a = 0;
                        }
                        for ($i = 0; $i < 2; $i++) {
                            $angle = $a * $radials + M_PI * $i;
                            $v = new Vector3(cos($angle) * $radius, $a * $grow, sin($angle) * $radius);
                            $player->getLevel()->addParticle($components = new FlameParticle($rotated = $this->rotate($player->add(0, $player->getEyeHeight()), $v)));
                            $motion = ($last === null ? new Vector3(0, 0, 0) : $rotated->subtract($last));
                            $boundingBox = new AxisAlignedBB($components->x - 1, $components->y - 1, $components->z - 1, $components->x + 1, $components->y + 1, $components->z + 1);
                            $list = $player->getLevel()->getCollidingEntities($boundingBox);
                            foreach ($list as $entity) {
                                if ($entity == $player)
                                    continue;
                                $damage = mt_rand(6, 10);
                                $event = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $damage);
                                $entity->attack($damage, $event);
                                $entity->setMotion($motion);
                                if ($entity instanceof Entity)
                                    $entity->setOnFire(mt_rand(3, 7));
                            }
                        }
                        $angle = $a * $radials;
                        $v = (new Vector3(cos($angle), 0, sin($angle)))->multiply($radius * 1 / $particlesBase);
                        $v->y = $a * $grow;
                        $this->getServer()->getDefaultLevel()->addParticle(new WhiteSmokeParticle($this->rotate($player->add(0, $radius), $v)));
                        $a++;
                        if (isset($rotated)) {
                            $last = $rotated;
                        }
                    }
                }
                break;
            case 325: // Water bucket
                $time = 20 * 6;
                if ($level === 1) {
                    for ($z = 0; $z < 600; $z++) {
                        $nearEntity = null;
                        $movingObjectPosition = null;
                        $components = $player->add($player->getDirectionVector()->getX() * ($z / 10), $player->getDirectionVector()->getY() * ($z / 10) + $player->getEyeHeight() + sin($z / 10), $player->getDirectionVector()->getZ() * ($z / 10));
                        $particle = new WaterParticle($components);
                        $player->getLevel()->addParticle($particle);
                        $boundingBox = new AxisAlignedBB($components->x - 1, $components->y - 1, $components->z - 1, $components->x + 1, $components->y + 1, $components->z + 1);
                        $list = $player->getLevel()->getCollidingEntities($boundingBox);
                        foreach ($list as $entity) {
                            if ($entity == $player)
                                continue;
                            $damage = mt_rand(3, $level * 2 + 4);
                            $entity->attack($damage, new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $damage));
                            $entity->extinguish();
                        }
                    }
                } elseif ($level === 2) {
                    $nearEntity = null;
                    $movingObjectPosition = null;
                    $time = 20 * 10;
                    $grow = 0.2;
                    $length = 15;
                    $particlesBase = 15;
                    $radials = M_PI / 30;
                    $radius = 1.5;
                    $particlesHelix = 200;
                    $a = 0;
                    for ($j = 0; $j < $particlesHelix; $j++) {
                        if ($a * $grow > $length) {
                            $a = 0;
                        }
                        for ($i = 0; $i < 4; $i++) {
                            $angle = $a * $radials + M_PI * $i;
                            $v = new Vector3($i > 1 ? sin($angle) : cos($angle) * $radius, $a * $grow, $i > 1 ? cos($angle) : sin($angle) * $radius);
                            $player->getLevel()->addParticle($components = new WaterParticle($rotated = $this->rotate($player->add(0, $player->getEyeHeight()), $v)));
                            $boundingBox = new AxisAlignedBB($components->x - 1, $components->y - 1, $components->z - 1, $components->x + 1, $components->y + 1, $components->z + 1);
                            $list = $player->getLevel()->getCollidingEntities($boundingBox);
                            foreach ($list as $entity) {
                                if ($entity == $player)
                                    continue;
                                $damage = mt_rand(6, 10);
                                $event = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $damage);
                                $entity->attack($damage, $event);
                                if ($entity instanceof Entity)
                                    $entity->extinguish();
                            }
                        }
                        $angle = $a * $radials;
                        $v = (new Vector3(cos($angle), 0, sin($angle)))->multiply($radius * 1 / $particlesBase);
                        $v->y = $a * $grow;
                        $a++;
                    }
                } elseif ($level === 3) {
                    $nearEntity = null;
                    $movingObjectPosition = null;
                    $time = 20 * 10;
                    $grow = 0.2;
                    $length = 15;
                    $particlesBase = 15;
                    $radials = M_PI / 30;
                    $radius = 1.5;
                    $particlesHelix = 200;
                    $a = 0;
                    for ($j = 0; $j < $particlesHelix; $j++) {
                        if ($a * $grow > $length) {
                            $a = 0;
                        }
                        for ($i = 0; $i < 4; $i++) {
                            $angle = $a * $radials + M_PI * $i;
                            $v = new Vector3($i > 1 ? sin($angle) : cos($angle) * $radius, $a * $grow, $i > 1 ? cos($angle) : sin($angle) * $radius);
                            $player->getLevel()->addParticle($components = new WaterParticle($rotated = $this->rotate($player->add(0, $player->getEyeHeight()), $v)));
                            $boundingBox = new AxisAlignedBB($components->x - 1, $components->y - 1, $components->z - 1, $components->x + 1, $components->y + 1, $components->z + 1);
                            $list = $player->getLevel()->getCollidingEntities($boundingBox);
                            foreach ($list as $entity) {
                                if ($entity == $player)
                                    continue;
                                $damage = mt_rand(6, 10);
                                $event = new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_MAGIC, $damage);
                                $entity->attack($damage, $event);
                                if ($entity instanceof Entity)
                                    $entity->extinguish();
                            }
                        }
                        $angle = $a * $radials;
                        $v = (new Vector3(cos($angle), 0, sin($angle)))->multiply($radius * 1 / $particlesBase);
                        $v->y = $a * $grow;
                        $this->getServer()->getDefaultLevel()->addParticle(new BubbleParticle($this->rotate($player->add(0, $radius), $v)));
                        $a++;
                    }
                }
                break;
            case 353: // Sugar
                /*
                    Level 1: a2 d4 r16
                    Level 2: a3 d6 r15
                    Level 3: a4 d8 r14
                */
                $time = 20 * 16 - ($level - 1) * 20;
                $effect = Effect::getEffect(Effect::SPEED)->setAmplifier($level + 1)->setDuration(20 * 2 + $level * 40);
                $effect->setColor(mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                $player->addEffect($effect);
                break;
            case 145: // Anvil
                $time = 20 * 5;
                for ($i = 0; $i < $level; $i++) {
                    $anvil = new FallingAnvil(
                        $player->chunk,
                        TCapi::nbt(
                            $player->x + $player->getDirectionVector()->x,
                            $player->y + $player->getEyeHeight() + $player->getDirectionVector()->y + $i / 10,
                            $player->z + $player->getDirectionVector()->z,
                            $motion[0],
                            $motion[1],
                            $motion[2],
                            $player->yaw,
                            $player->pitch,
                            new IntTag("TileID", Item::ANVIL)
                        )
                    );
                    $anvil->setMotion($anvil->getMotion()->multiply(1.5));
                    $anvil->spawnToAll();
                }
                break;
            case 341: // Slimepearl
                $time = 20 * 15;
                $nbt = TCapi::nbt(
                    $player->x + $player->getDirectionVector()->x,
                    $player->y + $player->getEyeHeight() + $player->getDirectionVector()->y,
                    $player->z + $player->getDirectionVector()->z,
                    $motion[0],
                    $motion[1],
                    $motion[2],
                    $player->yaw,
                    $player->pitch
                );
                $slimepearl = new Slimepearl($player->chunk, $nbt, $player);
                $slimepearl->setMotion($slimepearl->getMotion()->multiply(1.5));
                $slimepearl->spawnToAll();
                $fakecart = new Fakecart($player->chunk, $nbt, $player, $slimepearl);
                $fakecart->setMotion($fakecart->getMotion()->multiply(1.5));
                $fakecart->spawnToAll();
                break;
            case 378: // Magmapearl
                $time = 20 * 15;
                $nbt = TCapi::nbt(
                    $player->x + $player->getDirectionVector()->x,
                    $player->y + $player->getEyeHeight() + $player->getDirectionVector()->y,
                    $player->z + $player->getDirectionVector()->z,
                    $motion[0],
                    $motion[1],
                    $motion[2],
                    $player->yaw,
                    $player->pitch
                );
                $slimepearl = new Slimepearl($player->chunk, $nbt, $player, 1);
                $slimepearl->setMotion($slimepearl->getMotion()->multiply(1.5));
                $slimepearl->spawnToAll();
                $fakecart = new Fakecart($player->chunk, $nbt, $player, $slimepearl);
                $fakecart->setMotion($fakecart->getMotion()->multiply(1.5));
                $fakecart->spawnToAll();
                break;
            case 384: // XP Bottle
                $time = 20 * 8;
                for ($i = 0; $i < $level; $i++) {
                    $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function (TickTask $task, $tick, Player $player, array $motion) {
                        $grenade = new XpGrenade($player->chunk, TCapi::nbt(
                            $player->x + $player->getDirectionVector()->x,
                            $player->y + $player->getEyeHeight() + $player->getDirectionVector()->y,
                            $player->z + $player->getDirectionVector()->z,
                            $motion[0],
                            $motion[1],
                            $motion[2],
                            $player->yaw,
                            $player->pitch
                        ), $player);
                        $grenade->setMotion($grenade->getMotion()->multiply(1.5));
                        $grenade->spawnToAll();
                        unset($task, $tick);
                    }, $player, $motion), $i * 8);
                }
                break;
            case 288: // Feather
                $time = 20 * 10;
                $player->setMotion($player->getDirectionVector()->multiply($level * 1.337));
                break;
            case 290: // Wooden hoe
                // todo bug
                $time = 20 * 30;
                $player->getLevel()->addSound(new BlazeShootSound($player));
                for ($i = 10; $i < 305; $i++) {
                    $direction = $player->getDirectionVector()->multiply($i / 10);
                    $v3 = $player->add(
                        $direction->x,
                        $direction->y + $player->getEyeHeight(),
                        $direction->z
                    );
                    if (!$player->getLevel()->getBlock($v3)->isTransparent()) {
                        $radius = 3;
                        $count = 500;
                        $particle = new ExplodeParticle($v3);
                        $explosion = new Explosion($v3, $level * 1.5);
                        $explosion->explodeB();
                        for ($i = 0; $i < $count; $i++) {
                            $pitch = (mt_rand() / mt_getrandmax() - 0.5) * M_PI;
                            $yaw = mt_rand() / mt_getrandmax() * 2 * M_PI;
                            $y = -sin($pitch);
                            $delta = cos($pitch);
                            $x = -sin($yaw) * $delta;
                            $z = cos($yaw) * $delta;
                            $v = new Vector3($x, $y, $z);
                            $p = $v3->add(new Location($v->normalize()->multiply($radius)->x, $v->normalize()->multiply($radius)->y, $v->normalize()->multiply($radius)->z));
                            $particle->setComponents($p->x, $p->y, $p->z);
                            $player->getLevel()->addParticle($particle);
                        }
                        break;
                    }
                    $player->getLevel()->addParticle(new FlameParticle($v3));
                    foreach ($player->getLevel()->getEntities() as $p) {
                        if (($p->round()->distance($v3) <= 1.4 || $p->round()->add(0, 1, 0)->distance($v3) <= 1.4) && $p->isAlive() && $player != $p) {
                            $radius = 3;
                            $count = 500;
                            $particle = new ExplodeParticle($v3);
                            $player->getLevel()->addSound(new ExplodeSound($v3));
                            $explosion = new Explosion($v3, $level * 1.5);
                            $explosion->explodeB();
                            for ($i = 0; $i < $count; $i++) {
                                $pitch = (mt_rand() / mt_getrandmax() - 0.5) * M_PI;
                                $yaw = mt_rand() / mt_getrandmax() * 2 * M_PI;
                                $y = -sin($pitch);
                                $delta = cos($pitch);
                                $x = -sin($yaw) * $delta;
                                $z = cos($yaw) * $delta;
                                $v = new Vector3($x, $y, $z);
                                $p = $v3->add(new Location($v->normalize()->multiply($radius)->x, $v->normalize()->multiply($radius)->y, $v->normalize()->multiply($radius)->z));
                                $particle->setComponents($p->x, $p->y, $p->z);
                                $player->getLevel()->addParticle($particle);
                            }
                            break;
                        }
                    }
                }
                break;
            case 291: // Stone hoe
                $time = 20 * 13;
                $player->getLevel()->addSound(new ExplodeSound($player));
                for ($i = 10; $i < 805; $i++) {
                    $direction = $player->getDirectionVector()->multiply($i / 10);
                    $v3 = $player->add(
                        $direction->x,
                        $direction->y + $player->getEyeHeight(),
                        $direction->z
                    );
                    if (!$player->getLevel()->getBlock($v3)->isTransparent()) {
                        break;
                    }
                    $player->getLevel()->addParticle(new CriticalParticle($v3));
                    foreach ($player->getLevel()->getEntities() as $p) {
                        if (($p->round()->distance($v3) <= 1.4 || $p->round()->add(0, 1, 0)->distance($v3) <= 1.4) && $p->isAlive() && $player != $p) {
                            $p->attack(($damage = 10 + $level * 2), new EntityDamageEvent($p, EntityDamageEvent::CAUSE_PROJECTILE, $damage));
                            $player->getLevel()->addSound(new AnvilFallSound($p));
                        }
                    }
                }
                break;
            case 259: // Flamethrower
                $time = 20 * 14;
                $player->getLevel()->addSound(new TNTPrimeSound($player));
                $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player, $level) {
                    $t = $tick * 5;
                    for ($i = 0; $i < $t; $i++) {
                        $direction = $player->getDirectionVector()->multiply($i / 10);
                        $v3 = $player->add(
                            $direction->x,
                            $direction->y + $player->getEyeHeight(),
                            $direction->z
                        );
                        if (!($tick % 4)) {
                            $player->getLevel()->addParticle(new EntityFlameParticle($v3));
                        }
                        foreach ($player->getLevel()->getEntities() as $p) {
                            if (($p->round()->distance($v3) <= 1.4 || $p->round()->add(0, 1, 0)->distance($v3) <= 1.4) && $p->isAlive() && $player != $p) {
                                $p->attack(4, new EntityDamageEvent($p, EntityDamageEvent::CAUSE_PROJECTILE, 4));
                                $p->setOnFire($level * 2);
                            }
                        }
                    }
                    if (!($tick % 4))
                        if (isset($v3)) {
                            $player->getLevel()->addSound(new BlazeShootSound($v3));
                        }
                    if ($tick >= 60)
                        $task->cancel();
                }, $player, $level), 20 * (5 - $level), 1);
                break;
            case 247: // Momental bomb
                $time = 20 * 60;
                $player->getLevel()->addSound(new BlazeShootSound($player));
                (new Explosion($player, 5))->explodeB();
                break;
            case 294: // Golden hoe
                // todo bug
                $time = 20 * 5;
                for ($i = 0; $i < $level; $i++)
                    $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function (TickTask $task, $tick, Player $player, int $level) {
                        $player->getLevel()->addSound(new ClickSound($player));
                        for ($i = 10; $i < 400; $i++) {
                            $direction = $player->getDirectionVector()->multiply($i / 10);
                            $v3 = $player->add(
                                $direction->x,
                                $direction->y + $player->getEyeHeight(),
                                $direction->z
                            );
                            if (!$player->getLevel()->getBlock($v3)->isTransparent())
                                break;
                            $player->getLevel()->addParticle(new RedstoneParticle($v3, 0));
                            foreach ($player->getLevel()->getEntities() as $p) {
                                if (($p->round()->distance($v3) <= 1.4 || $p->round()->add(0, 1, 0)->distance($v3) <= 1.4) && $p->isAlive() && $player != $p) {
                                    $p->attack(6 + $level, new EntityDamageEvent($p, EntityDamageEvent::CAUSE_PROJECTILE, 6 + $level));
                                    $player->getLevel()->addSound(new AnvilFallSound($p));
                                }
                            }
                        }
                        unset($task, $tick);
                    }, $player, $level), $i * 4);
                break;
            default:
                return false;
        }
        $this->addReloadTask($player, $time, $id, $name);
        return false;
    }


    /**
     * @param Player $player
     * @param Position $pos
     */
    function cellItem(Player $player, Position $pos)
    {
        $chances = new ChancesAPI(self::CHANCES);
        $id = $chances->next();
        $message = $this->idToName($id, $id === Item::BUCKET ? 8 : 0);
        foreach ($this->getServer()->getDefaultLevel()->getPlayers() as $p)
            $p->sendMessage(TextFormat::GOLD . "Игрок " . $player->getName() . " выиграл " . mb_strtolower($message) . TextFormat::GOLD . " в кейсе!");
        if (in_array($id, self::PAID_ITEMS)) { // check if not money
            $array = $this->items->get(strtolower($player->getName()));
            if (in_array($id, $array[self::PAID_ITEMS_SLOTS[$id]])) {
                $level = $this->getItemLevel($player, $id);
                if (key_exists($id, self::UPGRADE) and count(self::UPGRADE[$id]) > $level) {
                    $player->sendMessage(TextFormat::DARK_AQUA . "У вас уже есть этот предмет, поэтому он был улучшен до уровня " . ($level + 1));
                    $this->levels->setNested(strtolower($player->getName()) . "." . $id, $level + 1);
                } else {
                    $player->sendMessage(TextFormat::DARK_AQUA . "У вас уже есть этот предмет и он улучшен до максимального уровня, поэтому мы дадим вам ещё один ключ!");
                    $this->keys->set(strtolower($player->getName()), $this->keys->get(strtolower($player->getName())) + 1);
                    $this->keys->save();
                }
            } else {
                $array[self::PAID_ITEMS_SLOTS[$id]][] = $id;
                $this->items->set(strtolower($player->getName()), $array);
                $this->items->save();
            }
        }
        switch ($id) {
            case 369:
                $this->i = 1;
                break;
            case 283:
                $player->getLevel()->spawnLightning($pos);
                break;
            case 247:
                $player->getLevel()->spawnLightning($pos);
                break;
            case 325:
                $this->water = 1;
                break;
            case 290:
                $explosion = new Explosion($pos, 3);
                $explosion->explodeB();
                $particle = new DustParticle($pos, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                for ($i = 0; $i < 600; $i++) {
                    $pitch = (mt_rand() / mt_getrandmax() - 0.5) * M_PI;
                    $yaw = mt_rand() / mt_getrandmax() * 2 * M_PI;
                    $y = -sin($pitch);
                    $delta = cos($pitch);
                    $x = -sin($yaw) * $delta;
                    $z = cos($yaw) * $delta;
                    $v = new Vector3($x, $y, $z);
                    $p = $pos->add($v->normalize()->multiply(3));
                    $particle->setComponents($p->x, $p->y, $p->z);
                    $player->getLevel()->addParticle($particle);
                }
                break;
            case 384:
                $player->getLevel()->spawnLightning($pos);

                break;
            case 259:
                $player->getLevel()->spawnLightning($pos);

                break;
            case 357:
                $player->getLevel()->spawnLightning($pos);
                $radius = 2;
                $count = 150;
                for ($i = 0; $i < $count; $i++) {
                    $pitch = (mt_rand() / mt_getrandmax() - 0.5) * M_PI;
                    $yaw = mt_rand() / mt_getrandmax() * 2 * M_PI;
                    $y = -sin($pitch);
                    $delta = cos($pitch);
                    $x = -sin($yaw) * $delta;
                    $z = cos($yaw) * $delta;
                    $v = new Vector3($x, $y, $z);
                    $p = $pos->add($v->normalize()->multiply($radius))->add(0.5, 4.5, 0.5);
                    $v = new Vector3($p->x, $p->y, $p->z);
                    $cookie = new Item(357);
                    $cookie->addEnchantment(Enchantment::getEnchantment(Enchantment::TYPE_INVALID));
                    $itemTag = NBT::putItemHelper($cookie);
                    $itemTag->setName("Item");
                    $nbt = TCapi::nbt($v->x, $v->y, $v->z, 0, 0, 0, lcg_value() * 360, 0, new ShortTag("Health", 5), new ShortTag("PickupDelay", 0), $itemTag);
                    $item = new \pocketmine\entity\Item($player->getLevel()->getChunk($x >> 4, $z >> 4), $nbt);
                    $item->spawnToAll();
                    $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($t, $t2, \pocketmine\entity\Item $item) {
                        if ($item->closed)
                            return;
                        $item->getLevel()->addSound(new DoorBumpSound($item));
                        for ($i = 0; $i < 16; $i++)
                            $item->getLevel()->addParticle(new ItemBreakParticle($item, Item::get(Item::COOKIE)));
                        $item->close();
                        unset($t, $t2);
                    }, $item), 20 * 6);
                }
                break;
            case 378:
            case 341:
                $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function (TickTask $task, $tick, $id, Position $pos) {
                    $particles = 20;
                    $inc = (2 * M_PI) / $particles;
                    $angle = $tick * $inc;
                    for ($i = 0; $i < 16; $i++)
                        $pos->getLevel()->addParticle(new ItemBreakParticle($pos->add(cos($angle) * 2 + 0.5, 0, sin($angle) * 2 + 0.5), Item::get($id)));
                    if ($tick >= (20 * 6))
                        $task->cancel();
                }, $id, $pos), 1);
                break;
            case 175: // money 1
                $count = mt_rand(1, 1);
                $eid = [];
                foreach ($player->getLevel()->getPlayers() as $pl) {
                    $eid[] = [$pl, $this->dropItem($pos->x + 0.5, $pos->y, $pos->z + 0.5, Item::get($id), $message, $pl, 1)];
                }
                $this->coins($player, $count, $eid, $pos, [], [], 20 * 6);
                $doNotDrop = true;
                break;
            case 41: // money 3
                $doNotDrop = true;
                $count = mt_rand(4, 9);
                $eid = [];
                foreach ($player->getLevel()->getPlayers() as $pl) {
                    $eid[] = [$pl, $this->dropItem($pos->x + 0.5, $pos->y, $pos->z + 0.5, Item::get($id), $message, $pl, 1)];
                }
                $this->coinBlocks($player, $count, $eid, $pos);
                break;
            case 266: // money 2
                $doNotDrop = true;
                $count = mt_rand(2, 4);
                $eid = [];
                foreach ($player->getLevel()->getPlayers() as $pl) {
                    $eid[] = [$pl, $this->dropItem($pos->x + 0.5, $pos->y, $pos->z + 0.5, Item::get($id), $message, $pl, 1)];
                }
                $this->coinIngots($player, $count, $eid, $pos, [], [], 60);
                break;
            default:
                break;
        }
        $this->keys->set(strtolower($player->getName()), $this->keys->get(strtolower($player->getName())) - 1);
        $this->keys->save();
        $this->updateHotbar($player);
        unset($this->case[$player->getName()]);
        $eid = [];
        foreach ($player->getLevel()->getPlayers() as $p) {
            if (!isset($doNotDrop))
                $eid[] = [$p, $this->dropItem($pos->x + 0.5, $pos->y, $pos->z + 0.5, Item::get($id), $message, $p, 1)];
            else
                $eid[] = [$p];
            $pk = new RemoveEntityPacket;
            $pk->eid = $this->caseEid[$p->getName()];
            $p->dataPacket($pk);
            unset($this->caseEid[$p->getName()]);
        }
        $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function ($task, $tick, Vector3 $block, array $eid, Level $level, $doNotDrop) {
            $frame = $this->config->get("case");
            $level->setBlock($block, new Block(Block::MONSTER_SPAWNER));
            if (!$doNotDrop) {
                foreach ($eid as $id) {
                    foreach ($id[1] as $item) {
                        if ($id[0] instanceof Player) {
                            $pk = new RemoveEntityPacket;
                            $pk->eid = $item;
                            $id[0]->dataPacket($pk);
                        }
                    }
                }
            }
            foreach ($eid as $id) {
                if ($id[0] instanceof Player)
                    $this->caseEid[$id[0]->getName()] = $this->dropItem($frame[0], $frame[1] + 0.5, $frame[2], null, TextFormat::GOLD . "Открытие кейса", $id[0], 0, true);
            }

            unset($tick);
            unset($task);
        }, $pos, isset($eid) ? $eid : [], $player->getLevel(), !isset($doNotDrop) ? false : true), 20 * 6);
    }

    function coinBlocks(Player $player, int $count, $eid, $pos)
    {
        $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function (TickTask $task, $tick, Player $player, int $count, $eid, Vector3 $pos) {
            foreach ($eid as $ar) {
                if ($ar[0] instanceof Player and $ar[0]->isOnline()) {
                    for ($i = 0; $i < 2; $i++) {
                        $pk = new RemoveEntityPacket;
                        $pk->eid = $ar[1][$i];
                        $ar[0]->dataPacket($pk);
                    }
                    for ($i = 0; $i < 32; $i++)
                        $player->getLevel()->addParticle(new ItemBreakParticle($pos->add(0.5, 0.2, 0.5), Item::get(Item::GOLD_INGOT)));
                    $pk = new ExplodePacket;
                    $pk->x = $pos->x + 0.5;
                    $pk->y = $pos->y + 0.1;
                    $pk->z = $pos->z + 0.5;
                    $pk->radius = 0.1;
                    $ar[0]->dataPacket($pk);
                }
            }
            $eid = [];
            $x = $z = [];
            foreach ($player->getLevel()->getPlayers() as $pl) {
                for ($i = 0; $i < $count; $i++) {
                    $eid[] = [$pl, $this->dropItem(($pos->x) + 0.5 + ($x[] = (mt_rand(-1000, 1000) / 1000)), $pos->y, $pos->z + 0.5 + ($z[] = (mt_rand(-1000, 1000) / 1000)), Item::get(Item::GOLD_INGOT), $this->idToName(Item::GOLD_INGOT), $pl, 1)];
                }
            }
            $this->coinIngots($player, mt_rand(2 * $count, 4 * $count), $eid, $pos, $x, $z);
            unset($tick, $task);
        }, $player, $count, $eid, $pos), 40);
    }

    function coinIngots(Player $player, int $count, $eid, $pos, array $cx = [], array $cz = [], $time = null)
    {
        $this->api->addMoney($player->getName(), $count);
        $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function (TickTask $task, $tick, Player $player, int $count, $eid, Vector3 $pos, array $cx, array $cz, $time) {
            foreach ($eid as $number => $ar) {
                if ($ar[0] instanceof Player and $ar[0]->isOnline()) {
                    for ($i = 0; $i < 2; $i++) {
                        $pk = new RemoveEntityPacket;
                        $pk->eid = $ar[1][$i];
                        $ar[0]->dataPacket($pk);
                    }
                    $pk = new ExplodePacket;
                    $pk->x = $pos->x + 0.5;
                    $pk->y = $pos->y + 0.1;
                    $pk->z = $pos->z + 0.5;
                    $pk->radius = 0.1;
                    $ar[0]->dataPacket($pk);
                }
            }
            for ($i = 0; $i < 32; $i++)
                if (isset($number)) {
                    $player->getLevel()->addParticle(new ItemBreakParticle($pos->add(!empty($cx) ? $cx[$number] + 0.5 : 0.5, 0.2, !empty($cz) ? $cz[$number] + 0.5 : 0.5), Item::get(Item::GOLD_INGOT)));
                }

            $x = $z = [];
            $eid = [];
            foreach ($player->getLevel()->getPlayers() as $pl) {
                for ($i = 0; $i < $count; $i++) {
                    $eid[] = [$pl, $this->dropItem($pos->x + 0.5 + ($x[] = (mt_rand(-1000, 1000) / 1000)), $pos->y, $pos->z + 0.5 + ($z[] = (mt_rand(-1000, 1000) / 1000)), Item::get(175), $this->idToName(175), $pl, 1)];
                }
            }
            $this->coins($player, 1, $eid, $pos, $x, $z, $time == null ? null : $time);
            unset($tick, $task);
        }, $player, $count, $eid, $pos, $cx, $cz, $time), $time == null ? 40 : $time);
    }

    function coins(Player $player, int $count, $eid, $pos, array $x = [], array $z = [], $time = null)
    {
        $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function (TickTask $task, $tick, Player $player, int $count, $eid, Vector3 $pos, array $x, array $z) {
            foreach ($eid as $number => $ar) {
                if ($ar[0] instanceof Player and $ar[0]->isOnline()) {
                    for ($i = 0; $i < 2; $i++) {
                        $pk = new RemoveEntityPacket;
                        $pk->eid = $ar[1][$i];
                        $ar[0]->dataPacket($pk);
                    }
                    for ($i = 0; $i < 32; $i++)
                        $player->getLevel()->addParticle(new ItemBreakParticle($pos->add(!empty($x) ? $x[$number] + 0.5 : 0.5, 0.2, !empty($z) ? $z[$number] + 0.5 : 0.5), Item::get(Item::GOLD_INGOT)));
                    $pk = new ExplodePacket;
                    $pk->x = $pos->x + 0.5;
                    $pk->y = $pos->y + 0.1;
                    $pk->z = $pos->z + 0.5;
                    $pk->radius = 0.1;
                    $ar[0]->dataPacket($pk);
                }
            }
            unset($tick, $task);
        }, $player, $count, $eid, $pos, $x, $z), $time == null ? 40 : $time);
    }

    /**
     * @param PlayerInteractEvent $e
     */
    function onTap(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        /*$grow = 0.2;
        $length = 15;
        $particlesBase = 15;
        $radials = M_PI / 30;
        $radius = 1.5;
        $particlesHelix = 6;
        $baseInterval = 10;
        for ($j = 0; $j < $particlesHelix; $j++) {
            if ($this->step * $grow > $length) {
                $this->step = 0;
            }
            for ($i = 0; $i < 2; $i++) {
                $angle = $this->step * $radials + M_PI * $i;
                $v = new Vector3(cos($angle) * $radius, $this->step * $grow, sin($angle) * $radius);
                $this->getServer()->getDefaultLevel()->addParticle(new FlameParticle($player->add($v->x, $v->y, $v->z)));
            }
            if ($this->step % $baseInterval == 0) {
                for ($i = -$particlesBase; $i <= $particlesBase; $i++) {
                    if ($i == 0) {
                        continue;
                    }
                    $type = 1;
                    if ($i < 0) {
                        $type = 2;
                    }
                    $angle = $this->step * $radials;
                    $v = (new Vector3(cos($angle), 0, sin($angle)))->multiply($radius * $i / $particlesBase);
                    $v->y = $this->step * $grow;
                    $this->getServer()->getDefaultLevel()->addParticle(new FlameParticle($player->add($v->x, $v->y, $v->z)));
                }
            }
            $this->step++;
        }*/

        if ($player->getLevel()->getName() === $this->getServer()->getDefaultLevel()->getName() && $e->getBlock()->getId() === Item::TRAPPED_CHEST) {
            $e->setCancelled();
            /*$pk = new BlockEventPacket;
            $pk->x = $e->getBlock()->x;
            $pk->y = $e->getBlock()->y;
            $pk->z = $e->getBlock()->z;
            $pk->case1 = 1;
            $pk->case2 = 2;
            $player->dataPacket($pk);
            $this->getServer()->getScheduler()->scheduleDelayedTask(new TickTask($this, function(TickTask $task, $tick, BlockEventPacket $pk, Player $player) {
                $pk->case1 = 1;
                $pk->case2 = 0;
                $player->dataPacket($pk);
            }, $pk, $player), 20 * 6);*/

            /*
             *
                        $rand = mt_rand(0, 360);
                        $pedestal = new Vector3(979.5, 16.5, 704.5);
                        $x = sin(rad2deg($rand)) * 11 + 979.5;
                        $z = cos(rad2deg($rand)) * 11 + 704.5;
                        $yaw = $player->getMyYaw($pedestal->x - $x, $pedestal->z - $z);
                        $pitch = $player->getMyPitch($player, $pedestal->add(0, $player->getEyeHeight()));
                        $player->teleport(new Vector3($x, 14, $z), $yaw, $pitch);
             */
            $player->getInventory()->clearAll();
            //$player->getInventory()->addItem(Item::get(Item::BOW)->setCustomName(TextFormat::RESET . TextFormat::LIGHT_PURPLE . "Оружее дальнего боя"),
            //     Item::get(Item::DIAMOND_SWORD)->setCustomName(TextFormat::RESET . TextFormat::DARK_AQUA . "Оружее ближнего боя"),
            //     Item::get(Item::BREWING_STAND)->setCustomName(TextFormat::RESET . TextFormat::DARK_GREEN . "Предметы с эффектами"));
            $pk = new BlockEntityDataPacket();
            $nbt = new NBT(NBT::LITTLE_ENDIAN);
            $nbt->setData(new CompoundTag("", [
                new StringTag("id", Tile::CHEST),
                new StringTag("CustomName", TextFormat::GOLD . "Улучшение предметов"),
                new IntTag("x", (int)$e->getBlock()->x),
                new IntTag("y", (int)$e->getBlock()->y - 3),
                new IntTag("z", (int)$e->getBlock()->z)
            ]));
            $pk->namedtag = $nbt->write();
            $pk->x = $e->getBlock()->x;
            $pk->y = $e->getBlock()->y - 3;
            $pk->z = $e->getBlock()->z;
            $player->dataPacket($pk);
            $pk = new ContainerOpenPacket();
            $pk->windowid = 1;
            $pk->type = InventoryType::CHEST;
            $pk->x = $e->getBlock()->x;
            $pk->y = $e->getBlock()->y - 3;
            $pk->z = $e->getBlock()->z;
            $pk->slots = 1;
            $player->dataPacket($pk);
            $this->updateChest($player);
            $this->inUpgradeChest[$player->getId()] = true;
            return;
        }
        if ($e->getBlock()->getId() == Item::STONE_BUTTON) {
            $money = $this->api->getMoney($player->getName()) - 50;
            if ($money < 0) {
                $player->sendTip(TextFormat::DARK_RED . "Вам не хватает денег на покупку ключа для кейса!");
                return;
            }
            $this->api->setMoney($player->getName(), $money);
            $player->sendTip(TextFormat::DARK_GREEN . "Вы обменяли 50 монет на ключ для кейса!");
            $this->keys->set(strtolower($player->getName()), $this->keys->get(strtolower($player->getName())) + 1);
            $this->updateHotbar($player);
            return;
        }

        if ($e->getItem()->getId() === Item::SPRUCE_DOOR and isset($this->upgrading[$player->getId()])) {
            $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
            unset($this->inUpgradeChest[$player->getId()]);
            $player->getInventory()->clearAll();
            unset($this->upgrading[$player->getId()]);
            $this->updateHotbar($player);
            $player->getInventory()->sendContents($player);
            unset($this->cooldown[$player->getName()]);
            return;
        }
        if ($e->getItem()->getId() === 340) {
            if ($this->auth->isAuthenticated($player)) {
                if (isset($this->upgrading[$player->getId()])) {
                    $item = $this->upgrading[$player->getId()];
                    $player->sendMessage(TCapi::colorize($this->translations->getNested("weaponDescriptions." . $this->idToShortName($item->getId(), $item->getDamage()), "{RED}Описание не завезли :c")));
                } else
                    $player->sendTip(TextFormat::RED . "В разработке.");
                /*$this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this, function (TickTask $task, $tick, Player $player, Location $start) {
                    if($player->isOnline()) {
                        $location = $player->add(sin(rad2deg($tick)) * 6, 0, cos(rad2deg($tick)) * 6);
                        $location->yaw = $player->getMyYaw($start->x - $location->x, $start->z - $location->y);
                        $location->pitch = $player->getMyPitch($location, $start);
                        $player->teleport($location);
                    }
                    if($tick >= 80) {
                        $task->cancel();
                    }
                }, $player, $player->getLocation()), 2);*/
                return;
            }
        } else
            if ($e->getItem()->getId() === 267) {
                if ($this->auth->isAuthenticated($player)) {
                    $this->addToWaitRoom($player);
                    return;
                }
            } elseif ($e->getItem()->getId() === 498) {
                if ($this->auth->isAuthenticated($player)) {
                    if (!isset($this->hide[$player->getName()]) || $this->hide[$player->getName()] === false) {
                        $this->hide[$player->getName()] = true;
                        foreach ($player->getLevel()->getPlayers() as $p) {
                            $player->hidePlayer($p);
                        }
                        $this->updateHotbar($player);
                    } elseif (isset($this->hide[$player->getName()]) && $this->hide[$player->getName()] == true) {
                        $this->hide[$player->getName()] = false;
                        foreach ($player->getLevel()->getPlayers() as $p) {
                            $player->showPlayer($p);
                        }
                        $this->updateHotbar($player);
                    }
                    return;
                }
            } elseif ($e->getItem()->getId() === 397) {
                if ($this->auth->isAuthenticated($player)) {
                    $player->sendMessage(TextFormat::BLACK . str_repeat("-", 5) . TextFormat::GOLD . " " . strtolower($player->getName()) . " " . TextFormat::BLACK . str_repeat("-", 5) . "\n" . ($player instanceof Player ? TextFormat::GREEN . "Онлайн, " . ($this->isInGame($player) ? "на арене" : "не на арене") : TextFormat::RED . "Оффлайн") . "\n" . TextFormat::DARK_GREEN . "Побед: " . $this->top->get(strtolower($player->getName()))[0] . "\n" . TextFormat::DARK_RED . "Поражений: " . $this->top->get(strtolower($player->getName()))[1] . "\n" . TextFormat::BLACK . str_repeat("-", 13 + strlen($player->getName())));
                    return;
                }
            }
        if ($e->getItem()->getId() === Item::WOODEN_PICKAXE && $e->getBlock()->getId() === Item::MONSTER_SPAWNER) {
            if (!isset($this->case[$player->getName()]))
                $this->case[$player->getName()] = 0;
            $e->setCancelled();
            $this->case[$player->getName()]++;
            $block = $e->getBlock();
            for ($i = 0; $i < 32; $i++)
                $player->getLevel()->addParticle(new ItemBreakParticle($block->add(mt_rand(-10000, 10000) / 20000 + 0.5, mt_rand(0, 10000) / 10000, mt_rand(-10000, 10000) / 20000 + 0.5), Item::get(318)));
            $player->getLevel()->addParticle(new DestroyBlockParticle($block, $block));
            $item = $player->getInventory()->getItemInHand();
            $item->setDamage($this->case[$player->getName()] * 10);
            $player->getInventory()->setItemInHand($item);
            if ($this->case[$player->getName()] >= 6) {
                $item = $player->getInventory()->getItemInHand();
                $item->setCount($item->getCount() - 1);
                $item->setDamage(0);
                if ($item->getCount() == 0)
                    $player->getInventory()->setItemInHand(new Item(0));
                else
                    $player->getInventory()->setItemInHand($item);
                $player->getLevel()->setBlock($block, new Air);
                $this->cellItem($player, $block);
            }
            return;
        }
        if (!$this->isInGame($e->getPlayer()) || $this->getArena($e->getPlayer())["wait"] > 0 || count($this->getArena($e->getPlayer())["players"]) == 1) {
            if (isset($this->upgrading[$player->getId()]) and $this->upgrading[$player->getId()]->getId() === $e->getItem()->getId() and !$this->processItem($player, $e->getItem(), true))
                $e->setCancelled();
            elseif (isset($this->upgrading[$player->getId()]) and $this->upgrading[$player->getId()]->getId() !== ($id = $e->getItem()->getId()) and key_exists($id, self::UPGRADE)) {
                unset($this->cooldown[$player->getName()][$this->idToShortName($this->upgrading[$player->getId()]->getId(), $this->upgrading[$player->getId()]->getDamage())]);
                $e->setCancelled();
                $last = null;
                $next = null;
                foreach (self::UPGRADE as $upgrade => $cost) {
                    if ($upgrade === $id) {
                        $next = true;
                        continue;
                    }
                    if ($next === true) {
                        if ($this->isUnlocked($player, $upgrade))
                            $next = $upgrade;
                        else
                            continue;
                        break;
                    }
                    $last = $upgrade;
                }
                while ($next === true or !$this->isUnlocked($player, $next)) {
                    $keys = array_keys(self::UPGRADE);
                    $next = array_shift($keys);
                }
                $a = self::UPGRADE;
                $key = 0;
                while (isset($key) and !$this->isUnlocked($player, $key)) {
                    end($a);
                    $key = key($a);
                    unset($a[$key]);
                    reset($a);
                }
                $idLast = $last === null ? $key : $last;
                $metaLast = 0;
                if ($idLast === 325)
                    $metaLast = 8;
                elseif ($idLast === 351)
                    $metaLast = 1;
                $metaNext = 0;
                if ($next === 325)
                    $metaNext = 8;
                elseif ($next === 351)
                    $metaNext = 1;
                $item = $e->getItem();
                $level = $this->getItemLevel($player, $id);
                $levelLast = $this->getItemLevel($player, $idLast);
                $levelNext = $this->getItemLevel($player, $next);
                if ($level >= count(self::UPGRADE[$id]) + 1) {
                    $up = Item::get(Item::APPLE)
                        ->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Предмет улучшен до последнего уровня!");
                } elseif (($current = $this->api->getMoney($player->getName())) < ($cost = $this->getUpgradeCost($player, $id))) {
                    $up = Item::get(Item::APPLE)
                        ->setCustomName(TextFormat::RESET . TextFormat::DARK_RED . "Улучшение стоит $cost монет, у вас есть только $current");
                } else {
                    $up = Item::get(Item::GOLDEN_APPLE)
                        ->setCustomName(TextFormat::RESET . TextFormat::GOLD . "Улучшить предмет до уровня " . ($level + 1));
                }
                $player->sendMessage(TCapi::colorize($this->translations->getNested("weaponDescriptions." . $this->idToShortName($id, $item->getDamage()), "{RED}Описание не завезли :c")));
                $player->getInventory()->setItem(0, Item::get($idLast, $metaLast, $levelLast)
                    ->setCustomName(TextFormat::RESET . TextFormat::AQUA . "Предыдущий предмет: " . $this->idToName($idLast, $metaLast)));
                $player->getInventory()->setItem(2, Item::get(Item::BOOK)
                    ->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Информация"));
                $player->getInventory()->setItem(3, $item
                    ->setCustomName(TextFormat::RESET . TextFormat::DARK_AQUA . "Текущий предмет: " . $this->idToName($item->getId(), $item->getDamage())));
                $player->getInventory()->setItem(4, $up);
                $player->getInventory()->setItem(6, Item::get($next, $metaNext, $levelNext)
                    ->setCustomName(TextFormat::RESET . TextFormat::AQUA . "Следующий предмет: " . $this->idToName($next, $metaNext)));
                $player->getInventory()->setItem(8, Item::get(Item::SPRUCE_DOOR)
                    ->setCustomName(TextFormat::RESET . TextFormat::RED . "В хаб"));
                $player->getInventory()->setHeldItemIndex(3);
                $this->upgrading[$player->getId()] = $item;
            }
            return;
        }
        if (!$this->processItem($player, $e->getItem()))
            $e->setCancelled();
    }

    private function isUnlocked(Player $player, $key)
    {
        foreach ($this->items->get(strtolower($player->getName())) ?? [] as $category => $items)
            if (in_array($key, $items))
                return true;
        return false;
    }

    private function getItemLevel($player, int $item)
    {
        return $this->levels->getNested(
            (
            $player instanceof Player ?
                strtolower($player->getName()) :
                strtolower($player)
            )
            . "." . $item, 1);
    }
}