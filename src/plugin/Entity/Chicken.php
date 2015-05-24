<?php

namespace plugin\Entity;

use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class Chicken extends Animal{
    const NETWORK_ID = 10;

    public $width = 0.4;
    public $height = 0.75;

    public function getName(){
        return "닭";
    }

    public function initEntity(){
        parent::initEntity();

        $this->setMaxHealth(4);
        if(isset($this->namedtag->Health)){
            $this->setHealth((int) $this->namedtag["Health"]);
        }else{
            $this->setHealth($this->getMaxHealth());
        }
        $this->created = true;
    }

    public function getTarget(){
        $target = null;
        $nearDistance = PHP_INT_MAX;
        foreach($this->hasSpawned as $p){
            $slot = $p->getInventory()->getItemInHand();
            if(($distance = $this->distanceSquared($p)) <= 36 and $p->spawned and $p->isAlive() and !$p->closed){
                if($distance < $nearDistance && $slot->getID() == Item::SEEDS){
                    $target = $p;
                    $nearDistance = $distance;
                    continue;
                }
            }
        }
        if($this->stayTime > 0){
            if($target != null){
                $this->stayVec = null;
                $this->stayTime = 0;
            }else{
                if($this->stayVec === null or mt_rand(1, 120) <= 3) $this->stayVec = $this->add(mt_rand(-100, 100), mt_rand(-20, 20) / 10, mt_rand(-100, 100));
                return $this->stayVec;
            }
        }
        if($target === null && $this->stayTime <= 0 && mt_rand(1, 420) === 1){
            $this->stayTime = mt_rand(82, 400);
            return $this->stayVec = $this->add(mt_rand(-100, 100), mt_rand(-20, 20) / 10, mt_rand(-100, 100));
        }
        if($target instanceof Player){
            return $target;
        }elseif($this->moveTime >= mt_rand(400, 800) or ($target === null and !$this->target instanceof Vector3)){
            $this->moveTime = 0;
            $this->target = $this->add(mt_rand(-100, 100), 0, mt_rand(-100, 100));
        }
        return $this->target;
    }

    public function getDrops(){
        $drops = [];
        if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
            switch(mt_rand(0, 2)){
                case 0 :
                    $drops[] = Item::get(Item::RAW_CHICKEN, 0, 1);
                    break;
                case 1 :
                    $drops[] = Item::get(Item::EGG, 0, 1);
                    break;
                case 2 :
                    $drops[] = Item::get(Item::FEATHER, 0, 1);
                    break;
            }
        }
        return $drops;
    }

}