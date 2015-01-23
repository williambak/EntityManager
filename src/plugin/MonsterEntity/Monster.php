<?php

namespace plugin\MonsterEntity;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\AddMobPacket;
use pocketmine\Player;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\entity\Monster as MonsterEntity;
use pocketmine\entity\Entity;
use pocketmine\Server;

abstract class Monster extends MonsterEntity{

    /** @var Vector3 */
    public $target = null;

    public $moveTime = 0;
    public $bombTime = 0;

    /** @var Entity|null */
    protected $attacker = null;
    protected $attackDelay = 0;

    private $entityMovement = true;


    public function isMovement(){
        return $this->entityMovement;
    }

    public function setMovement($value){
        $this->entityMovement = (bool) $value;
    }

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

    public function attack($damage, $source = EntityDamageEvent::CAUSE_MAGIC){
        if($this->attacker instanceof Entity) return;
        $health = $this->getHealth();
        parent::attack($damage, $source);
        if($source instanceof EntityDamageByEntityEvent and ($health - $damage) == $this->getHealth()){
            $this->moveTime = 100;
            $this->attacker = $source->getDamager();
        }
    }

    public function knockBack(Entity $attacker, $damage, $x, $z, $base = 0.4){

    }

    public function move($dx, $dz, $dy = 0){
        if($this->isMovement() === false) return;
        if($this->onGround === false && $this->lastX !== null && $dy === 0){
            $this->motionY -= $this->gravity;
            $dy = $this->motionY;
        }
		
		$movX = $dx;
		$movY = $dy;
		$movZ = $dz;

		$list = $this->level->getCollisionCubes($this, $this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz));

		foreach($list as $bb){
			$dx = $bb->calculateXOffset($this->boundingBox, $dx);
		}
		$this->boundingBox->offset($dx, 0, 0);

		foreach($list as $bb){
			$dz = $bb->calculateZOffset($this->boundingBox, $dz);
		}
		$this->boundingBox->offset(0, 0, $dz);

		foreach($list as $bb){
			$dy = $bb->calculateYOffset($this->boundingBox, $dy);
		}
		$this->boundingBox->offset(0, $dy, 0);
		$this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);

		$this->isCollidedVertically = $movY != $dy;
		$this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
		$this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
		$this->onGround = ($movY != $dy and $movY < 0);
        if($this->onGround) $this->motionY = 0;
		$this->updateFallState($dy, $this->onGround);
    }
    
    public function knockBackCheck(){
        if(!$this->attacker instanceof Entity) return false;

        if($this->moveTime > 5) $this->moveTime = 5;
		$this->moveTime--;
        $target = $this->attacker;

        $x = $target->x - $this->x;
        $y = $target->y - $this->y;
        $z = $target->z - $this->z;
        $atn = atan2($z, $x);
        $this->move(cos($atn) * -0.28, sin($atn) * -0.28, 0.32);
        $this->setRotation(rad2deg(atan2($z, $x) - M_PI_2), rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2))));

        $this->entityBaseTick();
        $this->updateMovement();
        if($this->moveTime <= 0) $this->attacker = null;
        return true;
    }

    /**
     * @return Player|Vector3
     */
    public function getTarget(){
        $target = null;
        $nearDistance = PHP_INT_MAX;
        foreach($this->getViewers() as $p){
            if(($distance = $this->distanceSquared($p)) <= 81 and $p->spawned and $p->isSurvival() and $p->dead == false and !$p->closed){
                if($distance < $nearDistance){
                    $target = $p;
                    $nearDistance = $distance;
                    continue;
                }
            }
        }
        if($target instanceof Player){
            return $target;
        }elseif($this->moveTime >= mt_rand(650, 800) or ($target === null and !$this->target instanceof Vector3)){
            $this->moveTime = 0;
            return $this->target = new Vector3($this->x + mt_rand(-100, 100), $this->y, $this->z + mt_rand(-100,100));
        }
        return $this->target;
    }
    
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