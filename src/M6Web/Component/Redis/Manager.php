<?php
/**
 * @author Olivier Mansour
 *
 * strongly depend on predis
 *
 */

namespace M6Web\Component\Redis;


/**
 * Manager for Redis classes
 */
abstract class Manager
{

    /**
     * server default timeout
     * @var float
     */
    protected $timeout = 0.2;

    /**
     * array of server configuration
     * @var array
     */
    protected $serverConfig = array();

    /**
     * array of Redis object
     * @var array
     */
    protected static $redisArray = array();

    /**
     * db (as redis understand it)
     * @var integer
     */
    protected $currentDb;

    /**
     * do I use the compression
     * @var bool
     */
    private $compress;

    /**
     * set the current db
     * @param string $v db
     */
    abstract public function setCurrentDb($v);

    /**
     * get the current db
     * @return string / int WTF !!!!!
     */
    public function getCurrentDb()
    {
        if (is_null($this->currentDb)) {
            throw new Exception("currentDb cant be null");
        }

        return $this->currentDb;
    }

    /**
     * event dispatcher
     * @var Object
     */
    protected $eventDispatcher = null;

    /**
     * class of the event notifier
     * @var string
     */
    protected $eventClass = null;

    /**
     * constructor
     * $params = array(
     * 'timeout' => 2,
     * 'compress' => true,
     * 'server_config' = array(
     *   'php50' => array (
     *       'ip' => '193.22.143.110',
     *       'port' => 6379,
     *       ));
     *
     * @param array $params Manager params
     * @param bool $purgeStatic do we have to purge the static array containing the configuration ?
     *
     * @return \M6Web\Component\Redis\Manager
     */
    public function __construct($params, $purgeStatic = false)
    {
        if ($purgeStatic === true) {
            self::$redisArray = array();
        }
        $this->init($params);

        return $this;
    }

    /**
     * Notify an event to the event dispatcher
     * @param string $command   The command name
     * @param array  $arguments args of the command
     * @param int    $time      exec time
     *
     * @return \M6Web\Component\Redis\Manager
     */
    public function notifyEvent($command, $arguments, $time = 0)
    {
        if ($this->eventDispatcher) {
            $className = $this->eventClass;
            $event = new $className();
            $event->setCommand($command);
            $event->setExecutionTime($time);
            $event->setArguments($arguments);
            $this->eventDispatcher->dispatch('redis.command', $event);
        }

        return $this;
    }

    /**
     * Set an event dispatcher to notify redis command
     * @param Object $eventDispatcher The eventDispatcher object, which implement the notify method
     * @param string $eventClass The event class used to create an event and send it to the event dispatcher
     *
     * @throws Exception
     * @return \M6Web\Component\Redis\Manager
     */
    public function setEventDispatcher($eventDispatcher, $eventClass)
    {
        if (!is_object($eventDispatcher) || !method_exists($eventDispatcher, 'dispatch')) {
            throw new Exception("The EventDispatcher must be an object and implement a dispatch method");
        }

        if (!class_exists($eventClass) || !method_exists($eventClass, 'setCommand') || !method_exists($eventClass, 'setArguments') || !method_exists($eventClass, 'setExecutionTime')) {
            throw new Exception("The Event class : ".$eventClass." must implement the setCommand, setExecutionTime and the setArguments method");
        }
        $this->eventDispatcher = $eventDispatcher;
        $this->eventClass      = $eventClass;

        return $this;
    }

    /**
     * init for the class
     * @param array $params confg array
     *
     * @throws Exception
     * @return \M6Web\Component\Redis\Manager
     * @throw Exception
     */
    protected function init($params)
    {
        // check serverConfig
        if (!isset($params['server_config'])) {
            throw new Exception("Le parametre serverConfig est obligatoire");
        }

        $this->setServerConfig($params['server_config']);
        if (isset($params['timeout'])) {
            $this->timeout = $params['timeout'];
        }
        if (isset($params['compress']) and is_bool($params['compress'])) {
            $this->compress = $params['compress'];
        }

        return $this;
    }

    /**
     * retourn compress param
     * @return bool
     */
    public function getCompress()
    {
        return $this->compress;
    }

