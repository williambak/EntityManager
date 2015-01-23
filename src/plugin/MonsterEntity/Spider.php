<?php

namespace plugin\MonsterEntity;

use plugin\EntityManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\String;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;

class Spider extends Monster{
    const NETWORK_ID = 35;

    public $width = 1.5;
    public $length = 0.8;
    public $height = 1.12;

    public function __construct(FullChunk $chunk, Compound $nbt){
        parent::__construct($chunk, $nbt);
        $this->setMaxHealth(16);
    }

    protected function initEntity(){
        $this->namedtag->id = new String("id", "Spider");
    }

    public function getName(){
        return "거미";
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
				//$this->despawnFromAll();
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
        $this->move(cos($atn) * 0.1, sin($atn) * 0.1);
        $this->setRotation(rad2deg($atn - M_PI_2), rad2deg(-atan2($y, sqrt(pow($x, 2) + pow($z, 2)))));
        if ($target instanceof Player) {
            if($this->attackDelay >= 16 && $this->distance($target) <= 1.1){
                $this->attackDelay = 0;
                $damage = [0, 2, 2, 3];
                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage[EntityManager::core()->getDifficulty()]);
                $target->attack($ev->getFinalDamage(), $ev);
            }
        } else {
            if ($this->distance($target) <= 1) {
                $this->moveTime = 800;
            } elseif ($this->x === $this->lastX or $this->z === $this->lastZ) {
                $this->moveTime += 20;
            }
        }

        $this->entityBaseTick();
        $this->updateMovement();
        return true;
    }

}
