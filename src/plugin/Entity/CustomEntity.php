<?php

namespace plugin\Entity;

use plugin\EntityManager;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\nbt\tag\Short;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

class CustomEntity extends Monster{

    public $width = 0.72;
    public $height = 1.8;
    public $eyeHeight = 1.62;

    public function initEntity(){
        $this->setMaxHealth(EntityManager::getData("custom.maxhp", 20));
        $this->setDamage(EntityManager::getData("custom.damage", [0, 3, 4, 6]));
        if(!isset($this->namedtag->Health)){
            $this->namedtag->Health = new Short("Health", $this->getMaxHealth());
        }
        $this->setHealth((int) $this->namedtag["Health"]);
        parent::initEntity();
        $this->created = true;
    }

    public function spawnTo(Player $player){
        if(isset($this->hasSpawned[$player->getId()]) or !isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) return;

        $pk = new AddEntityPacket();
        $pk->eid = $this->getID();
        $pk->type = EntityManager::getData("custom.type", 32);
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->speedX = 0;
        $pk->speedY = 0;
        $pk->speedZ = 0;
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->metadata = $this->dataProperties;
        $player->dataPacket($pk);

        $this->hasSpawned[$player->getId()] = $player;
    }

    public function getName(){
        return EntityManager::getData("custom.name", "CustomEntity");
    }

    public function attackOption(Player $player){

    }

    public function getDrops(){
        $drops = [];
        foreach(EntityManager::getData("custom.drops", []) as $drop){
            $drops[] = Item::get($drop[0], $drop[1], $drop[2]);
        }
        return $drops;
    }
}
