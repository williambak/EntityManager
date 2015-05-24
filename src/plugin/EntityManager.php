<?php

namespace plugin;

use plugin\Entity\Animal;
use plugin\Entity\Chicken;
use plugin\Entity\Cow;
use plugin\Entity\Pig;
use plugin\Entity\Sheep;
use plugin\Entity\BaseEntity;
use plugin\Entity\Minecart;
use plugin\Entity\Creeper;
use plugin\Entity\Enderman;
use plugin\Entity\Monster;
use plugin\Entity\PigZombie;
use plugin\Entity\Skeleton;
use plugin\Entity\Spider;
use plugin\Entity\Zombie;
use plugin\Task\SpawnEntityTask;
use plugin\Task\UpdateEntityTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Arrow;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Float;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\entity\Item as ItemEntity;

class EntityManager extends PluginBase implements Listener{

    public $path;
    public $tick = 0;
    public $entTick = 0;

    public static $entityData;
    public static $spawnerData;

    private static $entities = [];
    private static $knownEntities = [];

    public function __construct(){
        $classes = [
            Cow::class,
            Pig::class,
            Sheep::class,
            Chicken::class,

            Zombie::class,
            Creeper::class,
            Skeleton::class,
            Spider::class,
            PigZombie::class,
            Enderman::class,

            Minecart::class
        ];
        foreach($classes as $name) self::registerEntity($name);
    }

