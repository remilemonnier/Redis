<?php
/**
 *
 */

namespace M6\Component\Redis\tests\units;

include_once __DIR__.'/../../../../vendor/autoload.php';

require_once __DIR__.'/DispatcherTest.php';
require_once __DIR__.'/EventTest.php';

use \mageekguy\atoum;
use \M6\Component\Redis;

/**
 * @maxChildrenNumber 1
 */
class DB extends atoum\test
{
    const spacename = 'testCacheDB';

    private function getServerConfig($config)
    {
        if ($config == 'one') {
            return array(
                'php51' => array (
            'ip' => '127.0.0.1',
            'port' => 6379,
            ));
        }
        if ($config == 'wrong') {
            return array(
                'phpraoul' => array (  // mauvais server
                    'ip' => '1.2.3.4',
                    'port' => 6379,
                    ),
                );
        }
        throw new \Exception("one or wrong can be accessed via ".__METHOD__." not : ".$config);
    }

    /**
     * test on constructor - cant accept more than one server
     * @return void
     */
    public function testConstructor()
    {
        $server_config = $this->getServerConfig('one') + $this->getServerConfig('wrong');
        $this->assert
            ->exception(
                function() use ($server_config) {
                    $redis = new redis\DB(array(
                        'timeout' => 1,
                        'server_config' => $server_config
                    ));
            })
            ->isInstanceOf('\M6\Component\Redis\Exception');
        $server_config = $this->getServerConfig('one');
        $this->assert
            ->exception(
                function() use ($server_config) {
                    $redis = new redis\DB(array(
                        'timeout' => 1,
                        'compress' => true,
                        'server_config' => $server_config
                    ));
            })
            ->isInstanceOf('\M6\Component\Redis\Exception');
        $this->assert
            ->exception(
                function() use ($server_config) {
                    $redis = new redis\DB(array(
                        'timeout' => 1,
                        'namespace' => 'raoul',
                        'server_config' => $server_config
                    ));
            })
            ->isInstanceOf('\M6\Component\Redis\Exception');
    }

