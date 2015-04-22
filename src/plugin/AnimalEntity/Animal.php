<?php

namespace plugin\AnimalEntity;

use pocketmine\entity\Animal as AnimalEntity;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\network\Network;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;
use pocketmine\Server;

abstract class Animal extends AnimalEntity{

    /** @var Vector3 */
    public $stayVec = null;
    public $stayTime = 0;

    /** @var Vector3 */
    protected $target = null;
    /** @var Entity|null */
    protected $attacker = null;

    protected $lastTick = 0;
    protected $moveTime = 0;
    protected $created = false;

    private $entityMovement = true;

    /**
     * @return Player|Vector3
     */
    public abstract function getTarget();

    public function isCreated(){
        return $this->created;
    }

    public function isMovement(){
        return $this->entityMovement;
    }

    public function spawnTo(Player $player){
        parent::spawnTo($player);

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
    }

    public function updateMovement(){
        $this->lastX = $this->x;
        $this->lastY = $this->y;
        $this->lastZ = $this->z;
        $this->lastYaw = $this->yaw;
        $this->lastPitch = $this->pitch;

        foreach($this->hasSpawned as $player) $player->addEntityMovement($this->id, $this->x, $this->y, $this->z, $this->yaw, $this->pitch, $this->yaw);
    }

    public function setMovement($value){
        $this->entityMovement = (bool) $value;
    }

    public function knockBack(Entity $attacker, $damage, $x, $z, $base = 0.4){}

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
            $this->moveTime = 100;
            $this->attacker = $source->getDamager();
        }
        $pk = new EntityEventPacket();
        $pk->eid = $this->getId();
        $pk->event = (int) $this->getHealth() <= 0 ? 3 : 2; //Ouch!
        Server::broadcastPacket($this->hasSpawned, $pk->setChannel(Network::CHANNEL_WORLD_EVENTS));
    }

    public function updateMove($tick = 1){
        $target = null;
        if($this->isMovement()){
            $target = $this->getTarget();
            $x = $target->x - $this->x;
            $y = $target->y - $this->y;
            $z = $target->z - $this->z;
            $atn = atan2($z, $x);
            if($this->stayTime > 0){
                $this->move(0, 0);
                if(--$this->stayTime <= 0) $this->stayVec = null;
            }else{
                if(!$this->onGround && $this->lastY !== null) $this->motionY -= $this->gravity;
                $this->move(cos($atn) * 0.07 * $tick, sin($atn) * 0.07 * $tick, $this->motionY);
            }
            $this->setRotation(rad2deg($atn - M_PI_2), rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2))));
        }else{
            $this->move(0, 0);
        }
        return $target;
    }

    public function move($dx, $dz, $dy = 0){
        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;
        $list = $this->level->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz));
        foreach($list as $bb){
            $dy = $bb->calculateYOffset($this->boundingBox, $dy);
        }
        $this->boundingBox->offset(0, $dy, 0);
        foreach($list as $bb){
            $dx = $bb->calculateXOffset($this->boundingBox, $dx);
        }
        $this->boundingBox->offset($dx, 0, 0);
        foreach($list as $bb){
            $dz = $bb->calculateZOffset($this->boundingBox, $dz);
        }
        $this->boundingBox->offset(0, 0, $dz);
        $this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);

        $this->isCollidedVertically = $movY != $dy;
        $this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
        $this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
        $this->onGround = ($movY != $dy and $movY < 0);
        if($this->onGround) $this->motionY = 0;
        $this->updateFallState($dy, $this->onGround);
    }

    public function knockBackCheck($tick = 1){
        if(!$this->attacker instanceof Entity) return false;

        if($this->moveTime > 5) $this->moveTime = 5;
        $this->moveTime -= $tick;
        $target = $this->attacker;
        $x = $target->x - $this->x;
        $y = $target->y - $this->y;
        $z = $target->z - $this->z;
        $atn = atan2($z, $x);
        $this->move(-cos($atn) * 0.3 * $tick, -sin($atn) * 0.3 * $tick, $this->moveTime > 3 ? 0.6 * $tick : 0);
        $this->setRotation(rad2deg(atan2($z, $x) - M_PI_2), rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2))));
        if((int) $this->moveTime <= 0) $this->attacker = null;
        $this->entityBaseTick($tick);
        $this->updateMovement();
        $this->lastTick = microtime(true);
        return true;
    }

    public function onUpdate($currentTick){}

    public function updateTick(){
        $tick = (microtime(true) - $this->lastTick) * 20;
        if($this->dead === true){
            $this->knockBackCheck($tick);
            if(++$this->deadTicks >= 23) $this->close();
            return;
        }
        if($this->knockBackCheck($tick)) return;

        $this->moveTime += $tick;
        $target = $this->updateMove($tick);
        if($target instanceof Player){
            if($this->distance($target) <= 2){
                $this->pitch = 22;
                $this->x = $this->lastX;
                $this->y = $this->lastY;
                $this->z = $this->lastZ;
            }
        }elseif($target instanceof Vector3){
            if($this->distance($target) <= 1){
                $this->moveTime = 800;
            }elseif($this->x === $this->lastX or $this->z === $this->lastZ){
                $this->moveTime += 20;
            }
        }
        $this->entityBaseTick($tick);
        $this->updateMovement();
        $this->lastUpdate = microtime(true);
    }

}
