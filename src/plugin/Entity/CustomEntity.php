<?php

namespace plugin\Entity;

use plugin\EntityManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Short;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

class CustomEntity extends Monster{

    public $width = 0.72;
    public $height = 1.8;
    public $eyeHeight = 1.62;

    public function __construct(){
        //이 엔티티는 미완성입니다
    }

    public function initEntity(){
        parent::initEntity();

        $this->setMaxHealth(EntityManager::getData("custom.maxhp", 20));
        $this->setDamage(EntityManager::getData("custom.damage", [0, 3, 4, 6]));
        if(!isset($this->namedtag->Health)){
            $this->namedtag->Health = new Short("Health", $this->getMaxHealth());
        }
        $this->setHealth((int) $this->namedtag["Health"]);
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
        //$player->dataPacket($pk);

        //$this->hasSpawned[$player->getId()] = $player;
    }

    public function getName(){
        return EntityManager::getData("custom.name", "CustomEntity");
    }

    public function updateTick(){
        if($this) return;
        if(!$this->isAlive()){
            if(++$this->deadTicks >= 23) $this->close();
            return;
        }

        ++$this->attackDelay;
        if($this->knockBackCheck()) return;

        ++$this->moveTime;
        $target = $this->updateMove();
        if($target instanceof Player){
            if($this->attackDelay >= 16 && $this->distanceSquared($target) <= 1){
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
        $drops = [];
        foreach(EntityManager::getData("custom.drops", []) as $drop){
            $drops[] = Item::get($drop[0], $drop[1], $drop[2]);
        }
        return $drops;
    }
}
