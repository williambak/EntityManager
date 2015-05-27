<?php

namespace plugin\Entity;

use pocketmine\entity\Ageable;
use pocketmine\math\Vector3;
use pocketmine\Player;

abstract class Animal extends BaseEntity implements Ageable{

    public function initEntity(){
        if($this->getDataProperty(self::DATA_AGEABLE_FLAGS) === null){
            $this->setDataProperty(self::DATA_AGEABLE_FLAGS, self::DATA_TYPE_BYTE, 0);
        }
        parent::initEntity();
    }

    public function isBaby(){
        return $this->getDataFlag(self::DATA_AGEABLE_FLAGS, self::DATA_FLAG_BABY);
    }

    public function updateMove(){
        $target = null;
        if($this->isMovement()){
            $target = $this->getTarget();
            $x = $target->x - $this->x;
            $y = $target->y - $this->y;
            $z = $target->z - $this->z;
            $atn = atan2($z, $x);
            if($this->stayTime > 0){
                $this->move(0, 0);
                if(--$this->stayTime <= 0) $this->stayVec = null;
            }else{
                $this->move(cos($atn) * 0.07, sin($atn) * 0.07);
            }
            $this->yaw = rad2deg($atn - M_PI_2);
            $this->pitch = rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2)));
        }else{
            $this->move(0, 0);
        }
        return $target;
    }

    public function updateTick(){
        if(!$this->isAlive()){
            if(++$this->deadTicks >= 23) $this->close();
            return;
        }

        if(!$this->knockBackCheck()){
            ++$this->moveTime;
            $target = $this->updateMove();
            if($target instanceof Player){
                if($this->distance($target) <= 2){
                    $this->pitch = 22;
                    $this->x = $this->lastX;
                    $this->y = $this->lastY;
                    $this->z = $this->lastZ;
                }
            }elseif($target instanceof Vector3){
                if($this->distance($target) <= 1) $this->moveTime = 800;
            }
        }
        $this->entityBaseTick();
        $this->updateMovement();
    }

}
