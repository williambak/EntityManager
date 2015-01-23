<?php

namespace plugin\AnimalEntity;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\AddMobPacket;
use pocketmine\Player;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\entity\Animal as AnimalEntity;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Server;

abstract class Animal extends AnimalEntity{

    /** @var Vector3 */
    public $target = null;
    public $moveTime = 0;

    /** @var Entity|null */
    protected $attacker = null;
    private $entityMovement = true;

    public function spawnTo(Player $player){
        $pk = new AddMobPacket();
        $pk->eid = $this->getID();
        $pk->type = static::NETWORK_ID;
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->metadata = $this->getData();
        $player->dataPacket($pk);

        parent::spawnTo($player);
    }

    public function updateMovement(){
        $this->lastX = $this->x;
        $this->lastY = $this->y;
        $this->lastZ = $this->z;

        $this->lastYaw = $this->yaw;
        $this->lastPitch = $this->pitch;

        $pk = new MovePlayerPacket();
        $pk->eid = $this->id;
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->bodyYaw = $this->yaw;

        Server::broadcastPacket($this->getViewers(), $pk);
    }


    public function isMovement(){
        return $this->entityMovement;
    }

    public function setMovement($value){
        $this->entityMovement = (bool) $value;
    }

    public function knockBackCheck(){
        if (!$this->attacker instanceof Entity) return false;

        $this->moveTime--;
        $target = $this->attacker;

        $x = $target->x - $this->x;
        $y = $target->y - $this->y;
        $z = $target->z - $this->z;
        $atn = atan2($z, $x);
        if($this->moveTime >= 3){
            $this->move(cos($atn) * -0.64, 0.58, sin($atn) * -0.62);
        }else{
            $this->move(cos($atn) * -0.64, -0.46, sin($atn) * -0.62);
        }
        $this->setRotation(rad2deg(atan2($z, $x) - M_PI_2), rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2))));

        $this->entityBaseTick();
        $this->updateMovement();
        if($this->moveTime <= 0) $this->attacker = null;
        return true;
    }

    public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
        if($this->attacker instanceof Entity) return;
        parent::attack($damage, $source);
        if($source instanceof EntityDamageByEntityEvent and !$source->isCancelled()){
            $this->moveTime = 6;
            $this->attacker = $source->getDamager();
        }
    }

    public function getCollisionCubes(AxisAlignedBB $bb){
        $minX = Math::floorFloat($bb->minX);
        $minY = Math::floorFloat($bb->minY);
        $minZ = Math::floorFloat($bb->minZ);
        $maxX = Math::floorFloat($bb->maxX + 1);
        $maxY = Math::floorFloat($bb->maxY + 1);
        $maxZ = Math::floorFloat($bb->maxZ + 1);

        $all = [];
        $blocks = [];

        for($z = $minZ; $z < $maxZ; ++$z){
            for($x = $minX; $x < $maxX; ++$x){
                for($y = $minY - 1; $y < $maxY; ++$y){
                    $block = $this->level->getBlock(new Vector3($x, $y, $z));
                    if($block->getId() > 0){
                        $blocks[] = $block;
                        $block->collidesWithBB($bb, $all);
                    }
                }
            }
        }

        foreach($this->level->getCollidingEntities($bb->grow(0.25, 0.25, 0.25), $this) as $ent){
            $all[] = clone $ent->boundingBox;
        }

        return [
            "all" => $all,
            "block" => $blocks,
        ];
    }

    public function move($dx, $dz, $dy = 0){
        if($this->isMovement() === false) return;
        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;
        if($this->keepMovement === false){
            /** @var AxisAlignedBB[] $list */
            $lists = $this->getCollisionCubes($this->boundingBox->addCoord($dx, 0, $dz));
            $list = $lists["all"];
            foreach($list as $bb){
                $dx = $bb->calculateXOffset($this->boundingBox, $dx);
            }
            $this->boundingBox->offset($dx, 0, 0);
            foreach($list as $bb){
                $dz = $bb->calculateZOffset($this->boundingBox, $dz);
            }
            $this->boundingBox->offset(0, 0, $dz);
            /*foreach($lists["block"] as $block){
                // @var Block $block
                $blbb = $block->getBoundingBox();
                if($blbb !== null and $blbb->maxY - $blbb->minY <= 1) $dy += 0.32;
            }*/
            $this->setComponents($this->x + $dx, $this->y, $this->z + $dz);
        }else{
            $this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);
        }
        $this->onGround = ($movY !== $dy and $dy < 0);
        $this->updateFallState($dy, $this->onGround);
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
            if($this->distance($target) <= 2){
                $this->pitch = 22;
                $this->x = $this->lastX;
                $this->y = $this->lastY;
                $this->z = $this->lastZ;
            }
        }else{
            if($this->distance($target) <= 1){
                $this->moveTime = 800;
            }else if($this->x === $this->lastX or $this->z === $this->lastZ){
                $this->moveTime += 50;
            }
        }

        $this->entityBaseTick();
        $this->updateMovement();
        return true;
    }

    /**
     * @return Player|Vector3
     */
    public abstract function getTarget();
    
    public function getData(){
        $flags = 0;
        $flags |= $this->fireTicks > 0 ? 1 : 0;

        return [
            0 => ["type" => 0, "value" => $flags],
            1 => ["type" => 1, "value" => $this->airTicks],
            16 => ["type" => 0, "value" => 0],
            17 => ["type" => 6, "value" => [0, 0, 0]],
        ];
    }
}
