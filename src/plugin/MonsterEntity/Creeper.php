<?php

namespace plugin\MonsterEntity;

use pocketmine\entity\Explosive;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\level\Explosion;
use pocketmine\nbt\tag\String;
use pocketmine\network\protocol\EntityEventPacket;
use pocketmine\Player;

class Creeper extends Monster implements Explosive{

    const NETWORK_ID = 33;

    public $width = 0.72;
    public $length = 0.6;
    public $height = 1.8;

    protected function initEntity(){
        $this->namedtag->id = new String("id", "Creeper");
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
        }
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
            if($this->distance($target) > 6.2){
                if($this->bombTime > 0) $this->bombTime -= min(2, $this->bombTime);
            }else{
                $this->bombTime++;
                if($this->bombTime >= 45){
                    $this->explode();
                    $this->close();
                    $this->closed = true;
                    return false;
                }
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
