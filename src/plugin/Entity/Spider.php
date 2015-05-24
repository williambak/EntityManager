<?php

namespace plugin\Entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;

class Spider extends Monster{
    const NETWORK_ID = 35;

    public $width = 1.5;
    public $height = 1.2;

    public function initEntity(){
        parent::initEntity();

        $this->setMaxHealth(16);
        if(isset($this->namedtag->Health)){
            $this->setHealth((int) $this->namedtag["Health"]);
        }else{
            $this->setHealth($this->getMaxHealth());
        }
        $this->setDamage([0, 2, 2, 3]);
        $this->created = true;
    }

    public function getName(){
        return "거미";
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

        ++$this->attackDelay;
        if($this->knockBackCheck()) return;

        ++$this->moveTime;
        $target = $this->updateMove();
        if($target instanceof Player){
            if($this->attackDelay >= 16 && $this->distance($target) <= 1.1){
                $this->attackDelay = 0;
                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getDamage()[$this->server->getDifficulty()]);
                $target->attack($ev->getFinalDamage(), $ev);
            }
        }elseif($target instanceof Vector3){
            if($this->distance($target) <= 1) $this->moveTime = 800;
        }
        $this->entityBaseTick();
        $this->updateMovement();
    }

    public function getDrops(){
        return $this->lastDamageCause instanceof EntityDamageByEntityEvent ? [Item::get(Item::STRING, 0, mt_rand(0, 3))] : [];
    }

}
