<?php

namespace plugin\Entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
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

    public function attackOption(Player $player){
        if($this->distanceSquared($player) <= 0.81){
            $ev = new EntityDamageByEntityEvent($this, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getDamage());
            $player->attack($ev->getFinalDamage(), $ev);
        }
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
