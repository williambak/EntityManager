<?php

namespace plugin\Entity;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Minecart as Real_Minecart;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Math;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

class Minecart extends Real_Minecart{
    const NETWORK_ID = 84;

    public $width = 0.75;
    public $height = 0.92;

    /** @var Player */
    private $rider = null;

    public function spawnTo(Player $player){
        if(isset($this->hasSpawned[$player->getId()]) or !isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) return;

        $pk = new AddEntityPacket();
        $pk->eid = $this->getID();
        $pk->type = self::NETWORK_ID;
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

    public function getRider(){
        return $this->rider;
    }

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

        foreach($this->level->getCollidingEntities($bb->grow(0.25, 0.25, 0.25), $this) as $ent){
            $collides[] = $ent;
        }

        return $collides;
    }

    public function move($dx, $dz, $dy = 0){
        if($dy <= 0 && !$this->onGround && $this->lastY !== null){
            $this->motionY -= $this->gravity;
            $dy = $this->motionY;
        }
        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;
        $list = $this->getCollisionCubes($this->boundingBox->getOffsetBoundingBox($dx, $dy, $dz));
        foreach($list as $target){
            if(!$target instanceof Block && !$target instanceof Entity) continue;
            $bb = $target->getBoundingBox();
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

            $minY = (int) $this->boundingBox->minY;
            if($minY === $bb->minY && ($minY + 0.5) !== $bb->maxY){
                if($this->boundingBox->maxZ > $bb->minZ && $this->boundingBox->minZ < $bb->maxZ){
                    if($this->boundingBox->maxX + $dx >= $bb->minX and $this->boundingBox->maxX <= $bb->minX){
                        if(($x1 = $this->boundingBox->maxX + $dx - $bb->minX) > 0) $dx += $x1;
                    }
                    if($this->boundingBox->minX + $dx <= $bb->maxX and $this->boundingBox->minX >= $bb->maxX){
                        if(($x1 = $this->boundingBox->minX + $dx - $bb->maxX) < 0) $dx += $x1;
                    }
                }
                if($this->boundingBox->maxX > $bb->minX && $this->boundingBox->minX < $bb->maxX){
                    if($this->boundingBox->maxZ + $dz >= $bb->minZ and $this->boundingBox->maxZ <= $bb->minZ){
                        if(($z1 = $this->boundingBox->maxZ + $dz - $bb->minZ) > 0) $dz += $z1;
                    }
                    if($this->boundingBox->minZ + $dz <= $bb->maxZ and $this->boundingBox->minZ >= $bb->maxZ){
                        if(($z1 = $this->boundingBox->minZ + $dz - $bb->maxZ) < 0) $dz += $z1;
                    }
                }
                if($target instanceof Block && $minY + 1 == $bb->maxY && $bb->maxY - $bb->maxY <= 1 & $dy === 0){
                    $dy = 0.3;
                    $this->motionY = 0;
                }
            }
        }
        $this->boundingBox->offset($dx, $dy, $dz);
        $this->setComponents($this->x + $dx, $this->y + $dy, $this->z + $dz);

        $this->updateFallState($dy, $this->onGround = ($movY != $dy and $movY < 0));
        if($this->onGround) $this->motionY = 0;

        $this->isCollidedVertically = $movY != $dy;
        $this->isCollidedHorizontally = ($movX != $dx or $movZ != $dz);
        $this->isCollided = ($this->isCollidedHorizontally or $this->isCollidedVertically);
    }

}