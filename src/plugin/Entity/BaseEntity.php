<?php

namespace plugin\Entity;

use pocketmine\block\Block;
use pocketmine\entity\Creature;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
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

    protected $moveTime = 0;
    protected $created = false;
    /** @var Vector3 */
    protected $target = null;
    /** @var Entity|null */
    protected $attacker = null;

    private $movement = true;

    public abstract function updateTick();

    /**
     * @return Player|Vector3
     */
    public abstract function getTarget();

    public function getSaveId(){
        $class = new \ReflectionClass(static::class);
        return $class->getShortName();
    }

    public function onUpdate($currentTick){
        return false;
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
        Entity::initEntity();
    }

    public function saveNBT(){
        $this->namedtag->Movement = new Byte("Movement", $this->isMovement());
        parent::saveNBT();
    }

    public function spawnTo(Player $player){
        if(isset($this->hasSpawned[$player->getId()]) or !isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) return;

        $this->setDataProperty(self::DATA_NO_AI, self::DATA_TYPE_BYTE, 1);
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

        foreach($this->hasSpawned as $player) $player->addEntityMovement($this->id, $this->x, $this->y, $this->z, $this->yaw, $this->pitch, $this->yaw);
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

        /*foreach($this->level->getCollidingEntities($bb->grow(0.25, 0.25, 0.25), $this) as $ent){
            $collides[] = $ent;
        }*/

        return $collides;
    }

    public function move($dx, $dz, $dy = 0){
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
                $target instanceof Block
                && $dy === 0
                && $bb->maxY - $bb->minY <= 1
                && $this->boundingBox->minY >= $bb->minY
                && $this->boundingBox->minY < $bb->maxY
                && $target->getSide(Vector3::SIDE_UP)->getBoundingBox() === null
                && $target->distanceSquared($target->add(0.5, 0.5, 0.5)) <= 1
            ){
                $isJump = true;
                $dy = 0.55;
                $this->motionY = 0;
            }
            if($this->boundingBox->maxY > $bb->minY and $this->boundingBox->minY < $bb->maxY){
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
            if(
                $dy != 0
                and $this->boundingBox->maxX > $bb->minX
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
        }
        $radius = $this->width / 2;
        $this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);
        $this->boundingBox->setBounds($this->x - $radius, $this->y, $this->z - $radius, $this->x + $radius, $this->y + $this->height, $this->z + $radius);

        $this->updateFallState($dy, $this->onGround = ($movY != $dy and $movY < 0));
        if($this->onGround){
            $this->motionY = 0;
        }elseif(!$isJump){
            $this->motionY -= $this->gravity;
        }

        $this->isCollidedVertically = $movY != $dy;
        $this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
        $this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
    }

    public function knockBackCheck(){
        if(!$this->attacker instanceof Entity) return false;

        if($this->moveTime > 5) $this->moveTime = 5;
        $target = $this->attacker;
        if(--$this->moveTime <= 0) $this->attacker = null;
        $y = [
            0,
            0,
            0,
            1.1,
            0.3,
        ];
        if(!isset($y[$this->moveTime])){
            $motionY = 0;
            $this->attacker = null;
        }else{
            $motionY = $y[$this->moveTime];
        }
        $x = $target->x - $this->x;
        $z = $target->z - $this->z;
        $atn = atan2($z, $x);
        $this->move(-cos($atn) * 0.41, -sin($atn) * 0.41, $motionY);

        $this->entityBaseTick();
        $this->updateMovement();
        return true;
    }

    public function close(){
        $this->created = false;
        parent::close();
    }

}
