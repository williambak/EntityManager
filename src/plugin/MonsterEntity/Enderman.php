<?php

namespace plugin\MonsterEntity;

use plugin\EntityManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\String;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;

class Enderman extends Monster{
    const NETWORK_ID = 38;

    public $width = 0.7;
    public $height = 2.8;

    protected function initEntity(){
        $this->namedtag->id = new String("id", "Enderman");
    }

    public function getName(){
        return "엔더맨";
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
            if($this->deadTicks >= 23){
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
        $this->move(cos($atn) * 0.1, sin($atn) * 0.1);
        $this->setRotation(rad2deg($atn - M_PI_2), rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2))));
        if($target instanceof Player){
            if($this->attackDelay >= 16 && $this->distance($target) <= 0.8){
                $this->attackDelay = 0;
                $damage = [0, 3, 4, 6];
                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage[EntityManager::core()->getDifficulty()]);
                $target->attack($ev->getFinalDamage(), $ev);
            }
        }else{
            if($this->distance($target) <= 1){
                $this->moveTime = 800;
            }elseif($this->x == $this->lastX or $this->z == $this->lastZ){
                $this->moveTime += 20;
            }
        }
        $this->entityBaseTick();
        $this->updateMovement();
        return true;
    }

    public function getDrops(){
        $drops = [
            Item::get(Item::FEATHER, 0, 1)
        ];
        if($this->lastDamageCause instanceof EntityDamageByEntityEvent and $this->lastDamageCause->getEntity() instanceof Player){
            if(mt_rand(0, 199) < 5){
                switch(mt_rand(0, 2)){
                    case 0:
                        $drops[] = Item::get(Item::IRON_INGOT, 0, 1);
                        break;
                    case 1:
                        $drops[] = Item::get(Item::CARROT, 0, 1);
                        break;
                    case 2:
                        $drops[] = Item::get(Item::POTATO, 0, 1);
                        break;
                }
            }
        }

        return $drops;
    }
}
