<?php
/**
 *
 * @author o_mansour
 */
namespace M6Web\Component\Redis;

/**
 * classe implémentant Rédis comme un DB
 */
class DB extends Manager
{


    /**
     * constructor - db is hardcoded
     * @param array $params      Manager parameters
     * @param bool  $purgeStatic do we have to purge the static server list ?
     */
    public function __construct($params, $purgeStatic = false)
    {
        $maxNbServer = 1;
        $this->setCurrentDb(1); // default hardcoded choice for the db
        if (count($params['server_config']) > $maxNbServer) {
            throw new Exception("cant declare more of ".$maxNbServer." ".count($params['server_config'])." found ");
        }
        if (isset($params['compress']) and ($params['compress'] === true)) {
            throw new Exception("cant use the compress option in this class");
        }
        if (isset($params['namespace'])) {
            throw new Exception("cant use the namespace option in this class");
        }

        return parent::__construct($params, $purgeStatic);
    }

    /**
     * @deprecated
     * @param integer $v
     *
     * @return object DB
     */
    public function setCurrentDb($v)
    {
        if (!is_int($v)) {
            throw new Exception("la db doit être décrite par un entier ^^");
        }
        if ($v == Cache::CACHE) {
            throw new Exception("cant use ".Cache::CACHE." in class ".__CLASS__);
        }
        $this->currentDb = $v;

        return $this;
    }

    /**
     * return a predis object
     * @param integer $serverRank server rank
     *
     * @return \Redis
     */
    public function getRedisObject($serverRank = 0)
    {
        $serverConfig = $this->getServerConfig();

        $keys = array_keys($serverConfig);
        if (isset($keys[$serverRank])) {
            return $this->getRedisFromServerConfig($keys[$serverRank]);
        } else {
            throw new Exception("No server found at rank ".$serverRank);
        }
    }

    /**
     *  magic method to the \Redis() proxy
     *
     * @param string $name      method name
     * @param array  $arguments method arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($redis = $this->getRedisObject()) {
            $start = microtime(true);
            try {
                $ret = call_user_func_array(array($redis, $name), $arguments);
                $this->notifyEvent($name, $arguments, microtime(true) - $start);

                return $ret;
            } catch (\Predis\ClientException $e) {
                throw new Exception("Error calling the method ".$name." : ".$e->getMessage());
            }
        } else {
            throw new Exception("Cant connect to Redis");
        }
    }
}
