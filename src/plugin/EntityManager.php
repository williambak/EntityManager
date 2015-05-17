<?php

namespace plugin;

use plugin\AnimalEntity\Animal;
use plugin\AnimalEntity\Chicken;
use plugin\AnimalEntity\Cow;
use plugin\AnimalEntity\Pig;
use plugin\AnimalEntity\Sheep;
use plugin\AnotherEntity\BaseEntity;
use plugin\AnotherEntity\Minecart;
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
use pocketmine\event\entity\EntityDespawnEvent;
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
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

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
        $this->path = self::core()->getDataPath() . "plugins/EntityManager/";
        if(!is_dir($this->path)) mkdir($this->path);
        if(file_exists($this->path. "EntityData.yml")){
            self::$entityData = yaml_parse($this->yaml($this->path . "EntityData.yml"));
        }else{
            self::$entityData = [
                "entity" => [
                    "explode" => true,
                ],
                "spawn" => [
                    "auto" => true,
                    "mob" => true,
                    "animal" => true,
                    "tick" => 150,
                    "radius" => 25
                ],
            ];
            file_put_contents($this->path . "EntityData.yml", yaml_emit(self::$entityData, YAML_UTF8_ENCODING));
        }

        if(file_exists($this->path. "SpawnerData.yml")){
            self::$spawnerData = yaml_parse($this->yaml($this->path . "SpawnerData.yml"));
        }else{
            self::$spawnerData = [];
            file_put_contents($this->path . "SpawnerData.yml", yaml_emit(self::$spawnerData, YAML_UTF8_ENCODING));
        }

        for($a = 10; $a <= 13; $a++){
            $item = Item::get(Item::SPAWN_EGG, $a);
            if(!Item::isCreativeItem($item)) Item::addCreativeItem($item);
        }
        for($a = 32; $a <= 42; $a++){
            $item = Item::get(Item::SPAWN_EGG, $a);
            if(!Item::isCreativeItem($item)) Item::addCreativeItem($item);
        }

        self::core()->getPluginManager()->registerEvents($this, $this);
        self::core()->getLogger()->info(TextFormat::GOLD . "[EntityManager]플러그인이 활성화 되었습니다");
        self::core()->getScheduler()->scheduleRepeatingTask(new EntityManagerTask([$this, "updateEntity"], $this), 1);
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
     * @return BaseEntity[]
     */
    public static function getEntities(){
        return self::$entities;
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
        $level = $level === null ? self::core()->getDefaultLevel() : $level;
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

    public static function getData($key, $default = false){
        $vars = explode(".", $key);
        $base = array_shift($vars);
        if(!isset(self::$entityData[$base])){
            self::$entityData[$base] = $default;
            return $default;
        }
        $base = self::$entityData[$base];
        while(count($vars) > 0){
            $baseKey = array_shift($vars);
            if(!is_array($base) or !isset($base[$baseKey])) return $default;
            $base = $base[$baseKey];
        }
        return $base;
    }

    /**
     * @param int|string $type
     * @param Position $source
     * @param $args
     *
     * @return BaseEntity
     */
    public static function createEntity($type, Position $source, ...$args){
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
        if(isset(self::$knownEntities[$type])){
            $class = self::$knownEntities[$type];
            /** @var BaseEntity $entity */
            $entity =  new $class($chunk, $nbt, ...$args);
            if($entity !== null && $entity->isCreated()) $entity->spawnToAll();
            return $entity;
        }
        return null;
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

    public function updateEntity(){
        if(++$this->tick >= self::getData("spawn.tick", 150)){
            $this->tick = 0;
            foreach(self::$spawnerData as $pos => $data){
                if(mt_rand(1, 3) > 1) continue;
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
                    || ($bb2 !== null and $bb2->maxY - $bb2->minY > 1)
                ) continue;
                self::createEntity($data["mob-list"][mt_rand(0, count($data["mob-list"]) - 1)], Position::fromObject($pos, $level));
            }
            if(self::getData("spawn.auto", true)) foreach(self::core()->getOnlinePlayers() as $player){
                if(mt_rand(1, 10) > 1) continue;
                $level = $player->getLevel();
                $radius = self::getData("spawn.radius", 25);
                $pos = $player->add(mt_rand(-$radius, $radius), 0, mt_rand(-$radius, $radius));
                $pos->y = $level->getHighestBlockAt($pos->x, $pos->z);
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
        if($entity instanceof Animal && !self::getData("spawn.animal", true)){
            $entity->close();
        }elseif($entity instanceof Monster && !self::getData("spawn.mob", true)){
            $entity->close();
        }
        if(!$entity->closed && in_array(get_class($entity), [Minecart::class, BaseEntity::class])) self::$entities[$entity->getId()] = $entity;
    }

    public function EntityDespawnEvent(EntityDespawnEvent $ev){
        $entity = $ev->getEntity();
        if($entity instanceof Animal or $entity instanceof Monster){
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
            self::createEntity($item->getDamage(), $pos);
            if($player->isSurvival()){
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
                self::clearEntity($i instanceof Player ? $i->getLevel() : null, [Animal::class, Monster::class, Arrow::class]);
                $output .= "소환된 엔티티를 모두 제거했어요";
                break;
            case "체크":
                $output .= "현재 소환된 모든 엔티티 수: " . count(self::getEntities());
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

class EntityManagerTask extends PluginTask{

    protected $callable;

    public function __construct(callable $callable, Plugin $owner){
        $this->callable = $callable;
        $this->owner = $owner;
    }

    public function onRun($currentTicks){
        call_user_func_array($this->callable, []);
    }

}