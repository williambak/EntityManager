<?php

namespace plugin\MonsterEntity;

use plugin\AnotherEntity\BaseEntity;
use pocketmine\block\Water;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Timings;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;

abstract class Monster extends BaseEntity{

    protected $attackDelay = 0;

    private $damage = [];

    public abstract function updateTick();

    /**
     * @return int[]
     */
    public function getDamage(){
        return $this->damage;
    }

    /**
     * @param float|float[] $damage
     * @param int $difficulty
     */
    public function setDamage($damage, $difficulty = null){
        $difficulty = $difficulty === null ? Server::getInstance()->getDifficulty() : (int) $difficulty;
        if(is_array($damage)) $this->damage = $damage;
        elseif($difficulty >= 1 && $difficulty <= 3) $this->damage[$difficulty] = (float) $damage;
    }

    public function updateMove($tick = 1){
        $target = null;
        if($this->isMovement()){
            $target = $this->getTarget();
            $x = $target->x - $this->x;
            $y = $target->y - $this->y;
            $z = $target->z - $this->z;
            $atn = atan2($z, $x);
            if($this->stayTime > 0){
                $this->move(0, 0);
                $this->stayTime -= $tick;
                if($this->stayTime <= 0) $this->stayVec = null;
            }else{
                $speed = [
                    Zombie::NETWORK_ID => 0.11,
                    Creeper::NETWORK_ID => 0.09,
                    Skeleton::NETWORK_ID => 0.1,
                    Spider::NETWORK_ID => 0.113,
                    PigZombie::NETWORK_ID => 0.115,
                    Enderman::NETWORK_ID => 0.121
                ];
                $add = $this instanceof PigZombie && $this->isAngry() ? 0.132 : $speed[static::NETWORK_ID];
                $this->move(cos($atn) * $add * $tick, sin($atn) * $add * $tick);
            }
            $this->setRotation(rad2deg($atn - M_PI_2), rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2))));
        }
        $this->updateMovement();
        return $target;
    }

    public function entityBaseTick($tickDiff = 1){
        Timings::$timerEntityBaseTick->startTiming();

        if(!$this->isAlive()) return false;
        $hasUpdate = Entity::entityBaseTick($tickDiff);

        if($this->isInsideOfSolid()){
            $hasUpdate = true;
            $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
            $this->attack($ev->getFinalDamage(), $ev);
        }

        if($this instanceof Enderman){
            if($this->level->getBlock(new Vector3(Math::floorFloat($this->x), Math::floorFloat($this->y), Math::floorFloat($this->z))) instanceof Water){
                $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 2);
                $this->attack($ev->getFinalDamage(), $ev);
                $this->teleport($this->add(mt_rand(-20, 20), mt_rand(-20, 20), mt_rand(-20, 20)));
            }
        }else{
            if(!$this->hasEffect(Effect::WATER_BREATHING) && $this->isInsideOfWater()){
                $hasUpdate = true;
                $airTicks = $this->getDataProperty(self::DATA_AIR) - $tickDiff;
                if($airTicks <= -20){
                    $airTicks = 0;
                    $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 2);
                    $this->attack($ev->getFinalDamage(), $ev);
                }
                $this->setDataProperty(self::DATA_AIR, self::DATA_TYPE_SHORT, $airTicks);
            }else{
                $this->setDataProperty(self::DATA_AIR, self::DATA_TYPE_SHORT, 300);
            }
        }

        if($this->attackTime > 0) $this->attackTime -= $tickDiff;
        Timings::$timerEntityBaseTick->stopTiming();
        return $hasUpdate;
    }

    /**
     * @return Player|Vector3
     */
    public function getTarget(){
        if(!$this->isMovement()) return new Vector3();
        if($this->stayTime > 0){
            if($this->stayVec === null or (mt_rand(1, 115) <= 3 and $this->stayTime % 20 === 0)) $this->stayVec = $this->add(mt_rand(-10, 10), mt_rand(-3, 3), mt_rand(-10, 10));
            return $this->stayVec;
        }
        $target = null;
        $nearDistance = PHP_INT_MAX;
        foreach($this->getViewers() as $p){
            if($p->spawned and $p->isAlive() and !$p->closed and $p->isSurvival() and ($distance = $this->distanceSquared($p)) <= 81){
                if($distance < $nearDistance){
                    $target = $p;
                    $nearDistance = $distance;
                    continue;
                }
            }
        }
        if(($target === null || ($this instanceof PigZombie && !$this->isAngry())) && $this->stayTime <= 0 && mt_rand(1, 420) === 1){
            $this->stayTime = mt_rand(100, 450);
            return $this->stayVec = $this->add(mt_rand(-10, 10), mt_rand(-3, 3), mt_rand(-10, 10));
        }
        if((!$this instanceof PigZombie && $target instanceof Player) || ($this instanceof PigZombie && $this->isAngry() && $target instanceof Player)){
            return $target;
        }elseif($this->moveTime >= mt_rand(650, 800) or !$this->target instanceof Vector3){
            $this->moveTime = 0;
            $this->target = $this->add(mt_rand(-100, 100), 0, mt_rand(-100, 100));
        }
        return $this->target;
    }

}
