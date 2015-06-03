<?php

namespace plugin\Entity;

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

    private $damage = [];

    public abstract function attackOption(Player $player);

    /**
     * @param int $difficulty
     *
     * @return int
     */
    public function getDamage($difficulty = null){
        if($difficulty === null or !is_numeric($difficulty)){
            $difficulty = Server::getInstance()->getDifficulty();
        }
        return isset($this->damage[(int) $difficulty]) ? $this->damage[(int) $difficulty] : 0;
    }

    /**
     * @param float|float[] $damage
     * @param int $difficulty
     */
    public function setDamage($damage, $difficulty = null){
        $difficulty = $difficulty === null ? Server::getInstance()->getDifficulty() : (int) $difficulty;
        if(is_array($damage)){
            foreach($damage as $key => $int){
                $this->damage[(int) $key] = (float) $int;
            }
        }elseif($difficulty >= 1 && $difficulty <= 3){
            $this->damage[$difficulty] = (float) $damage;
        }
    }

    public function updateMove(){
        $target = null;
        if($this->isMovement()){
            if($this->stayTime > 0){
                $this->move(0, 0);
                if(--$this->stayTime <= 0) $this->stayVec = null;
            }else{
                $target = $this->getTarget();
                $x = $target->x - $this->x;
                $y = $target->y - $this->y;
                $z = $target->z - $this->z;
                $atn = atan2($z, $x);
                $speed = [
                    Zombie::NETWORK_ID => 0.11,
                    Creeper::NETWORK_ID => 0.09,
                    Skeleton::NETWORK_ID => 0.1,
                    Spider::NETWORK_ID => 0.113,
                    PigZombie::NETWORK_ID => 0.115,
                    Enderman::NETWORK_ID => 0.121
                ];
                $add = $this instanceof PigZombie && $this->isAngry() ? 0.132 : $speed[static::NETWORK_ID];
                $this->move(cos($atn) * $add, sin($atn) * $add);
                $this->yaw = rad2deg($atn - M_PI_2);
                $this->pitch = $y == 0 ? 0 : rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2)));
            }
        }
        $this->updateMovement();
        return $target;
    }

    public function updateTick(){
        if($this->server->getDifficulty() < 1){
            $this->close();
            return;
        }
        if(!$this->isAlive()){
            if(++$this->deadTicks >= 23) $this->close();
            return;
        }

        if(!$this->knockBackCheck()){
            ++$this->moveTime;
            $target = $this->updateMove();
            if($target instanceof Player){
                $this->attackOption($target);
            }elseif($target instanceof Vector3){
                if($this->distance($target) <= 1) $this->moveTime = 0;
            }
        }
        $this->entityBaseTick();
    }

    public function entityBaseTick($tickDiff = 1){
        Timings::$timerEntityBaseTick->startTiming();

        if(!$this->isCreated()){
            return false;
        }

        $hasUpdate = Entity::entityBaseTick($tickDiff);
        if($this->attackTime > 0){
            $this->attackTime -= $tickDiff;
        }
        if($this->isInsideOfSolid()){
            $hasUpdate = true;
            $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_SUFFOCATION, 1);
            $this->attack($ev->getFinalDamage(), $ev);
        }
        if($this instanceof Enderman){
            if($this->level->getBlock(new Vector3(Math::floorFloat($this->x), (int) $this->y, Math::floorFloat($this->z))) instanceof Water){
                $ev = new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 2);
                $this->attack($ev->getFinalDamage(), $ev);
                $this->move(mt_rand(-20, 20), mt_rand(-20, 20), mt_rand(-20, 20));
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

        Timings::$timerEntityBaseTick->stopTiming();
        return $hasUpdate;
    }

    public function targetOption(Player $player, $distance){
        return parent::targetOption($player) && $player->isSurvival() && $distance <= 81;
    }

}
