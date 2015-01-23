<?php

namespace plugin;

//동물
use plugin\AnimalEntity\Animal;
use plugin\AnimalEntity\Chicken;
use plugin\AnimalEntity\Cow;
use plugin\AnimalEntity\Pig;
use plugin\AnimalEntity\Sheep;

//몬스터
use plugin\MonsterEntity\Creeper;
use plugin\MonsterEntity\Monster;
use plugin\MonsterEntity\PigZombie;
use plugin\MonsterEntity\Skeleton;
use plugin\MonsterEntity\Spider;
use plugin\MonsterEntity\Zombie;
use plugin\MonsterEntity\Enderman;

use pocketmine\entity\Arrow;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\nbt\tag\Double;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\nbt\tag\Compound;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\player\PlayerInteractEvent;

class EntityManager extends PluginBase implements Listener{

    public $path;

    public static $entityData;
    public static $spawnerData;

    public function __construct(){
        Entity::registerEntity(Cow::class);
        Entity::registerEntity(Pig::class);
        Entity::registerEntity(Sheep::class);
        Entity::registerEntity(Chicken::class);

        Entity::registerEntity(Zombie::class);
        Entity::registerEntity(Creeper::class);
        Entity::registerEntity(Skeleton::class);
        Entity::registerEntity(Spider::class);
        Entity::registerEntity(PigZombie::class);
        Entity::registerEntity(Enderman::class);
    }

    public function onEnable(){
        if($this->isPhar() === true){
            @mkdir($this->path = self::core()->getDataPath()."plugins/EntityManager/");
            $this->readData();
            self::core()->getPluginManager()->registerEvents($this, $this);
            self::core()->getLogger()->info(TextFormat::GOLD . "[EntityManager]플러그인이 활성화 되었습니다");
            self::core()->getScheduler()->scheduleDelayedRepeatingTask(new CallbackTask([$this, "SpawningEntity"]), 65, 1);
        }else{
            self::core()->getLogger()->info(TextFormat::GOLD . "[EntityManager]플러그인을 Phar파일로 변환해주세요");
        }
    }

    public function onDisable(){
        file_put_contents($this->path . "SpawnerData.yml", yaml_emit(self::$spawnerData, YAML_UTF8_ENCODING));
    }

    public static function yaml($file){
        return preg_replace("#^([ ]*)([a-zA-Z_]{1}[^\:]*)\:#m", "$1\"$2\":", file_get_contents($file));
    }

    public static function core(){
        return Server::getInstance();
    }

    /**
     * @return Entity[]
     */
    public static function getEntities(){
        $entities = [];
        foreach(self::core()->getDefaultLevel()->getEntities() as $id => $ent){
            if($ent instanceof Animal or $ent instanceof Monster) $entities[$id] = $ent;
        }
        return $entities;
    }

    public static function clearEntity(){
        foreach(self::core()->getDefaultLevel()->getEntities() as $ent){
            if(
                $ent instanceof Animal
                || $ent instanceof Monster
                || $ent instanceof Arrow
                || $ent instanceof \pocketmine\entity\Item
            ){
                $ent->attack(1000);
                $ent->close();
            }
        }
    }

    public function readData(){
        if(file_exists($this->path. "EntityData.yml")){
            self::$entityData = yaml_parse($this->yaml($this->path . "EntityData.yml"));
        }else{
            self::$entityData = [
                "MaximumCount" => 25,
                "SpawnAnimal" => true,
                "SpawnMonster" => true,
            ];
            file_put_contents($this->path . "EntityData.yml", yaml_emit(self::$entityData, YAML_UTF8_ENCODING));
        }

        if(file_exists($this->path. "SpawnerData.yml")){
            self::$spawnerData = yaml_parse($this->yaml($this->path . "SpawnerData.yml"));
        }else{
            self::$spawnerData = [];
            file_put_contents($this->path . "SpawnerData.yml", yaml_emit(self::$spawnerData, YAML_UTF8_ENCODING));
        }
    }

    public static function getData($data){
        return isset(self::$entityData[$data]) ? self::$entityData[$data] : false;
    }
    
