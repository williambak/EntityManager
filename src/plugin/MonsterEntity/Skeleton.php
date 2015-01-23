<?php

namespace plugin\MonsterEntity;

use pocketmine\entity\Entity;
use pocketmine\entity\ProjectileSource;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\String;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;

class Skeleton extends Monster implements ProjectileSource{
    const NETWORK_ID = 34;

    public $width = 0.58;
    public $length = 0.6;
    public $height = 1.8;

    private $accuracy = 70;

    protected function initEntity(){
        $this->namedtag->id = new String("id", "Skeleton");
    }

    public function getName(){
        return "스켈레톤";
    }

    public function setAccuracy($accuracy){
        if(!is_numeric($accuracy) or $accuracy <= 0) $this->accuracy = 70;
        else $this->accuracy = 900 - 900 * $accuracy <= 0 ? 1 : 900 - 900 * $accuracy;
    }

    public function getAccuracy(){
        return (1 - $this->accuracy / 900) * 100;
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
            if($this->attackDelay >= 16 && $this->distance($target) <= 6.5 and mt_rand(1,24) === 1){
                $this->attackDelay = 0;
                $f = 1.5;
                $ywper = $this->accuracy * 2;
                $ptper = $this->accuracy / 2;
                $yaw = $this->yaw + mt_rand(-$ywper, $ywper) / 10;
                $pitch = $this->pitch + mt_rand(-$ptper, $ptper) / 10;
                $nbt = new Compound("", [
                    "Pos" => new Enum("Pos", [
                        new Double("", $this->x),
                        new Double("", $this->y + 1.62),
                        new Double("", $this->z)
                    ]),
                    "Motion" => new Enum("Motion", [
                        new Double("", -sin($yaw / 180 * M_PI) * cos($pitch / 180 * M_PI) * $f),
                        new Double("", -sin($pitch / 180 * M_PI) * $f),
                        new Double("", cos($yaw / 180 * M_PI) * cos($pitch / 180 * M_PI) * $f)
                    ]),
                    "Rotation" => new Enum("Rotation", [
                        new Float("", $yaw),
                        new Float("", $pitch)
                    ]),
                ]);
                /** @var \pocketmine\entity\Arrow $arrow */
                $arrow = Entity::createEntity("Arrow", $this->chunk, $nbt, $this);

                $ev = new EntityShootBowEvent($this, Item::get(Item::ARROW, 0, 1), $arrow, $f);

                $this->server->getPluginManager()->callEvent($ev);
                if($ev->isCancelled()){
                    $arrow->kill();
                }else{
                    $arrow->spawnToAll();
                }
            }
        }else{
            if($this->distance($target) <= 1) $this->moveTime = 800;
        }

        $this->entityBaseTick();
        $this->updateMovement();
        return true;
    }

}