    public function onEnable(){
        $this->path = $this->getServer()->getDataPath() . "plugins/EntityManager/";
        if(!is_dir($this->path)) mkdir($this->path);
        $data = [];
        if(file_exists($this->path. "EntityData.yml")){
            $data = yaml_parse($this->yaml($this->path . "EntityData.yml"));
        }

        self::$entityData = [
            "custom" =>[
                "name" => isset($data["custom"]["name"]) ? $data["custom"]["name"] : "CustomEntity",
                "type" => isset($data["custom"]["type"]) ? $data["custom"]["type"] : 32, //엔티티 타입
                "damage" => isset($data["custom"]["damage"]) ? $data["custom"]["damage"] : [0, 3, 4, 6], //난이도별 데미지
                "drops" => isset($data["custom"]["drops"]) ? $data["custom"]["drops"] : [], //죽을시 드롭할 아이템
            ],
            "entity" => [
                "explode" => isset($data["entity"]["explode"]) ? $data["entity"]["explode"] : true,
            ],
            "spawn" => [
                "auto" => isset($data["spawn"]["auto"]) ? $data["spawn"]["auto"] : true,
                "mob" => isset($data["spawn"]["mob"]) ? $data["spawn"]["mob"] : true,
                "animal" => isset($data["spawn"]["animal"]) ? $data["spawn"]["animal"] : true,
                "tick" => isset($data["spawn"]["tick"]) ? $data["spawn"]["tick"] : 150,
                "radius" => isset($data["spawn"]["radius"]) ? $data["spawn"]["radius"] : 25
            ],
        ];
        file_put_contents($this->path . "EntityData.yml", yaml_emit(self::$entityData, YAML_UTF8_ENCODING));

        if(file_exists($this->path. "SpawnerData.yml")){
            self::$spawnerData = yaml_parse($this->yaml($this->path . "SpawnerData.yml"));
        }else{
            self::$spawnerData = [];
            file_put_contents($this->path . "SpawnerData.yml", yaml_emit(self::$spawnerData, YAML_UTF8_ENCODING));
        }

        foreach(self::$knownEntities as $id => $name){
            if(!is_numeric($id)) continue;
            $item = Item::get(Item::SPAWN_EGG, $id);
            if(!Item::isCreativeItem($item)) Item::addCreativeItem($item);
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getLogger()->info(TextFormat::GOLD . "[EntityManager]플러그인이 활성화 되었습니다");
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new UpdateEntityTask($this), 1);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new SpawnEntityTask($this), EntityManager::getData("spawn.tick", 150));
    }

    public function onDisable(){
        file_put_contents($this->path . "SpawnerData.yml", yaml_emit(self::$spawnerData, YAML_UTF8_ENCODING));
    }

    public static function yaml($file){
        return preg_replace("#^([ ]*)([a-zA-Z_]{1}[^\:]*)\:#m", "$1\"$2\":", file_get_contents($file));
    }

    /**
     * @param Level $level
     *
     * @return BaseEntity[]
     */
    public static function getEntities(Level $level = null){
        /** @var BaseEntity[] $entities */
        $entities = self::$entities;
        if($level != null){
            foreach($entities as $id => $entity){
                if($entity->getLevel() !== $level) unset($entities[$id]);
            }
        }
        return $entities;
    }

    /**
     * @param Level $level
     * @param string[] $type
     *
     * @return bool
     */
    public static function clearEntity(Level $level = null, $type = []){
        if(!is_array($type)) return false;
        $type = count($type) === 0 ? [BaseEntity::class] : $type;
        $level = $level === null ? Server::getInstance()->getDefaultLevel() : $level;
        foreach($level->getEntities() as $id => $ent){
            foreach($type as $t){
                if(is_a(get_class($ent), $t, true)){
                    $ent->close();
                    continue;
                }
            }
        }
        return true;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public static function getData($key){
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
     * @param mixed ...$args
     *
     * @return BaseEntity|Entity
     */
    public static function createEntity($type, Position $source, ...$args){
        $chunk = $source->getLevel()->getChunk($source->x >> 4, $source->z >> 4, true);
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
        if(isset(self::$knownEntities[$type])){
            $class = self::$knownEntities[$type];
            /** @var BaseEntity $entity */
            $entity =  new $class($chunk, $nbt, ...$args);
            if($entity != null && $entity->isCreated()) $entity->spawnToAll();
            return $entity;
        }else{
            $entity = Entity::createEntity($type, $chunk, $nbt, ...$args);
            if($entity != null) $entity->spawnToAll();
            return $entity;
        }
    }

    public static function registerEntity($name){
        $class = new \ReflectionClass($name);
        if(is_a($name, BaseEntity::class, true) and !$class->isAbstract()){
            Entity::registerEntity($name, true);
            if($name::NETWORK_ID !== -1){
                self::$knownEntities[$name::NETWORK_ID] = $name;
            }
            self::$knownEntities[$class->getShortName()] = $name;
        }
    }

    public function EntitySpawnEvent(EntitySpawnEvent $ev){
        $entity = $ev->getEntity();
        if($entity instanceof Animal && !self::getData("spawn.animal", true)){
            $entity->close();
        }elseif($entity instanceof Monster && !self::getData("spawn.mob", true)){
            $entity->close();
        }
        foreach([BaseEntity::class, Minecart::class] as $class){
            if(is_a($entity, $class, true) && !$entity->closed){
                self::$entities[$entity->getId()] = $entity;
                return;
            }
        }
    }

    public function EntityDespawnEvent(EntityDespawnEvent $ev){
        $entity = $ev->getEntity();
        if($entity instanceof BaseEntity or $entity instanceof Minecart){
            unset(self::$entities[$entity->getId()]);
        }
    }

    public function PlayerInteractEvent(PlayerInteractEvent $ev){
        if(
            $ev->getFace() === 255
            && ($ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_AIR && $ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK)
        ) return;
        $item = $ev->getItem();
        $player = $ev->getPlayer();
        $pos = $ev->getBlock()->getSide($ev->getFace());

        if($item->getId() === Item::SPAWN_EGG){
            if(self::createEntity($item->getDamage(), $pos) != null && $player->isSurvival()){
                $item->count--;
                $player->getInventory()->setItemInHand($item);
            }
            $ev->setCancelled();
        }elseif($item->getId() === Item::MONSTER_SPAWNER){
            self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"] = [
                "radius" => 5,
                "mob-list" => [
                    "Cow", "Pig", "Sheep", "Chicken",
                    "Zombie", "Creeper", "Skeleton", "Spider", "PigZombie", "Enderman"
                ],
            ];
        }
    }

    public function BlockBreakEvent(BlockBreakEvent $ev){
        if($ev->isCancelled()) return;
        $pos = $ev->getBlock();
        if(isset(self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"])) unset(self::$spawnerData["{$pos->x}:{$pos->y}:{$pos->z}"]);
    }

    public function a(ExplosionPrimeEvent $ev){
        $ev->setCancelled(!self::getData("entity.explode", true));
    }

    public function onCommand(CommandSender $i, Command $cmd, $label, array $sub){
        $output = "[EntityManager]";
        switch($cmd->getName()){
            case "제거":
                self::clearEntity($i instanceof Player ? $i->getLevel() : null, [BaseEntity::class, Arrow::class]);
                $output .= "소환된 엔티티를 모두 제거했어요";
                break;
            case "체크":
                $mob = [];
                $animal = [];
                $item = [];
                $arrow = [];
                $level = $i instanceof Player ? $i->getLevel() : $this->getServer()->getDefaultLevel();
                foreach($level->getEntities() as $id => $ent){
                    if($ent instanceof Monster){
                        $mob[$id] = $ent;
                    }elseif($ent instanceof Animal){
                        $animal[$id] = $ent;
                    }elseif($ent instanceof ItemEntity){
                        $item[$id] = $ent;
                    }elseif($ent instanceof Arrow){
                        $arrow[$id] = $ent;
                    }
                }
                $output = "--- Level \"{$level->getName()}\" 에 있는 엔티티 ---\n";
                $output .= TextFormat::YELLOW . "Monster: " . count($mob) . "\n";
                $output .= TextFormat::YELLOW . "Animal: " . count($animal) . "\n";
                $output .= TextFormat::YELLOW . "Items: " . count($item) . "\n";
                $output .= TextFormat::YELLOW . "Arrows: " . count($arrow) . "\n";
                break;
            case "스폰":
                if(!is_numeric($sub[0]) and gettype($sub[0]) !== "string"){
                    $output .= "엔티티 이름이 올바르지 않아요";
                    break;
                }
                if(count($sub) >= 4){
                    $level = $this->getServer()->getDefaultLevel();
                    if(isset($sub[4]) && ($k = $this->getServer()->getLevelByName($sub[4]))){
                        $level = $k;
                    }elseif($i instanceof Player){
                        $level = $i->getLevel(); 
                    }
                    $pos = new Position($sub[1], $sub[2], $sub[3], $level);
                }elseif($i instanceof Player){
                    $pos = $i->getPosition();
                }
                
                if(!isset($pos) || self::createEntity($sub[0], $pos) == null){
                    $output .= "사용법: /스폰 <id|name> (x) (y) (z) (level)";
                }
                break;
        }
        $i->sendMessage($output);
        return true;
    }

}