    /**
     * @param int|string $type
     * @param Position $source
     *
     * @return Entity
     */
    public static function createEntity($type, Position $source){
        if(self::getData("MaximumCount") <= count(self::getEntities())) return null;
        $nbt = new Compound("", [
            "Pos" => new Enum("Pos", [
                new Double("", $source->x),
                new Double("", $source->y),
                new Double("", $source->z)
            ]),
            "Motion" => new Enum("Motion", [
                new Double("", 0),
                new Double("", 0),
                new Double("", 0)
            ]),
            "Rotation" => new Enum("Rotation", [
                new Float("", 0),
                new Float("", 0)
            ]),
        ]);
        return Entity::createEntity($type, $source->getLevel()->getChunk($source->getX() >> 4, $source->getZ() >> 4), $nbt);
    }

    public function SpawningEntity(){
        foreach(self::$spawnerData as $pos => $data){
            if(mt_rand(0,1) !== 1 or count($data["SpawnMob"]) <= 0) continue;
            $vector = explode(":", $pos);
            $radius = (int) ($data["Radius"] / 2);
            $level = self::core()->getDefaultLevel();
            $pos = (new Vector3(...$vector))->add(mt_rand(-$radius, $radius), mt_rand(-$radius, $radius), mt_rand(-$radius, $radius));
            $bb = $level->getBlock($pos)->getBoundingBox();
            $bb1 = $level->getBlock($pos->add(0, 1))->getBoundingBox();
            $bb2 = $level->getBlock($pos->add(0, -1))->getBoundingBox();
			if(
                ($bb !== null and $bb->maxY - $bb->minY > 0)
                || ($bb1 !== null and $bb1->maxY - $bb1->minY > 0)
                || $bb2 === null or ($bb2 !== null and $bb2->maxY - $bb2->minY !== 1)
            ) continue;
            $entity = self::createEntity($data["SpawnMob"][mt_rand(0, count($data["SpawnMob"]) - 1)], Position::fromObject($pos, $level));
            if($entity instanceof Entity){
				$entity->spawnToAll();
				$entity->setPosition($pos);
			}
        }
    }

    public function PlayerInteractEvent(PlayerInteractEvent $ev){
        $item = $ev->getItem();
        $pos = $ev->getBlock()->getSide($ev->getFace());

        if($item->getId() === Item::SPAWN_EGG && $ev->getFace() !== 255){
            $entity = self::createEntity($item->getDamage(), $pos);
            if($entity instanceof Entity) $entity->spawnToAll();
            if($ev->getPlayer()->isSurvival()){
                $item->count -= 1;
                $ev->getPlayer()->getInventory()->setItemInHand($item);
            }
            $ev->setCancelled();
        }elseif($item->getId() === Item::MONSTER_SPAWNER && $ev->getFace() !== 255){
            self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"] = [
                "Radius" => 10,
                "SpawnMob" => ["Cow", "Pig", "Sheep", "Chicken", "Zombie", "Creeper", "Skeleton", "Spider", "PigZombie", "Enderman"],
            ];
            $ev->getPlayer()->sendMessage("[EntityManager]스포너가 설치되었습니다");
        }
    }

    public function BlockBreakEvent(BlockBreakEvent $ev){
        $pos = $ev->getBlock();
        if($pos->getId() === Item::MONSTER_SPAWNER && isset(self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"])){
            $ev->getPlayer()->sendMessage("[EntityManager]스포너가 파괴되었습니다");
            unset(self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"]);
        }
    }

    public function onCommand(CommandSender $i, Command $cmd, $label, array $sub){
        $output = "[EntityManager]";
        switch($cmd->getName()){
            case "제거":
                self::clearEntity();
                $output .= "소환된 엔티티를 모두 제거했어요";
                break;
            case "체크":
                $output .= "현재 소환된 수:" . count(self::getEntities()) . "마리";
                break;
            case "스폰":
                if(!is_numeric($sub[0]) and gettype($sub[0]) !== "string"){
                    $output .= "엔티티 이름이 올바르지 않습니다";
                    break;
                }
				$pos = null;
                if(count($sub) >= 4) $pos = new Position($sub[1], $sub[2], $sub[3], self::core()->getDefaultLevel());
				elseif($i instanceof Player) $pos = $i->getPosition();
                if($pos !== null && self::createEntity($sub[0], $pos) !== null){
					$output .= "몬스터가 소환되었어요";
				}else{
					$output .= "몬스터를 소환하는데 실패했어요";
				}
                break;
        }
        $i->sendMessage($output);
        return true;
    }

}