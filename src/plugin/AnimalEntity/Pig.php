<?php

namespace plugin\AnimalEntity;

use pocketmine\entity\Rideable;
use pocketmine\item\Item;
use pocketmine\item\Item as ItemItem;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\String;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class Pig extends Animal implements Rideable{
    const NETWORK_ID = 12;

    public $width = 1.6;
    public $length = 0.8;
    public $height = 1.12;

    public function getName(){
        return "돼지";
    }

    public function initEntity(){
        parent::initEntity();
        $this->setMaxHealth(10);
        $this->namedtag->id = new String("id", "Pig");
        $this->lastTick = microtime(true);
        $this->created = true;
    }

    public function getTarget(){
        if(!$this->isMovement()) return new Vector3();
        if($this->stayTime > 0){
            if($this->stayVec === null or (mt_rand(1, 115) <= 3 and $this->stayTime % 20 === 0)) $this->stayVec = $this->add(mt_rand(-10, 10), mt_rand(-3, 3), mt_rand(-10, 10));
            return $this->stayVec;
        }
        $target = null;
        $nearDistance = PHP_INT_MAX;
        foreach($this->hasSpawned as $p){
            $slot = $p->getInventory()->getItemInHand();
            if(($distance = $this->distanceSquared($p)) <= 36 and $p->spawned and $p->isAlive() and !$p->closed){
                if($distance < $nearDistance && $slot->getID() == Item::CARROT){
                    $target = $p;
                    $nearDistance = $distance;
                    continue;
                }
            }
        }
        if($target === null && $this->stayTime <= 0 && mt_rand(1, 420) === 1){
            $this->stayTime = mt_rand(82, 400);
            return $this->stayVec = $this->add(mt_rand(-10, 10), 0, mt_rand(-10, 10));
        }
        if($target instanceof Player){
            return $target;
        }elseif($this->moveTime >= mt_rand(400, 800) or ($target === null and !$this->target instanceof Vector3)){
            $this->moveTime = 0;
            $this->target = new Vector3($this->x + mt_rand(-100, 100), $this->y, $this->z + mt_rand(-100,100));
        }
        return $this->target;
    }
    public function getDrops() {
    	$drops = [ ];
    	if ($this->lastDamageCause instanceof EntityDamageByEntityEvent) {
    		$drops [] = ItemItem::get ( ItemItem::RAW_PORKCHOP, 0, 1 );
    	}
    	return $drops;
    }
}