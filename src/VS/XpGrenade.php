<?php
/**
 * Created by PhpStorm.
 * User: Gena
 * Date: 12.08.2016
 * Time: 10:52
 */

namespace VS;


use pocketmine\entity\Entity;
use pocketmine\entity\Projectile;
use pocketmine\entity\ThrownExpBottle;
use pocketmine\entity\XPOrb;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\level\particle\SpellParticle;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

class XpGrenade extends Projectile
{
    const NETWORK_ID = 68;

    public $width = 0.25;
    public $length = 0.25;
    public $height = 0.25;

    protected $gravity = 0.1;
    protected $drag = 0.15;

    public function __construct(FullChunk $chunk, CompoundTag $nbt, Entity $shootingEntity = null){
        parent::__construct($chunk, $nbt, $shootingEntity);
    }

    function onUpdate($currentTick)
    {
        if ($this->closed) {
            return false;
        }

        $this->timings->startTiming();

        $hasUpdate = parent::onUpdate($currentTick);

        $this->age++;

        if ($this->age > 1200 or $this->isCollided) {
            $this->kill();
            $this->close();
            $this->getLevel()->addParticle(new SpellParticle($this, 46, 82, 153));
            $v1 = $this->add(0, -0.2, 0);
            $v2 = $this->add(-0.1, -0.2, 0);
            $v3 = $this->add(0, -0.2, -0.1);
            $nbt1 = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $v1->x),
                    new DoubleTag("", $v1->y),
                    new DoubleTag("", $v1->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new FloatTag("", 0),
                    new FloatTag("", 0)
                ])
            ]);
            $nbt2 = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $v2->x),
                    new DoubleTag("", $v2->y),
                    new DoubleTag("", $v2->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new FloatTag("", 0),
                    new FloatTag("", 0)
                ])
            ]);
            $nbt3 = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $v3->x),
                    new DoubleTag("", $v3->y),
                    new DoubleTag("", $v3->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new FloatTag("", 0),
                    new FloatTag("", 0)
                ])
            ]);
            $orb1 = new FakeXPOrb($this->getLevel()->getChunk($v1->x >> 4, $v1->z >> 4), $nbt1);
            $orb2 = new FakeXPOrb($this->getLevel()->getChunk($v2->x >> 4, $v2->z >> 4), $nbt2);
            $orb3 = new FakeXPOrb($this->getLevel()->getChunk($v3->x >> 4, $v3->z >> 4), $nbt3);
            $orb1->spawnToAll();
            $orb2->spawnToAll();
            $orb3->spawnToAll();
            $hasUpdate = true;
        }

        $this->timings->stopTiming();

        return $hasUpdate;
    }
    public function spawnTo(Player $player){
        $pk = new AddEntityPacket();
        $pk->type = XpGrenade::NETWORK_ID;
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
