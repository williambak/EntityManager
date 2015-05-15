<?php

namespace plugin\MonsterEntity;

use pocketmine\entity\Explosive;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\level\Explosion;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\Short;
use pocketmine\nbt\tag\String;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item as ItemItem;

class Creeper extends Monster implements Explosive{
    const NETWORK_ID = 33;

    public $width = 0.72;
    public $height = 1.8;
    public $eyeHeight = 1.62;

    private $bombTime = 0;

    protected function initEntity(){
        parent::initEntity();

        $this->lastTick = microtime(true);
        if(!isset($this->namedtag->id)){
            $this->namedtag->id = new String("id", "Creeper");
        }
        if(!isset($this->namedtag->Health)){
            $this->namedtag->Health = new Short("Health", $this->getMaxHealth());
        }
        if(!isset($this->namedtag->BombTime)){
            $this->namedtag->BombTime = new Int("BombTime", $this->bombTime);
        }
        $this->setHealth($this->namedtag["Health"]);
        $this->bombTime = (int) $this->namedtag["BombTime"];
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
        if(!$this->isAlive()){
            $this->deadTicks += $tick;
            if($this->deadTicks >= 25) $this->close();
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
    public function getDrops() {
    	$drops = [ ];
    	if ($this->lastDamageCause instanceof EntityDamageByEntityEvent) {
    		switch (mt_rand ( 0, 2 )) {
    			case 0 :
    				$drops [] = ItemItem::get ( ItemItem::FLINT, 0, 1 );
    				break;
    			case 1 :
    				$drops [] = ItemItem::get ( ItemItem::GUNPOWDER, 0, 1 );
    				break;
    			case 2 :
    				$drops [] = ItemItem::get ( ItemItem::REDSTONE_DUST, 0, 1 );
    				break;
    		}
    	}
    	return $drops;
    }
}
