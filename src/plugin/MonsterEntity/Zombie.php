<?php

namespace plugin\MonsterEntity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\String;
use pocketmine\Player;

class Zombie extends Monster{
    const NETWORK_ID = 32;

    public $width = 0.8;
    public $length = 0.4;
    public $height = 1.8;
    public $eyeHeight = 1.62;

    protected function initEntity(){
        parent::initEntity();
        $this->setDamage([0, 3, 4, 6]);
        $this->namedtag->id = new String("id", "Zombie");
        $this->lastTick = microtime(true);
        $this->created = true;
    }

    public function getName(){
        return "좀비";
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
        $target = $this->updateMove($tick);
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
        $this->entityBaseTick($tick);
        $this->updateMovement();
        $this->lastTick = microtime(true);
    }

    public function getDrops(){
        $cause = $this->lastDamageCause;
        if($cause instanceof EntityDamageByEntityEvent and $cause->getEntity() instanceof Player){
            $drops = [Item::get(Item::FEATHER, 0, 1)];
            if(mt_rand(1, 200) >= 6) return $drops;
            if(mt_rand(0, 1) === 0){
                $drops[] = Item::get(Item::CARROT, 0, 1);
            }else{
                $drops[] = Item::get(Item::POTATO, 0, 1);
            }
            return $drops;
        }
        return [];
    }
}
