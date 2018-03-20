<?php
/**
 * Created by PhpStorm.
 * User: Gena
 * Date: 22.08.2016
 * Time: 12:52
 */

namespace VS;

use pocketmine\entity\Arrow;
use pocketmine\entity\Living;
use pocketmine\entity\Minecart;
use pocketmine\entity\Projectile;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\item\Potion;
use pocketmine\level\format\FullChunk;
use pocketmine\level\MovingObjectPosition;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\SetEntityLinkPacket;
use pocketmine\Player;
use pocketmine\entity\Entity;

class Fakecart extends Projectile
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

    public $slimepearl;

    public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity, Slimepearl $slimepearl)
    {
        parent::__construct($chunk, $nbt);
        $this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_INVISIBLE, true);
        $this->shot = $shootingEntity;
        $this->slimepearl = $slimepearl;
    }

    public function attack($damage, EntityDamageEvent $source)
    {
        if ($source->getCause() === EntityDamageEvent::CAUSE_VOID) {
            parent::attack($damage, $source);
        }
    }

    protected function initEntity()
    {
        parent::initEntity();

        $this->setMaxHealth(1);
        $this->setHealth(1);
        if (isset($this->namedtag->Age)) {
            $this->age = $this->namedtag["Age"];
        }

    }

    public function canCollideWith(Entity $entity)
    {
        return $entity instanceof Living and !$this->onGround;
    }

    public function saveNBT()
    {
        parent::saveNBT();
        $this->namedtag->Age = new ShortTag("Age", $this->age);
    }

    public function kill()
    {
        foreach ($this->getLevel()->getPlayers() as $pl) {
            $pk = new SetEntityLinkPacket;
            $pk->type = 3;
            $pk->from = $this->getId();
            $pk->to = $this->shot->getId();
            if ($pl != $this->shot)
                $pl->dataPacket($pk);
        }
        $pk = new SetEntityLinkPacket;
        $pk->type = 3;
        $pk->from = $this->getId();
        $pk->to = 0;
        $this->shot->dataPacket($pk);
        $this->shot->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_RIDING, false);
        parent::kill();
    }

    public function onUpdate($currentTick)
    {
        if ($this->closed) {
            return false;
        }


        $this->timings->startTiming();


        if ($this->closed) {
            return false;
        }


        $tickDiff = $currentTick - $this->lastUpdate;
        if ($tickDiff <= 0 and !$this->justCreated) {
            return true;
        }
        $this->lastUpdate = $currentTick;

        $hasUpdate = $this->entityBaseTick($tickDiff);

        if ($this->isAlive()) {
            $this->motionY -= $this->gravity;
            $this->move($this->motionX, $this->motionY, $this->motionZ);
            if(!$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001){
                $f = sqrt(($this->motionX ** 2) + ($this->motionZ ** 2));
                $this->yaw = (atan2($this->motionX, $this->motionZ) * 180 / M_PI);
                $this->pitch = (atan2($this->motionY, $f) * 180 / M_PI);
                $hasUpdate = true;
            }
            $this->updateMovement();
        }


        if (!$this->slimepearl->isAlive()) {
            $this->kill();
            $hasUpdate = true;
        }

        $this->timings->stopTiming();


        return $hasUpdate;
    }


    public function spawnTo(Player $player)
    {
        $pk = new AddEntityPacket();
        $pk->eid = $this->getId();
        $pk->type = Minecart::NETWORK_ID;
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
        $pk = new SetEntityLinkPacket;
        $pk->type = 2;
        $pk->from = $this->getId();
        $pk->to = $this->shot->getId();
        $player->dataPacket($pk);
        $pk = new SetEntityLinkPacket;
        $pk->type = 2;
        $pk->from = $this->getId();
        $pk->to = 0;
        if ($player == $this->shot)
            $player->dataPacket($pk);
        $this->shot->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_RIDING, true);
        parent::spawnTo($player);
    }
}