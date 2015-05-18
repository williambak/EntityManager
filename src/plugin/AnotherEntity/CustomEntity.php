<?php

namespace plugin\MonsterEntity;

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
    //아직 쓸게 못됨

    public $width = 0.72;
    public $height = 1.8;
    public $eyeHeight = 1.62;

    public function initEntity(){
        parent::initEntity();

        $this->setMaxHealth(EntityManager::getData("custom.maxhp", 20));
        $this->setDamage(EntityManager::getData("custom.damage", [0, 3, 4, 6]));
        if(!isset($this->namedtag->Health)){
            $this->namedtag->Health = new Short("Health", $this->getMaxHealth());
        }
        $this->setHealth((int) $this->namedtag["Health"]);
        $this->lastTick = microtime(true);
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

    public function updateTick(){
        $tick = (microtime(true) - $this->lastTick) * 20;
        if(!$this->isAlive()){
            $this->deadTicks += $tick;
            if($this->deadTicks >= 25) $this->close();
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
        $drops = [];
        if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
            switch(mt_rand(0, 2)){
                case 0 :
                    $drops [] = Item::get(Item::FEATHER, 0, 1);
                    break;
                case 1 :
                    $drops [] = Item::get(Item::CARROT, 0, 1);
                    break;
                case 2 :
                    $drops [] = Item::get(Item::POTATO, 0, 1);
                    break;
            }
        }
        return $drops;
    }
}