    /**
     * test the predis proxy
     * @return void
     */
    public function testProxy()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new redis\DB(array(
            'timeout' => 0.1,
            'server_config' => $server_config,
            ));
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->raouldemethode('foo');
                })
            ->isInstanceOf('\M6\Component\Redis\Exception');
        $this->assert
            ->boolean($redis->set('raoul', 'v'))
            ->isEqualTo(true);
    }

    public function testSetCurrentDb()
    {
        // include_once __DIR__.'/../src/M6/Component/Redis/Cache.php';
        $server_config = $this->getServerConfig('one');
        $redis = new redis\DB(array(
            'timeout' => 0.1,
            'server_config' => $server_config
            ));
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->setCurrentDb('foo');
                })
            ->isInstanceOf('\M6\Component\Redis\Exception');
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->setCurrentDb(\M6\component\Redis\Cache::CACHE);
                })
            ->isInstanceOf('\M6\Component\Redis\Exception');
        $this->assert
            ->object($redis->setCurrentDb(1))
            ->isInstanceOf('\M6\component\Redis\DB');
    }

    public function testVariousMethod()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new redis\DB(array(
            'timeout' => 0.4,
            'server_config' => $server_config
            ));

        $this->assert
            ->integer($redis->lpush(self::spacename.'foo', 'bar'))
            ->isEqualTo(1);
        $this->assert
            ->integer($redis->lpush(self::spacename.'foo', 'raoul'))
            ->isEqualTo(2);
        $redis->del(self::spacename.'foo');
        $this->assert
            ->integer($redis->zAdd(self::spacename.'foo', 2, 'bar2'))
            ->isEqualTo(1);
        $this->assert
            ->integer($redis->zAdd(self::spacename.'foo', 3, 'bar3'))
            ->isEqualTo(1);
        $this->assert
            ->integer($redis->zAdd(self::spacename.'foo', 1, 'bar1'))
            ->isEqualTo(1);
        $this->assert
            ->array($redis->zRange(self::spacename.'foo', 0, -1))
            ->isEqualTo(array('bar1', 'bar2', 'bar3'));
        $redis->del(self::spacename.'foo');

        // using predis =======
        $redis = new redis\DB(array(
            'timeout' => 0.4,
            'server_config' => $server_config
            ), 'predis', true);
        $this->assert
            ->integer($redis->lPush(self::spacename.'foo', 'bar'))
            ->isEqualTo(1);
        $this->assert
            ->integer($redis->lPush(self::spacename.'foo', 'raoul'))
            ->isEqualTo(2);
        $redis->del(self::spacename.'foo');
        $this->assert
            ->integer($redis->zAdd(self::spacename.'foo', 2, 'bar2'))
            ->isEqualTo(1);
        $this->assert
            ->integer($redis->zAdd(self::spacename.'foo', 3, 'bar3'))
            ->isEqualTo(1);
        $this->assert
            ->integer($redis->zAdd(self::spacename.'foo', 1, 'bar1'))
            ->isEqualTo(1);
        $this->assert
            ->array($redis->zRange(self::spacename.'foo', 0, -1))
            ->isEqualTo(array('bar1', 'bar2', 'bar3'));
        $redis->del(self::spacename.'foo');

    }

    public function testGetRedisObject()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new redis\DB(array(
            'timeout' => 0.4,
            'server_config' => $server_config
            ));
        $this->assert
            ->exception(
                function() use ($redis) {
                    $redis->getRedisObject(1);
                }
            );
        $this->assert
            ->object($redis->getRedisObject())
            ->isInstanceOf('Predis\Client');
        $this->assert
            ->object($redis->getRedisObject(0))
            ->isInstanceOf('Predis\Client');
    }

    public function testNotifyEvent()
    {
        $server_config = $this->getServerConfig('one');
        $r = new redis\DB(array(
            'timeout' => 0.1,
            'server_config' => $server_config
            ));
        $dispatcher = new \mock\M6\Component\Redis\tests\fake\DispatcherTest();
        $dispatcher->getMockController()->dispatch = function() { return true; };
        $this->if($r->setEventDispatcher($dispatcher, '\M6\Component\Redis\tests\fake\EventTest'))
        ->then
        ->variable($r->get('raoul'))
        ->mock($dispatcher)
        ->call('dispatch')
        ->witharguments('redis.command')
        ->once()

        ;
    }

    /**
     * dernière méthode a être appelée
     * nettoyage global
     * @return void
     */
    public function tearDown()
    {
        $server_config = $this->getServerConfig('one');
        $r = new redis\DB(array(
            'timeout' => 0.1,
            'server_config' => $server_config
            ));
        foreach ($r->getServerConfig() as $server_id => $server) {
           if ($redis = $r->getRedisFromServerConfig($server_id)) {
                $all_keys = $redis->keys(self::spacename.'*'); // toutes les clés commençant par le pattern
                if (count($all_keys)) {
                    $redis->del($all_keys);
                }
           }
        }
    }

    public function testHash()
    {
        $server_config = $this->getServerConfig('one');
        $redis = new redis\Db(array(
            'timeout' => 1,
            'server_config' => $server_config
            ));
        $cacheKey = self::spacename.'AtoumTest_'.uniqid();
        $ttl = 60;

        $title = 'titre';
        $lastUpdate = '2013-03-22 11:03:25';
        $array = array('title' => $title, 'lastUpdate' => $lastUpdate);

        $redis->hmset($cacheKey, $array);

        //Est ce que les clés on bien été enregistrer
        $this->string($redis->hget($cacheKey, 'title'))->isIdenticalTo($title);
        $this->string($redis->hget($cacheKey, 'lastUpdate'))->isIdenticalTo($lastUpdate);

        //Est ce que le get multiple renvois bien le nombre de clés set
        $this->integer(count($redis->hgetall($cacheKey)))->isIdenticalTo(2);
        $this->array($redis->hgetall($cacheKey))->isIdenticalTo($array);

        //Test si on récupere bien les bonnes clés du tableau
        $this->array($redis->hkeys($cacheKey))->IsIdenticalTo(array('title', 'lastUpdate'));

        //Est ce que une clé particuliere existe
        $this->boolean($redis->hexists($cacheKey, 'title'))->isIdenticalTo(true);
        $this->boolean($redis->hexists($cacheKey, 'titleee'))->isIdenticalTo(false);

        //Suppression d'un clé du tableau
        $redis->hdel($cacheKey, 'title');

        //La clé a-t-elle bien été supprimé
        $this->boolean($redis->hexists($cacheKey, 'title'))->isIdenticalTo(false);
        $this->boolean($redis->hexists($cacheKey, 'lastUpdate'))->isIdenticalTo(true);
        $this->integer(count($redis->hgetall($cacheKey)))->isIdenticalTo(1);

        $redis->hkeys($cacheKey);

    }

}
