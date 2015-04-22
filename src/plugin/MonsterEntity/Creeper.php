<?php

namespace plugin\MonsterEntity;

use pocketmine\entity\Explosive;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\level\Explosion;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\String;
use pocketmine\Player;

class Creeper extends Monster implements Explosive{
    const NETWORK_ID = 33;

    public $width = 0.72;
    public $length = 0.6;
    public $height = 1.8;

    protected $bombTime = 0;

    protected function initEntity(){
        parent::initEntity();
        $this->namedtag->id = new String("id", "Creeper");
        $this->lastTick = microtime(true);
        $this->created = true;
    }

    public function getName(){
        return "크리퍼";
    }

    public function explode(){
        $this->server->getPluginManager()->callEvent($ev = new ExplosionPrimeEvent($this, 3.2));

        if(!$ev->isCancelled()){
            $explosion = new Explosion($this, $ev->getForce(), $this);
            if($ev->isBlockBreaking()){
                $explosion->explodeA();
            }
            $explosion->explodeB();
            $this->close();
        }
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
            if($this->distance($target) > 6.2){
                if($this->bombTime > 0) $this->bombTime -= min(2, $this->bombTime);
            }else{
                $this->bombTime++;
                if($this->bombTime >= 58){
                    $this->explode();
                    return;
                }
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
        $this->lastTick = microtime(true);
    }

}
