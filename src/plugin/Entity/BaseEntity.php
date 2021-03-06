<?php

namespace plugin\Entity;

use pocketmine\block\Block;
use pocketmine\entity\Creature;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Timings;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Byte;
use pocketmine\network\Network;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;
use pocketmine\Server;

abstract class BaseEntity extends Creature{

    /** @var Vector3 */
    public $stayVec = null;
    public $stayTime = 0;

    private $target = null;
    private $movement = true;

    protected $moveTime = 0;
    protected $created = false;

    protected $subTarget = null;
    protected $baseTarget = null;

    protected $attacker = null;

    public function __destruct(){

    }

    public function onUpdate($currentTick){
        return false;
    }

    public abstract function updateTick();

    /**
     * @param Player $player
     * @param float $distance
     *
     * @return bool
     */
    public abstract function targetOption(Player $player, $distance);

    /**
     * @return Player|Vector3
     */
    public final function getTarget(){
        if($this->target != null){
            if($this->moveTime > 0){
                return $this->target;
            }else{
                $this->target = null;
            }
        }
        $target = null;
        $nearDistance = PHP_INT_MAX;
        foreach($this->getViewers() as $player){
            if($this->targetOption($player, $distance = $this->distanceSquared($player)) && $distance < $nearDistance){
                $target = $player;
                $nearDistance = $distance;
            }
        }
        if($target == null){
            if($this->stayTime > 0){
                if($this->stayVec == null or mt_rand(1, 100) <= 3){
                    $x = mt_rand(25, 80);
                    $z = mt_rand(25, 80);
                    return $this->stayVec = $this->add(mt_rand(0, 1) ? $x : -$x, mt_rand(-20, 20) / 10, mt_rand(0, 1) ? $z : -$z);
                }
                return $this->stayVec;
            }elseif(mt_rand(1, 350) === 1){
                $this->stayTime = mt_rand(100, 450);
                $x = mt_rand(25, 80);
                $z = mt_rand(25, 80);
                return $this->stayVec = $this->add(mt_rand(0, 1) ? $x : -$x, mt_rand(-20, 20) / 10, mt_rand(0, 1) ? $z : -$z);
            }elseif($this->moveTime <= 0){
                $this->moveTime = mt_rand(100, 1000);
                $x = mt_rand(25, 80);
                $z = mt_rand(25, 80);
                return $this->baseTarget = $this->add(mt_rand(0, 1) ? $x : -$x, 0, mt_rand(0, 1) ? $z : -$z);
            }
        }elseif(!$this instanceof PigZombie || ($this instanceof PigZombie && $this->isAngry())){
            return $target;
        }
        if(!$this->baseTarget instanceof Vector3){
            $this->moveTime = mt_rand(100, 1000);
            $x = mt_rand(25, 80);
            $z = mt_rand(25, 80);
            $this->baseTarget = $this->add(mt_rand(0, 1) ? $x : -$x, 0, mt_rand(0, 1) ? $z : -$z);
        }
        return $this->baseTarget;
    }

    public function setTarget(Vector3 $target, $time = 1000){
        $this->target = $target;
        $this->moveTime = $time;
    }

    public function getSaveId(){
        $class = new \ReflectionClass(static::class);
        return $class->getShortName();
    }

    public function isCreated(){
        return $this->created;
    }

    public function isMovement(){
        return $this->movement;
    }

    public function setMovement($value){
        $this->movement = (bool) $value;
    }

    public function initEntity(){
        if(isset($this->namedtag->Movement)){
            $this->setMovement($this->namedtag["Movement"]);
        }
        $this->setDataProperty(self::DATA_NO_AI, self::DATA_TYPE_BYTE, 1);
        Entity::initEntity();
    }

    public function saveNBT(){
        $this->namedtag->Movement = new Byte("Movement", $this->isMovement());
        parent::saveNBT();
    }