    /**
     * check the server config
     * @param array $servers host_config
     *
     * @return boolean
     */
    protected function checkServerConfig($servers)
    {
        if (!is_array($servers)) {
            return false;
        }
        foreach ($servers as $serverId => $server) {
            if (!is_string($serverId)) {
                return false;
            }
            if (!isset($server['ip']) or !isset($server['port'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * getTimeout
     *
     * @return float
     */
    public function getTimeout()
    {
        return (float) $this->timeout;
    }

    /**
     * get the server info against servernam or all the config
     * @param int $servername clé
     *
     * @return array
     */
    public function getServerConfig($servername = null)
    {
        if (is_null($servername)) {
            return $this->serverConfig;
        } else {
            return $this->serverConfig[$servername];
        }
    }

    /**
     * set the server config
     * @param array $servers config
     * @param bool $check do I have to check the config
     * @throws Exception
     * @return \M6Web\Component\Redis\Manager
     */
    protected function setServerConfig($servers, $check = true)
    {
        if ($check and (!self::checkServerConfig($servers))) {
            throw new Exception("Le parametre serverConfig est mal formé");
        }
        $this->serverConfig = $servers;

        return $this;
    }

     /**
     * return a server according to the redis key passed
     * @param string $key key
     *
     * @return string
     */
    protected function getServerId($key)
    {
        $server = $this->getServerConfig();
        $serverKeys = array_keys($server); // todo, set a cache ?

        return $serverKeys[(int) (crc32($key) % count($serverKeys))];
    }

    /**
     * return a Redis object according to the key
     * @param string $key cache key
     *
     * @throws Exception
     * @return Redis
     */
    protected function getRedis($key)
    {
        $idServer = $this->getServerId($key);
        $servers = $this->getServerConfig(); // all the servers
        unset($servers[$idServer]); // supress the server that will be tested from the pile
        if (!($redis = $this->getRedisFromServerConfig($idServer))) {
            $this->notifyEvent('redis_host_on_error', array($idServer));
            if (count($servers) == 0) {
                throw new Exception("No redis server available ! ");
            }
            // find another server !!!
            $this->setServerConfig($servers, false); // reinit the object without the not responding server

            return $this->getRedis($key); // RECURSION ^^
        }
        $redis->select($this->getCurrentDb());

        return $redis;
    }

    /**
     * buid a redis server with a config
     * @param string $idServer server id in the configuration
     *
     * @return Redis or false
     */
    public function getRedisFromServerConfig($idServer)
    {
        if (array_key_exists($idServer, self::$redisArray)) {
            return self::$redisArray[$idServer];
        }

        $redis = $this->getNewRedis();
        if (!is_null($redis) and ($this->connectServer($redis, $this->getServerConfig($idServer)))) {
            // peuple le tableau statique
            self::$redisArray[$idServer] = $redis;

            return $redis;
        } else {
            return false;
        }
    }

    /**
     * return a Predis object
     *
     * @throws Exception
     * @return \Predis\Client
     */
    protected function getNewRedis()
    {
        if (class_exists('\Predis\Client')) {
            return new \Predis\Client();
        } else {
            throw new Exception("Cant find the Predis classes");
        }
    }

    /**
     * connecte un server
     * @param object|\Predis\Client $redis \Predis\Client
     * @param array $server array('ip' => , 'port' => , 'timeout' =>)
     *
     * @return boolean
     */
    protected function connectServer(\Predis\Client $redis, $server)
    {
        try {
            $redis->__construct(array(
                'host' => $server['ip'],
                'port' => (int) $server['port'],
                'connection_timeout' => $this->getTimeout()
                ));
                // check if we are connected
            $redis->connect();

            return true;
        } catch (\Predis\Connection\ConnectionException $e) {
            return false;
        }

    }

    /**
     * compress a string
     * @param string $data a data to compress
     *
     * @return string
     */
    protected static function compress($data)
    {
        return gzcompress($data);
    }

    /**
     * uncompress a string
     * @param string $data data to uncompress
     *
     * @return string
     */
    protected static function uncompress($data)
    {
        return gzuncompress($data);
    }


}
