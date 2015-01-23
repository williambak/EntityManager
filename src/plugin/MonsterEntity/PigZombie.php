<?php

namespace plugin\MonsterEntity;

use pocketmine\Player;
use plugin\EntityManager;
use pocketmine\nbt\tag\String;
use pocketmine\nbt\tag\Compound;
use pocketmine\level\format\FullChunk;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class PigZombie extends Monster{
    const NETWORK_ID = 36;

    public $width = 0.7;
    public $length = 0.6;
    public $height = 1.8;

    public function __construct(FullChunk $chunk, Compound $nbt){
        parent::__construct($chunk, $nbt);
        $this->setMaxHealth(22);
        $this->setHealth(22);
    }

    protected function initEntity(){
        $this->namedtag->id = new String("id", "PigZombie");
    }

    public function getName(){
        return "좀비피그맨";
    }

    public function onUpdate($currentTick){
        if($this->dead === true){
            if(++$this->deadTicks == 1){
                foreach($this->hasSpawned as $player){
                    $pk = new EntityEventPacket();
                    $pk->eid = $this->id;
                    $pk->event = 3;
                    $player->dataPacket($pk);
                }
            }
            if($this->deadTicks >= 20){
                $this->close();
                return false;
            }
            $this->updateMovement();
            return true;
        }

        $this->attackDelay++;
        if($this->knockBackCheck()) return true;

        $this->moveTime++;
        $target = $this->getTarget();
        $x = $target->x - $this->x;
        $y = $target->y - $this->y;
        $z = $target->z - $this->z;
        $atn = atan2($z, $x);
        $add = $target instanceof Player ? 0.1 : 0.135;
        $this->move(cos($atn) * $add, sin($atn) * $add);
        $this->setRotation(rad2deg($atn - M_PI_2), rad2deg(-atan2($y, sqrt(pow($x, 2) + pow($z, 2)))));
        if ($target instanceof Player) {
            if($this->attackDelay >= 16 && $this->distance($target) <= 1.14){
                $this->attackDelay = 0;
                $damage = [0, 5, 9, 13];
                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage[EntityManager::core()->getDifficulty()]);
                $target->attack($ev->getFinalDamage(), $ev);
            }
        } else {
            if ($this->distance($target) <= 1) {
                $this->moveTime = 500;
            } elseif ($this->x == $this->lastX or $this->z == $this->lastZ) {
                $this->moveTime += 20;
            }
        }
        $this->entityBaseTick();
        $this->updateMovement();
        return true;
    }

}
