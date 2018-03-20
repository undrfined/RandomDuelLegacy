<?php
/**
 * Created by PhpStorm.
 * User: Gena
 * Date: 25.07.2016
 * Time: 18:08
 */

namespace VS;


use pocketmine\entity\Arrow;
use pocketmine\level\Explosion;

class MyArrow extends Arrow
{

    protected $explosive = false;

    /**
     * @return boolean
     */
    public function isExplosive(): bool
    {
        return $this->explosive;
    }

    /**
     * @param boolean $explosive
     */
    public function setExplosive(bool $explosive = true)
    {
        $this->explosive = $explosive;
    }

    function addDamage($damage) {
        $this->damage += $damage;
    }

    function setPotionId($id) {
        $this->potionId = $id;
    }

    function onUpdate($currentTick)
    {
        $hasUpdate = parent::onUpdate($currentTick);
        if($this->explosive && $this->hadCollision){
            (new Explosion($this, 1.5))->explodeB();
        }
        if($this->hadCollision) {
            $this->kill();
            $this->close();
        }
        return $hasUpdate;
    }
}