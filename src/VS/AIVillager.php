<?php

namespace VS;

use pocketmine\entity\Projectile;
use pocketmine\level\Explosion;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\level\format\FullChunk;
use pocketmine\entity\Creature;
use pocketmine\entity\NPC;
use pocketmine\entity\Ageable;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

class AIVillager extends Projectile implements NPC, Ageable{
    const PROFESSION_FARMER = 0;
    const PROFESSION_LIBRARIAN = 1;
    const PROFESSION_PRIEST = 2;
    const PROFESSION_BLACKSMITH = 3;
    const PROFESSION_BUTCHER = 4;

    const NETWORK_ID = 15;

    const DATA_PROFESSION_ID = 16;

    public $width = 0.25;
    public $length = 0.25;
    public $height = 0.25;
    protected $gravity = 0.03;
    protected $drag = 0.01;

    public $player;
    public $power;

    public function getName() : string{
        return "Villager";
    }

    public function __construct(FullChunk $chunk, Player $player, $power = 1){
        $this->power = $power;
        $nbt = new CompoundTag("", [
            "Pos" => new ListTag("Pos", [
                new DoubleTag("", $player->x + $player->getDirectionVector()->x),
                new DoubleTag("", $player->y + $player->getDirectionVector()->y + $player->getEyeHeight()),
                new DoubleTag("", $player->z + $player->getDirectionVector()->z)
            ]),
            "Motion" => new ListTag("Motion", [
                new DoubleTag("", -sin($player->yaw / 180 * M_PI) * cos($player->pitch / 180 * M_PI)),
                new DoubleTag("", -sin($player->pitch / 180 * M_PI)),
                new DoubleTag("", cos($player->yaw / 180 * M_PI) * cos($player->pitch / 180 * M_PI))
            ]),
            "Rotation" => new ListTag("Rotation", [
                new FloatTag("", $player->yaw),
                new FloatTag("", $player->pitch)
            ])
        ]);
        if(!isset($nbt->Profession)){
            $nbt->Profession = new ByteTag("Profession", mt_rand(0, 4));
        }

        $this->player = $player;

        parent::__construct($chunk, $nbt);
        $this->damage = 2;
        $this->setDataProperty(self::DATA_PROFESSION_ID, self::DATA_TYPE_BYTE, $this->getProfession());
    }

    protected function initEntity(){
        parent::initEntity();
        if(!isset($this->namedtag->Profession)){
            $this->setProfession(self::PROFESSION_FARMER);
        }
    }

    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = AIVillager::NETWORK_ID;
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->speedX = $this->motionX;
        $pk->speedY = $this->motionY;
        $pk->speedZ = $this->motionZ;
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->metadata = $this->dataProperties;
        $player->dataPacket($pk);

        parent::spawnTo($player);
    }

    public function onUpdate($tick)
    {
        if($this->closed){
            return false;
        }

        $this->timings->startTiming();

        $hasUpdate = parent::onUpdate($tick);

        if($this->age > 1200 or $this->isCollided){
            $explosion = new Explosion($this, $this->power / 3);
            $explosion->explodeB();
            $this->kill();
            $hasUpdate = true;
        }

        $this->timings->stopTiming();

        return $hasUpdate;
    }

    /**
     * Sets the villager profession
     *
     * @param int $profession
     */
    public function setProfession(int $profession){
        $this->namedtag->Profession = new ByteTag("Profession", $profession);
    }

    public function getProfession() : int{
        $pro = (int) $this->namedtag["Profession"];
        return min(4, max(0, $pro));
    }

    public function isBaby(){
        return $this->getDataFlag(self::DATA_AGEABLE_FLAGS, self::DATA_FLAG_BABY);
    }
}
