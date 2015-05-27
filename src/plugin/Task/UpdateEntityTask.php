<?php

namespace plugin\Task;

use plugin\Entity\BaseEntity;
use plugin\EntityManager;
use pocketmine\scheduler\PluginTask;

class UpdateEntityTask extends PluginTask{

    public function onRun($currentTicks){
        $entities = EntityManager::getEntities();
        while(($entity = array_shift($entities)) instanceof BaseEntity) if($entity->isCreated()) $entity->updateTick();
    }

}