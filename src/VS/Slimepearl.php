<?php
/**
 * Created by PhpStorm.
 * User: Gena
 * Date: 22.08.2016
 * Time: 11:58
 */

namespace VS;


use pocketmine\entity\Arrow;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Minecart;
use pocketmine\entity\Projectile;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\item\Item;
use pocketmine\item\Potion;
use pocketmine\level\Explosion;
use pocketmine\level\format\FullChunk;
use pocketmine\level\MovingObjectPosition;
use pocketmine\level\particle\ItemBreakParticle;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\SetEntityLinkPacket;
use pocketmine\Player;

class Slimepearl extends Projectile
{
    const NETWORK_ID = 37;

    public $width = 0.3;
    public $length = 0.9;
    public $height = 5;
    public $damage = 0;
    protected $gravity = 0.05;
    protected $drag = 0.01;

    public $hadCollision = false;
    public $shot;
    public $type;

    public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity, $type = 0){
        parent::__construct($chunk, $nbt);
        $this->shot = $shootingEntity;
        $this->type = $type;
        $this->setDataProperty(Entity::DATA_LEAD, Entity::DATA_TYPE_BYTE, 0);
        $this->setDataProperty(16, Entity::DATA_TYPE_BYTE, 3);
        $this->setDataProperty(Entity::DATA_LEAD_HOLDER, Entity::DATA_TYPE_LONG, -1);
    }

    public function attack($damage, EntityDamageEvent $source){
        if($source->getCause() === EntityDamageEvent::CAUSE_VOID){
            parent::attack($damage, $source);
        }
    }

    public function canCollideWith(Entity $entity){
        return $entity instanceof Living and !$this->onGround and $this->shot != $entity;
    }

    protected function initEntity(){
        parent::initEntity();

        $this->setMaxHealth(1);
        $this->setHealth(1);
        if(isset($this->namedtag->Age)){
            $this->age = $this->namedtag["Age"];
        }

    }



    public function saveNBT(){
        parent::saveNBT();
        $this->namedtag->Age = new ShortTag("Age", $this->age);
    }

    public function kill()
    {
        parent::kill();
        if($this->type === 0) {
            $e = new Explosion($this, 2);
            $e->explodeB();
        }
        $this->shot->sendTip("");
    }

    public function onUpdate($currentTick){
        if($this->ticksLived === 1)
            $this->shootingEntity = $this->shot;
        if($this->closed){
            return false;
        }


        $this->timings->startTiming();


        $hasUpdate = parent::onUpdate($currentTick);

        if($this->age % 80 === 0) {
            $this->shot->sendTip("Что бы спрыгнуть с слизня нажмите «Прыжок»");
        }
        if($this->age > 1200){
            $this->kill();
            $hasUpdate = true;
        }

        if($this->isCollided) {
            $this->kill();
            $hasUpdate = true;
        }

        if($this->isAlive()) {
            for($i = 0; $i < 32; $i++)
                $this->getLevel()->addParticle(new ItemBreakParticle($this, Item::get($this->type === 0 ? Item::SLIMEBALL : Item::MAGMA_CREAM)));
            $this->getLevel()->addSound(new GhastShootSound($this));
            if($this->type === 1)$this->shot->setOnFire(1);
        }

        $this->timings->stopTiming();


        return $hasUpdate;
    }


    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = $this->type === 0 ? Slimepearl::NETWORK_ID : 42;
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
}