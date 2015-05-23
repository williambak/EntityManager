<?php

namespace plugin\Task;

use plugin\EntityManager;
use pocketmine\scheduler\PluginTask;

class UpdateEntityTask extends PluginTask{

    public function __construct(EntityManager $owner){
        $this->owner = $owner;
    }

    public function onRun($currentTicks){
        foreach(EntityManager::getEntities() as $entity){
            if($entity->isCreated()) $entity->updateTick();
        }
    }

}