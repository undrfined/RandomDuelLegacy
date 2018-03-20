<?php

namespace VS;


use pocketmine\entity\Entity;
use pocketmine\entity\Item;
use pocketmine\entity\Projectile;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\Player;

class Enderpearl extends Projectile{
    const NETWORK_ID = 87;

    public $width = 0.25;
    public $length = 0.25;
    public $height = 0.25;

    protected $gravity = 0.03;
    protected $drag = 0.01;
    protected $dmg;

    public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null, $damage = 6){
        parent::__construct($chunk, $nbt, $shootingEntity);
        $this->dmg = $damage;
    }

    public function onUpdate($currentTick){
        if($this->closed){
            return false;
        }

        $this->timings->startTiming();

        $hasUpdate = parent::onUpdate($currentTick);

        if($this->isCollided){
            $this->kill();
            $this->shootingEntity->teleport($this);
            if($this->dmg != 0)
                $this->shootingEntity->attack($this->dmg, new EntityDamageEvent($this->shootingEntity, EntityDamageEvent::CAUSE_FALL, $this->dmg));
            $hasUpdate = true;
        }

        if($this->age > 1200) {
            $this->kill();
            $hasUpdate = true;
            $this->shootingEntity->teleport($this);
            if($this->dmg != 0)
                $this->shootingEntity->attack($this->dmg, new EntityDamageEvent($this->shootingEntity, EntityDamageEvent::CAUSE_FALL, $this->dmg));
        }

        $this->timings->stopTiming();

        return $hasUpdate;
    }

    public function spawnTo(Player $player){
        $pk = new AddItemEntityPacket;
        $pk->eid = $this->getId();
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->speedX = $this->motionX;
        $pk->speedY = $this->motionY;
        $pk->speedZ = $this->motionZ;
        $pk->item = \pocketmine\item\Item::get(90);
        $player->dataPacket($pk);

        parent::spawnTo($player);
    }
}