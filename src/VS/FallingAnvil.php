<?php
/**
 *                  _       __ _                _
 *                 | |     / _(_)              | |
 *  _   _ _ __   __| |_ __| |_ _ _ __   ___  __| |
 * | | | | '_ \ / _` | '__|  _| | '_ \ / _ \/ _` |
 * | |_| | | | | (_| | |  | | | | | | |  __/ (_| |
 *  \__,_|_| |_|\__,_|_|  |_| |_|_| |_|\___|\__,_|
 */


namespace VS;

use pocketmine\block\Block;
use pocketmine\entity\FallingSand;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\math\Vector3;

class FallingAnvil extends FallingSand
{
    public function onUpdate($currentTick)
    {

        if ($this->closed) {
            return false;
        }

        $this->timings->startTiming();

        $tickDiff = $currentTick - $this->lastUpdate;
        if ($tickDiff <= 0 and !$this->justCreated) {
            return true;
        }

        $this->lastUpdate = $currentTick;

        $height = $this->fallDistance;

        $hasUpdate = $this->entityBaseTick($tickDiff);

        if ($this->isAlive()) {
            $pos = (new Vector3($this->x - 0.5, $this->y, $this->z - 0.5))->round();

            if ($this->ticksLived === 1) {
                $block = $this->level->getBlock($pos);
                if ($block->getId() !== $this->blockId) {
                    return true;
                }
                $this->level->setBlock($pos, Block::get(0), true);
            }

            $this->motionY -= $this->gravity;

            $this->move($this->motionX, $this->motionY, $this->motionZ);

            $friction = 1 - $this->drag;

            $this->motionX *= $friction;
            $this->motionY *= 1 - $this->drag;
            $this->motionZ *= $friction;


            if ($this->onGround) {
                $this->kill();

                $sound = new AnvilFallSound($this);
                $this->getLevel()->addSound($sound);
                foreach ($this->level->getNearbyEntities($this->boundingBox->grow(0.1, 0.1, 0.1), $this) as $entity) {
                    $entity->scheduleUpdate();
                    if (!$entity->isAlive()) {
                        continue;
                    }
                    if ($entity instanceof Living) {
                        $damage = ($height - 1) * 2;
                        if ($damage > 40) $damage = 40;
                        $ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageByEntityEvent::CAUSE_FALL, $damage, 0.1);
                        $entity->attack($damage, $ev);
                    }
                }
                $hasUpdate = true;
            }

            $this->updateMovement();
        }

        return $hasUpdate or !$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001;
    }
}