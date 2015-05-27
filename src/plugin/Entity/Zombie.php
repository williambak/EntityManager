<?php

namespace plugin\Entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;

class Zombie extends Monster{
    const NETWORK_ID = 32;

    public $width = 0.72;
    public $height = 1.8;

    public function initEntity(){
        if(isset($this->namedtag->Health)){
            $this->setHealth((int) $this->namedtag["Health"]);
        }else{
            $this->setHealth($this->getMaxHealth());
        }
        $this->setDamage([0, 3, 4, 6]);
        parent::initEntity();
        $this->created = true;
    }

    public function getName(){
        return "좀비";
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
                if($this->attackDelay >= 16 && $this->distanceSquared($target) <= 0.81){
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
            switch(mt_rand(0, 2)){
                case 0 :
                    $drops[] = Item::get(Item::FEATHER, 0, 1);
                    break;
                case 1 :
                    $drops[] = Item::get(Item::CARROT, 0, 1);
                    break;
                case 2 :
                    $drops[] = Item::get(Item::POTATO, 0, 1);
                    break;
            }
        }
        return $drops;
    }

}
