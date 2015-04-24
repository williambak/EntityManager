<?php

namespace plugin;

use plugin\AnimalEntity\Animal;
use plugin\AnimalEntity\Chicken;
use plugin\AnimalEntity\Cow;
use plugin\AnimalEntity\Pig;
use plugin\AnimalEntity\Sheep;
use plugin\MonsterEntity\Creeper;
use plugin\MonsterEntity\Enderman;
use plugin\MonsterEntity\Monster;
use plugin\MonsterEntity\PigZombie;
use plugin\MonsterEntity\Skeleton;
use plugin\MonsterEntity\Spider;
use plugin\MonsterEntity\Zombie;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Arrow;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class EntityManager extends PluginBase implements Listener{

    public $path;
    public $tick = 0;
    public $entTick = 0;

    public static $entityData;
    public static $spawnerData;
    public static $isLoaded = false;

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

    public function isPhar(){
        return true;
    }

    public function onEnable(){
        if($this->isPhar() === true){
            self::$isLoaded = true;
            $this->path = self::core()->getDataPath() . "plugins/EntityManager/";
            if(!is_dir($this->path)) mkdir($this->path);
            if(file_exists($this->path. "EntityData.yml")){
                self::$entityData = yaml_parse($this->yaml($this->path . "EntityData.yml"));
            }else{
                self::$entityData = [
                    "entity" => [
                        "autospawn" => true,
                        "limit" => 45,
                    ],
                    "spawn" => [
                        "mob" => true,
                        "animal" => true,
                        "tick" => 150,
                        "radius" => 25
                    ],
                    "explode" => true,
                ];
                file_put_contents($this->path . "EntityData.yml", yaml_emit(self::$entityData, YAML_UTF8_ENCODING));
            }

            if(file_exists($this->path. "SpawnerData.yml")){
                self::$spawnerData = yaml_parse($this->yaml($this->path . "SpawnerData.yml"));
            }else{
                self::$spawnerData = [];
                file_put_contents($this->path . "SpawnerData.yml", yaml_emit(self::$spawnerData, YAML_UTF8_ENCODING));
            }

            self::core()->getPluginManager()->registerEvents($this, $this);
            self::core()->getLogger()->info(TextFormat::GOLD . "[EntityManager]플러그인이 활성화 되었습니다");
            self::core()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "updateEntity"]), 1);
        }else{
            self::core()->getLogger()->info(TextFormat::GOLD . "[EntityManager]플러그인을 Phar파일로 변환해주세요");
        }
    }

    public function onDisable(){
        if(!$this->isPhar() || !self::$isLoaded) return;
        file_put_contents($this->path . "SpawnerData.yml", yaml_emit(self::$spawnerData, YAML_UTF8_ENCODING));
    }

    public static function yaml($file){
        if(!self::$isLoaded) return "";
        return preg_replace("#^([ ]*)([a-zA-Z_]{1}[^\:]*)\:#m", "$1\"$2\":", file_get_contents($file));
    }

    public static function core(){
        return Server::getInstance();
    }

    /**
     * @param mixed $level
     *
     * @return Animal[]|Monster[]
     */
    public static function getEntities($level = null){
        if(!self::$isLoaded) return [];
        $entities = [];
        $level = $level instanceof Level ? $level : self::core()->getDefaultLevel();
        foreach($level->getEntities() as $id => $ent){
            if($ent instanceof Animal or $ent instanceof Monster) $entities[$id] = $ent;
        }
        return $entities;
    }

    /**
     * @param Level $level
     * @param string[] $type
     *
     * @return bool
     */
    public static function clearEntity(Level $level = null, $type = null){
        if(!self::$isLoaded) return false;
        $type = $type === null ? [Animal::class, Monster::class] : $type;
        if(!is_array($type) || count($type) === 0) return false;
        $level = $level === null ? self::core()->getDefaultLevel() : $level;
        foreach($level->getEntities() as $id => $ent){
            if(in_array(get_class($ent), $type)){
                $ent->close();
            }
        }
        return true;
    }

    public static function getData($key){
        if(!self::$isLoaded) return null;
        $vars = explode(".", $key);
        $base = array_shift($vars);
        if(!isset(self::$entityData[$base])) return false;
        $base = self::$entityData[$base];
        while(count($vars) > 0){
            $baseKey = array_shift($vars);
            if(!is_array($base) or !isset($base[$baseKey])) return false;
            $base = $base[$baseKey];
        }
        return $base;
    }

    /**
     * @param int|string $type
     * @param Position $source
     * @param bool $isSpawn
     *
     * @return Animal|Monster
     */
    public static function createEntity($type, Position $source, $isSpawn = true){
        if(!self::$isLoaded || self::getData("entity.limit") <= count(self::getEntities())) return null;
        $chunk = $source->getLevel()->getChunk($source->getX() >> 4, $source->getZ() >> 4, true);
        if($chunk === null or !$chunk->isGenerated()) return null;
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
                new Float("", $source instanceof Location ? $source->yaw : 0),
                new Float("", $source instanceof Location ? $source->pitch : 0)
            ]),
        ]);
        $entity = Entity::createEntity($type, $chunk, $nbt);
        if($entity instanceof Animal && !self::getData("spawn.animal")){
            $entity->close();
            return null;
        }elseif($entity instanceof Monster && !self::getData("spawn.mob")){
            $entity->close();
            return null;
        }
        if($entity instanceof Entity && $isSpawn === true) $entity->spawnToAll();
        return $entity;
    }

    public function updateEntity(){
        if(++$this->tick >= self::getData("spawn.tick")){
            $this->tick = 0;
            foreach(self::$spawnerData as $pos => $data){
                if(mt_rand(1, 4) > 1) continue;
                if(count($data["mob-list"]) === 0){
                    unset(self::$spawnerData[$pos]);
                    continue;
                }
                $radius = (int) $data["radius"];
                $level = self::core()->getDefaultLevel();
                $pos = (new Vector3(...explode(":", $pos)))->add(mt_rand(-$radius, $radius), mt_rand(-$radius, $radius), mt_rand(-$radius, $radius));
                $bb = $level->getBlock($pos)->getBoundingBox();
                $bb1 = $level->getBlock($pos->add(0, 1))->getBoundingBox();
                $bb2 = $level->getBlock($pos->add(0, -1))->getBoundingBox();
                if(
                    ($bb !== null and $bb->maxY - $bb->minY > 0)
                    || ($bb1 !== null and $bb1->maxY - $bb1->minY > 0)
                    || $bb2 === null or ($bb2 !== null and $bb2->maxY - $bb2->minY !== 1)
                ) continue;
                self::createEntity($data["mob-list"][mt_rand(0, count($data["mob-list"]) - 1)], Position::fromObject($pos, $level));
            }
            if(self::getData("entity.autospawn")) foreach(self::core()->getOnlinePlayers() as $player){
                if(mt_rand(0, 4) > 0) continue;
                $level = $player->getLevel();
                $rad = self::getData("spawn.radius");
                $pos = $player->add(mt_rand(-$rad, $rad), mt_rand(-$rad, $rad), mt_rand(-$rad, $rad))->floor();
                $bb = $level->getBlock($pos)->getBoundingBox();
                $bb1 = $level->getBlock($pos->add(0, 1))->getBoundingBox();
                $bb2 = $level->getBlock($pos->add(0, -1))->getBoundingBox();
                if(
                    ($bb !== null && $bb->maxY - $bb->minY > 0)
                    || ($bb1 !== null && $bb1->maxY - $bb1->minY > 0)
                    || $bb2 === null or ($bb2 !== null and $bb2->maxY - $bb2->minY > 1)
                ) continue;
                $ent = [
                    ["Cow", "Pig", "Sheep", "Chicken", null, null],
                    ["Zombie", "Creeper", "Skeleton", "Spider", "PigZombie", "Enderman"]
                ];
                self::createEntity($ent[mt_rand(0, 1)][mt_rand(0, 5)], Position::fromObject($pos, $level));
            }
        }
        $per = $this->getServer()->getTickUsage();
        if($per <= 60){
            $maxTick = 1;
        }elseif($per <= 70){
            $maxTick = 2;
        }elseif($per <= 80){
            $maxTick = 3;
        }elseif($per <= 90){
            $maxTick = 4;
        }else{
            $maxTick = 5;
        }
        if(++$this->entTick >= $maxTick){
            foreach(self::getEntities() as $entity){
                if($entity->isCreated()) $entity->updateTick();
            }
            $this->entTick = 0;
        }
    }

    public function EntitySpawnEvent(EntitySpawnEvent $ev){
        $entity = $ev->getEntity();
        if($entity instanceof Animal && !self::getData("spawn.animal")){
            $entity->close();
        }elseif($entity instanceof Monster && !self::getData("spawn.mob")){
            $entity->close();
        }elseif(self::getData("entity.limit") <= count(self::getEntities())){
            $entity->close();
        }
    }

    public function PlayerInteractEvent(PlayerInteractEvent $ev){
        if(
            ($ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_AIR && $ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK)
            || $ev->getFace() === 255
        ) return;
        $item = $ev->getItem();
        $player = $ev->getPlayer();
        $pos = $ev->getBlock()->getSide($ev->getFace());

        if($item->getId() === Item::SPAWN_EGG && $ev->getFace() !== 255){
            $entity = self::createEntity($item->getDamage(), $pos);
            if($entity !== null) $entity->spawnToAll();
            if($player->isSurvival()){
                $item->count--;
                $player->getInventory()->setItemInHand($item);
            }
            $ev->setCancelled();
        }elseif($item->getId() === Item::MONSTER_SPAWNER){
            self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"] = [
                "radius" => 4,
                "mob-list" => ["Cow", "Pig", "Sheep", "Chicken", "Zombie", "Creeper", "Skeleton", "Spider", "PigZombie", "Enderman"],
            ];
        }
    }

    public function BlockBreakEvent(BlockBreakEvent $ev){
        if($ev->isCancelled()) return;
        $pos = $ev->getBlock();
        if(isset(self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"])) unset(self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"]);
    }

    public function a(ExplosionPrimeEvent $ev){
        $mode = self::getData("explode");
        if($mode === false) $ev->setCancelled();
        elseif($mode === "entity") $ev->setBlockBreaking(false);
    }

    public function onCommand(CommandSender $i, Command $cmd, $label, array $sub){
        if(!$this->isPhar() || !self::$isLoaded) return true;
        $output = "[EntityManager]";
        switch($cmd->getName()){
            case "제거":
                self::clearEntity($i instanceof Player ? $i->getLevel() : null, [Animal::class, Monster::class, Arrow::class]);
                $output .= "소환된 엔티티를 모두 제거했어요";
                break;
            case "체크":
                if(!$i instanceof Player){
                    $level = self::core()->getDefaultLevel();
                    $output .= "Level \"{$level->getName()}\" 에 있는 엔티티 수: " . count(self::getEntities());
                }else{
                    $output .= "Level \"{$i->getLevel()->getName()}\" 에 있는 엔티티 수: " . count(self::getEntities($i->getLevel()));
                }
                break;
            case "스폰":
                if(!is_numeric($sub[0]) and gettype($sub[0]) !== "string"){
                    $output .= "엔티티 이름이 올바르지 않아요";
                    break;
                }
                if(count($sub) >= 4){
                    $level = self::core()->getDefaultLevel();
                    if(isset($sub[4]) && ($k = self::core()->getLevelByName($sub[4]))){
                        $level = $k;
                    }elseif($i instanceof Player){
                        $level = $i->getLevel(); 
                    }
                    $pos = new Position($sub[1], $sub[2], $sub[3], $level);
                }elseif($i instanceof Player){
                    $pos = $i->getPosition();
                }
                
                if(isset($pos) && self::createEntity($sub[0], $pos) !== null){
                    $output .= "몬스터가 소환되었어요";
                }else{
                    $output .= "사용법: /스폰 <id|name> (x) (y) (z) (level)";
                }
                break;
        }
        $i->sendMessage($output);
        return true;
    }

}