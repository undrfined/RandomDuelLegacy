<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

namespace VS;

use pocketmine\entity\Projectile;
use pocketmine\entity\Entity;
use pocketmine\level\Explosion;
use pocketmine\level\format\FullChunk;
use pocketmine\level\MovingObjectPosition;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

class Fireball extends Projectile
{
    const NETWORK_ID = 94;

    public $width = 0.25;
    public $length = 0.25;
    public $height = 0.25;

    protected $explosive;
    protected $fire;
    protected $gravity = 0.03;
    protected $drag = 0.01;

    public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null, $power = 0, $fire = false)
    {
        $this->shootingEntity = $shootingEntity;
        $this->explosive = $power;
        $this->fire = $fire;
        if ($shootingEntity !== null) {
            //$this->setDataProperty(self::DATA_SHOOTER_ID, self::DATA_TYPE_LONG, $shootingEntity->getId());
            // removed because of fireball is not projectile in MCPE
        }
        parent::__construct($chunk, $nbt);
        $this->damage = 2;
    }

    public function onUpdate($currentTick)
    {
        if ($this->closed) {
            return false;
        }

        $this->fireTicks++;

        $this->timings->startTiming();

        $hasUpdate = parent::onUpdate($currentTick);

        if ($this->hadCollision && $this->explosive) {
            (new Explosion($this, $this->explosive))->explodeB();
            $this->kill();
        }

        if ($this->fire) {
            $list = $this->getLevel()->getCollidingEntities($this->boundingBox->addCoord($this->motionX, $this->motionY, $this->motionZ)->expand(1, 1, 1), $this);

            $moveVector = new Vector3($this->x + $this->motionX, $this->y + $this->motionY, $this->z + $this->motionZ);
            $nearDistance = PHP_INT_MAX;
            $nearEntity = null;

            foreach ($list as $entity) {
                if ($entity === $this->shootingEntity and $this->ticksLived < 5) {
                    continue;
                }

                $axisalignedbb = $entity->boundingBox->grow(0.3, 0.3, 0.3);
                $ob = $axisalignedbb->calculateIntercept($this, $moveVector);

                if ($ob === null) {
                    continue;
                }

                $distance = $this->distanceSquared($ob->hitVector);

                if ($distance < $nearDistance) {
                    $nearDistance = $distance;
                    $nearEntity = $entity;
                }
            }

            if ($nearEntity !== null) {
                $movingObjectPosition = MovingObjectPosition::fromEntity($nearEntity);
            }

            if (isset($movingObjectPosition) && $movingObjectPosition !== null) {
                if (($entity = $movingObjectPosition->entityHit) !== null) {
                    $entity->setOnFire(3);
                }
            }
        }

        if ($this->age > 1200 or $this->isCollided) {
            $this->kill();
            $hasUpdate = true;
        }

        $this->timings->stopTiming();

        return $hasUpdate;
    }

    public
    function spawnTo(Player $player)
    {
        $pk = new AddEntityPacket();
        $pk->type = Fireball::NETWORK_ID;
        $pk->eid = $this->getId();
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->speedX = $this->motionX;
        $pk->speedY = $this->motionY;
        $pk->speedZ = $this->motionZ;
        $pk->metadata = $this->dataProperties;
        $player->dataPacket($pk);

        parent::spawnTo($player);
    }
}