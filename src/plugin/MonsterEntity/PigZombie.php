<?php

namespace plugin\MonsterEntity;

use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Int;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;

class PigZombie extends Monster{
    const NETWORK_ID = 36;

    public $width = 0.72;
    public $length = 0.6;
    public $height = 1.8;
    public $eyeHeight = 1.62;

    private $angry = 0;

    public function initEntity(){
        parent::initEntity();

        $this->fireProof = true;
        $this->setMaxHealth(22);
        $this->setDamage([0, 5, 9, 13]);
        $this->lastTick = microtime(true);
        if(!isset($this->namedtag->Angry)){
            $this->namedtag->Angry = new Int("Angry", $this->angry);
        }
        $this->angry = (int) $this->namedtag["Angry"];
        $this->created = true;
    }

    public function saveNBT(){
        $this->namedtag->Angry = new Int("Angry", $this->angry);
        parent::saveNBT();
    }

    public function getName(){
        return "좀비피그맨";
    }

    public function isAngry(){
        return $this->angry > 0;
    }

    public function setAngry($val){
        $this->angry = (int) $val;
    }

    public function updateTick(){
        $tick = (microtime(true) - $this->lastTick) * 20;
        if(!$this->isAlive()){
            $this->deadTicks += $tick;
            if($this->deadTicks >= 25) $this->close();
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
        }
        elseif($target instanceof Vector3){
            if($this->distance($target) <= 1){
                $this->moveTime = 800;
            }
            elseif($this->x == $this->lastX or $this->z == $this->lastZ){
                $this->moveTime += 20;
            }
        }
        $this->entityBaseTick($tick);
        $this->updateMovement();
        $this->lastTick = microtime(true);
    }

    public function getDrops(){
        $drops = [];
        if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
            switch(mt_rand(0, 2)){
                case 0 :
                    $drops [] = Item::get(Item::FLINT, 0, 1);
                    break;
                case 1 :
                    $drops [] = Item::get(Item::GUNPOWDER, 0, 1);
                    break;
                case 2 :
                    $drops [] = Item::get(Item::REDSTONE_DUST, 0, 1);
                    break;
            }
        }
        return $drops;
    }

}
