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
    public $length = 0.4;
    public $height = 1.8;
    public $eyeHeight = 1.62;

    public function initEntity(){
        parent::initEntity();

        if(isset($this->namedtag->Health)){
            $this->setHealth((int) $this->namedtag["Health"]);
        }else{
            $this->setHealth($this->getMaxHealth());
        }
        $this->setDamage([0, 3, 4, 6]);
        $this->created = true;
    }

    public function getName(){
        return "좀비";
    }

    public function updateTick(){
        if(!$this->isAlive()){
            if(++$this->deadTicks >= 23) $this->close();
            return;
        }

        ++$this->attackDelay;
        if($this->knockBackCheck()) return;

        ++$this->moveTime;
        $target = $this->updateMove();
        if($target instanceof Player){
            if($this->attackDelay >= 16 && $this->distanceSquared($target) <= 0.81){
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