    public function spawnTo(Player $player){
        if(isset($this->hasSpawned[$player->getLoaderId()]) or !isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) return;

        $pk = new AddEntityPacket();
        $pk->eid = $this->getID();
        $pk->type = static::NETWORK_ID;
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

    public function updateMovement(){
        if($this->lastX != $this->x) $this->lastX = $this->x;
        if($this->lastY != $this->y) $this->lastY = $this->y;
        if($this->lastZ != $this->z) $this->lastZ = $this->z;
        if($this->lastYaw != $this->yaw) $this->lastYaw = $this->yaw;
        if($this->lastPitch != $this->pitch) $this->lastPitch = $this->pitch;

        $this->level->addEntityMovement($this->chunk->getX(), $this->chunk->getZ(), $this->id, $this->x, $this->y, $this->z, $this->yaw, $this->pitch);
    }

    public function attack($damage, EntityDamageEvent $source){
        if($this->attacker instanceof Entity) return;
        if($this->attackTime > 0 or $this->noDamageTicks > 0){
            $lastCause = $this->getLastDamageCause();
            if($lastCause !== null and $lastCause->getDamage() >= $damage){
                $source->setCancelled();
            }
        }

        Entity::attack($damage, $source);

        if($source->isCancelled()) return;

        $this->attackTime = 10;
        if($source instanceof EntityDamageByEntityEvent){
            $this->stayTime = 0;
            $this->stayVec = null;
            $this->moveTime = 100;
            $this->attacker = $source->getDamager();
            if($this instanceof PigZombie) $this->setAngry(1000);
        }
        $pk = new EntityEventPacket();
        $pk->eid = $this->getId();
        $pk->event = $this->isAlive() ? 2 : 3;
        Server::broadcastPacket($this->hasSpawned, $pk->setChannel(Network::CHANNEL_WORLD_EVENTS));
    }

    /**
     * @param AxisAlignedBB $bb
     *
     * @return Block[]|Entity[]
     */
    public function getCollisionCubes(AxisAlignedBB $bb){
        $minX = Math::floorFloat($bb->minX);
        $minY = Math::floorFloat($bb->minY);
        $minZ = Math::floorFloat($bb->minZ);
        $maxX = Math::ceilFloat($bb->maxX);
        $maxY = Math::ceilFloat($bb->maxY);
        $maxZ = Math::ceilFloat($bb->maxZ);

        $collides = [];
        $v = new Vector3();
        for($v->z = $minZ; $v->z <= $maxZ; ++$v->z){
            for($v->x = $minX; $v->x <= $maxX; ++$v->x){
                for($v->y = $minY - 1; $v->y <= $maxY; ++$v->y){
                    $block = $this->level->getBlock($v);
                    if($block->getBoundingBox() !== null) $collides[] = $block;
                }
            }
        }
        return $collides;
    }

    public function move($dx, $dz, $dy = 0){
        Timings::$entityMoveTimer->startTiming();
        if($dy == 0 && !$this->onGround && $this->motionY != 0){
            $dy = $this->motionY;
        }
        $isJump = false;
        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;
        $list = $this->getCollisionCubes($this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz));
        foreach($list as $target){
            $bb = $target->getBoundingBox();
            if(
                $movY == 0
                && $target instanceof Block
                && $this->boundingBox->minY >= $bb->minY
                && $this->boundingBox->minY < $bb->maxY
                && (($up = $target->getSide(Vector3::SIDE_UP)->getBoundingBox()) == null || $up->maxY - $this->boundingBox->minY <= 1)
                && $target->distanceSquared($target->add(0.5, 0.5, 0.5)) <= 1
            ){
                $isJump = true;
                $dy = $movY = 0.25;
                $this->motionY = 0;
            }
            if(
                $this->boundingBox->maxX > $bb->minX
                and $this->boundingBox->minX < $bb->maxX
                and $this->boundingBox->maxZ > $bb->minZ
                and $this->boundingBox->minZ < $bb->maxZ
            ){
                if($this->boundingBox->maxY + $dy >= $bb->minY and $this->boundingBox->maxY <= $bb->minY){
                    if(($y1 = $bb->minY - ($this->boundingBox->maxY + $dy)) < 0) $dy += $y1;
                }
                if($this->boundingBox->minY + $dy <= $bb->maxY and $this->boundingBox->minY >= $bb->maxY){
                    if(($y1 = $bb->maxY - ($this->boundingBox->minY + $dy)) > 0) $dy += $y1;
                }
            }
            if($this->boundingBox->maxY + $dy > $bb->minY and $this->boundingBox->minY + $dy < $bb->maxY){
                if($this->boundingBox->maxZ > $bb->minZ && $this->boundingBox->minZ < $bb->maxZ){
                    if($this->boundingBox->maxX + $dx >= $bb->minX and $this->boundingBox->maxX <= $bb->minX){
                        if(($x1 = $bb->minX - ($this->boundingBox->maxX + $dx)) < 0) $dx += $x1;
                    }
                    if($this->boundingBox->minX + $dx <= $bb->maxX and $this->boundingBox->minX >= $bb->maxX){
                        if(($x1 = $bb->maxX - ($this->boundingBox->minX + $dx)) > 0) $dx += $x1;
                    }
                }
                if($this->boundingBox->maxX > $bb->minX && $this->boundingBox->minX < $bb->maxX){
                    if($this->boundingBox->maxZ + $dz >= $bb->minZ and $this->boundingBox->maxZ <= $bb->minZ){
                        if(($z1 = $bb->minZ - ($this->boundingBox->maxZ + $dz)) < 0) $dz += $z1;
                    }
                    if($this->boundingBox->minZ + $dz <= $bb->maxZ and $this->boundingBox->minZ >= $bb->maxZ){
                        if(($z1 = $bb->maxZ - ($this->boundingBox->minZ + $dz)) > 0) $dz += $z1;
                    }
                }
            }
        }
        $radius = $this->width / 2;
        $this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);
        $this->boundingBox->setBounds($this->x - $radius, $this->y, $this->z - $radius, $this->x + $radius, $this->y + $this->height, $this->z + $radius);

        $this->checkChunks();

        $this->updateFallState($dy, $this->onGround = ($movY != $dy and $movY < 0));
        if($this->onGround){
            $this->motionY = 0;
        }elseif(!$isJump){
            $this->motionY -= $this->gravity;
        }

        $this->isCollidedVertically = $movY != $dy;
        $this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
        $this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
        Timings::$entityMoveTimer->stopTiming();
    }

    public function knockBackCheck(){
        if(!$this->attacker instanceof Entity) return false;
        if($this->moveTime > 5) $this->moveTime = 5;
        $target = $this->attacker;
        $x = $target->x - $this->x;
        $z = $target->z - $this->z;
        $y = [
            4 => 0.3,
            5 => 0.9,
        ];
        $this->move(-cos($atn = atan2($z, $x)) * 0.41, -sin($atn) * 0.41, isset($y[$this->moveTime]) ?  $y[$this->moveTime] : 0);
        if(--$this->moveTime <= 0) $this->attacker = null;
        return true;
    }

    public function close(){
        $this->created = false;
        parent::close();
    }

}
