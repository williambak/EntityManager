<?php

namespace plugin\Entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\item\Item;

class Enderman extends Monster{
    const NETWORK_ID = 38;

    public $width = 0.7;
    public $height = 2.8;
    public $eyeHeight = 2.62;

    public function initEntity(){
        if(isset($this->namedtag->Health)){
            $this->setHealth((int) $this->namedtag["Health"]);
        }else{
            $this->setHealth($this->getMaxHealth());
        }
        $this->setDamage([0, 1, 2, 3]);
        parent::initEntity();
        $this->created = true;
    }

    public function getName(){
        return "엔더맨";
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
            ++$this->attackDelay;
            $target = $this->updateMove();
            if($target instanceof Player){
                if($this->attackDelay >= 16 && $this->distance($target) <= 1){
                    $this->attackDelay = 0;
                    $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getDamage());
                    $target->attack($ev->getFinalDamage(), $ev);
                }
            }elseif($target instanceof Vector3){
                if($this->distance($target) <= 1) $this->moveTime = 800;
            }
        }
        $this->entityBaseTick();
        $this->updateMovement();
    }

    public function getDrops(){
        $drops = [];
        if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
            $drops[] = Item::get(Item::END_STONE, 0, 1);
        }
        return [];
    }

}
