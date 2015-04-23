<?php

namespace plugin\MonsterEntity;

use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\nbt\tag\String;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class PigZombie extends Monster{
    const NETWORK_ID = 36;

    public $width = 0.7;
    public $length = 0.6;
    public $height = 1.8;
    private $angry = 0;

    protected function initEntity(){
        parent::initEntity();
        $this->setMaxHealth(22);
        $this->setHealth(22);
        $this->setDamage([0, 5, 9, 13]);
        $this->namedtag->id = new String("id", "PigZombie");
        $this->lastTick = microtime(true);
        $this->created = true;
    }

    public function getName(){
        return "좀비피그맨";
    }

    public function isAngry(){
        return (int) $this->angry > 0;
    }

    public function setAngry($val){
        $this->angry = (int) $val;
    }

    public function updateTick(){
        $tick = (microtime(true) - $this->lastTick) * 20;
        if($this->dead === true){
            $this->knockBackCheck($tick);
            if(++$this->deadTicks >= 25) $this->close();
            return;
        }

        $this->attackDelay += $tick;
        if($this->knockBackCheck($tick)) return;

        $this->moveTime += $tick;
        if($this->angry > 0) $this->angry -= min($tick, $this->angry);
        $target = $this->updateMove($tick);
        if($target instanceof Player){
            if($this->attackDelay >= 16 && $this->distance($target) <= 1.18){
                $this->attackDelay = 0;
                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getDamage()[$this->server->getDifficulty()]);
                $target->attack($ev->getFinalDamage(), $ev);
            }
        }elseif($target instanceof Vector3){
            if($this->distance($target) <= 1){
                $this->moveTime = 800;
            }elseif($this->x == $this->lastX or $this->z == $this->lastZ){
                $this->moveTime += 20;
            }
        }
        $this->entityBaseTick($tick);
        $this->updateMovement();
        $this->lastTick = microtime(true);
    }

    public function getDrops(){
        $cause = $this->lastDamageCause;
        if($cause instanceof EntityDamageByEntityEvent and $cause->getEntity() instanceof Player){
            $drops = [];
            return $drops;
        }
        return [];
    }

}
