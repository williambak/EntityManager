<?php

namespace plugin\Task;

use plugin\EntityManager;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\scheduler\PluginTask;

class SpawnEntityTask extends PluginTask{

    public function onRun($currentTicks){
        foreach(EntityManager::$spawnerData as $pos => $data){
            if(mt_rand(1, 3) > 1) continue;
            if(count($data["mob-list"]) === 0){
                unset(EntityManager::$spawnerData[$pos]);
                continue;
            }
            $radius = (int) $data["radius"];
            $level = $this->owner->getServer()->getDefaultLevel();
            $pos = (new Vector3(...explode(":", $pos)))->add(mt_rand(-$radius, $radius), mt_rand(-$radius, $radius), mt_rand(-$radius, $radius));
            if(
                (($bb = $level->getBlock($pos)->getBoundingBox()) !== null and $bb->maxY - $bb->minY > 0)
                || (($bb1 = $level->getBlock($pos->add(0, 1))->getBoundingBox()) !== null and $bb1->maxY - $bb1->minY > 0)
                || (($bb2 = $level->getBlock($pos->add(0, -1))->getBoundingBox()) !== null and $bb2->maxY - $bb2->minY > 1)
            ) continue;
            EntityManager::createEntity($data["mob-list"][mt_rand(0, count($data["mob-list"]) - 1)], Position::fromObject($pos, $level));
        }
        if(EntityManager::getData("spawn.auto", true)) foreach($this->owner->getServer()->getOnlinePlayers() as $player){
            if(mt_rand(1, 10) > 1) continue;
            $level = $player->getLevel();
            $radius = EntityManager::getData("spawn.radius", 25);
            $pos = $player->add(mt_rand(-$radius, $radius), 0, mt_rand(-$radius, $radius));
            $pos->y = $level->getHighestBlockAt($pos->x, $pos->z);
            $ent = [
                ["Cow", "Pig", "Sheep", "Chicken", null, null],
                ["Zombie", "Creeper", "Skeleton", "Spider", "PigZombie", "Enderman"]
            ];
            EntityManager::createEntity($ent[mt_rand(0, 1)][mt_rand(0, 5)], Position::fromObject($pos, $level));
        }
    }

}