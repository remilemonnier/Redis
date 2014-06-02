<?php

namespace M6Web\Component\Redis;

/**
 * Class Multi
 * Allow you to send a unique command on multiple redis
 *
 * @package M6Web\Component\Redis
 */
class Multi extends Manager
{

    protected $selectedRedis = array();

    /**
     * constructor
     *
     * @param array $params
     *
     * @throws Exception
     */
    public function __construct($params)
    {
        $this->setCurrentDb(1); // default hardcoded choice for the db
        if (count($params['server_config']) <= 1) {
            throw new Exception("you have to declare more than one server in server_config found ");
        }
        if (isset($params['compress']) and ($params['compress'] === true)) {
            throw new Exception("cant use the compress option in this class");
        }
        if (isset($params['namespace'])) {
            throw new Exception("cant use the namespace option in this class");
        }

        return parent::__construct($params);
    }


    /**
     * select one random server
     *
     * @return $this
     */
    public  function onOneRandomServer()
    {
        $keys = array_keys($this->getServerConfig());

        do {
            $randServerRank = array_rand($keys);
            if ($redis = $this->getRedisFromServerConfig($keys[$randServerRank])) {
                $this->selectedRedis = array($redis);
                // got one redis
                break;
            } else {
                unset($keys[$randServerRank]);
            }
        } while (!empty($keys));

        return $this;
    }

    /**
     * select all the servers
     *
     * @param bool $strict
     *
     * @throws Exception
     * @return $this
     */
    public function onAllServer($strict = true)
    {
        foreach ($this->getServerConfig() as $idServer => $config) {
            if ($redis = $this->getRedisFromServerConfig($idServer)) {
                $this->selectedRedis[] = $redis;
            } else {
                if ($strict) {
                    throw new Exception('cant connect to redis '.$idServer);
                }
            }
        }

        return $this;
    }


    /**
     *  magic method to the \Redis() proxy
     *
     * @param string $name      method name
     * @param array  $arguments method arguments
     *
     * @throws Exception
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $toReturn = '';

        if (empty($this->selectedRedis)) {
            throw new Exception("please call onOneRandomServer or onAllServer before ".__METHOD__);
        }

        foreach ($this->selectedRedis as $redis) {
                $start = microtime(true);
                try {
                    $ret = call_user_func_array(array($redis, $name), $arguments);
                    $this->notifyEvent($name, $arguments, microtime(true) - $start);

                    $toReturn .= $ret;
                } catch (\Predis\ClientException $e) {
                    throw new Exception("Error calling the method ".$name." : ".$e->getMessage()." on redis : ".$idServer);
                }
        }
        // reinit selected Redis
        $this->selectedRedis = array();

        return $toReturn;
    }


} 