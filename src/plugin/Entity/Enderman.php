<?php

namespace plugin\Entity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
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

    public function attackOption(Player $player){
        if($this->distance($player) <= 1){
            $ev = new EntityDamageByEntityEvent($this, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getDamage());
            $player->attack($ev->getFinalDamage(), $ev);
        }
    }

    public function getDrops(){
        $drops = [];
        if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
            $drops[] = Item::get(Item::END_STONE, 0, 1);
        }
        return [];
    }

}
