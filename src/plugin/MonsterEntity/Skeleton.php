<?php

namespace plugin\MonsterEntity;

use pocketmine\entity\Entity;
use pocketmine\entity\ProjectileSource;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\Item;
use pocketmine\level\sound\LaunchSound;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\String;
use pocketmine\Player;

class Skeleton extends Monster implements ProjectileSource{
    const NETWORK_ID = 34;

    public $width = 0.58;
    public $length = 0.6;
    public $height = 1.8;

    protected function initEntity(){
        parent::initEntity();
        $this->namedtag->id = new String("id", "Skeleton");
        $this->lastTick = microtime(true);
        $this->created = true;
    }

    public function getName(){
        return "스켈레톤";
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
            if($this->attackDelay >= 16 && $this->distance($target) <= 7 and mt_rand(1,25) === 1){
                $this->attackDelay = 0;
                $f = 1.5;
                $yaw = $this->yaw + mt_rand(-180, 180) / 10;
                $pitch = $this->pitch + mt_rand(-90, 90) / 10;
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

                $ev = new EntityShootBowEvent($this, Item::get(Item::ARROW, 0, 1), Entity::createEntity("Arrow", $this->chunk, $nbt, $this), $f);

                $this->server->getPluginManager()->callEvent($ev);
                if($ev->isCancelled()){
                    $ev->getProjectile()->kill();
                }else{
                    $this->server->getPluginManager()->callEvent($launch = new ProjectileLaunchEvent($ev->getProjectile()));
                    if($launch->isCancelled()){
                        $launch->getEntity()->kill();
                    }else{
                        $launch->getEntity()->spawnToAll();
                        $this->level->addSound(new LaunchSound($this), $this->getViewers());
                    }
                }
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
            return [
                Item::get(Item::BONE, 0, mt_rand(0, 2)),
                Item::get(Item::ARROW, 0, mt_rand(0, 3)),
            ];
        }
        return [];
    }

}
