<?php

namespace plugin\MonsterEntity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Short;
use pocketmine\nbt\tag\String;
use pocketmine\Player;

class Spider extends Monster{
    const NETWORK_ID = 35;

    public $width = 1.5;
    public $length = 0.8;
    public $height = 1.12;

    protected function initEntity(){
        parent::initEntity();

        $this->setMaxHealth(16);
        $this->setDamage([0, 2, 2, 3]);
        $this->lastTick = microtime(true);
        if(!isset($this->namedtag->id)){
            $this->namedtag->id = new String("id", "Enderman");
        }
        if(!isset($this->namedtag->Health)){
            $this->namedtag->Health = new Short("Health", $this->getMaxHealth());
        }
        $this->setHealth($this->namedtag["Health"]);
        $this->created = true;
    }

    public function getName(){
        return "거미";
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
        $target = $this->updateMove($tick);
        if($target instanceof Player){
            if($this->attackDelay >= 16 && $this->distance($target) <= 1.1){
                $this->attackDelay = 0;
                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getDamage()[$this->server->getDifficulty()]);
                $target->attack($ev->getFinalDamage(), $ev);
            }
        }elseif($target instanceof Vector3){
            if($this->distance($target) <= 1){
                $this->moveTime = 800;
            }elseif($this->x === $this->lastX or $this->z === $this->lastZ){
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
            return [
                Item::get(Item::STRING, 0, mt_rand(0, 3))
            ];
        }
        return [];
    }

}
