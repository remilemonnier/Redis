<?php
/**
 * This class require a redis setuped on localhost
 */

namespace M6Web\Component\Redis\tests\units;

include_once __DIR__.'/../../../../vendor/autoload.php';

use \mageekguy\atoum;
use \M6Web\Component\Redis;

class Multi extends atoum\test
{

    const spacename = 'testCacheMulti';

    private function getServerConfig($config)
    {
        if ($config == 'many') {
            return array(
                'php51' => array (
                    'ip' => '127.0.0.1',
                    'port' => 6379,
                ),
                'php52' => array (
                'ip' => '127.0.0.1',
                'port' => 6379,
                )
            );
        }
        if ($config == 'manywrong') {
            return array(
                'php51' => array (
                    'ip' => '127.0.0.1',
                    'port' => 6379,
                ),
                'php52' => array (
                    'ip' => '1.0.0.1',
                    'port' => 6379,
                )
            );
        }
        if ($config == 'wrong') {
            return array(
                'phpraoul' => array (  // mauvais server
                    'ip' => '1.2.3.4',
                    'port' => 6379,
                ),
            );
        }
        if ($config == 'unavailable') {
            return array(
                'phpraoul' => array (  // mauvais server
                    'ip' => '1.2.3.4',
                    'port' => 6379,
                ),
                'phpraoul2' => array (  // mauvais server
                    'ip' => '1.2.3.5',
                    'port' => 6379,
                ),
            );
        }
        throw new \Exception("one or wrong can be accessed via ".__METHOD__." not : ".$config);
    }



    public function testWorking()
    {
        $server_config = $this->getServerConfig('many');

        $this->assert
            ->if($redis = new redis\Multi([
                'timeout' => 0.1,
                'server_config' => $server_config
            ]))
            ->then($redis->onAllServer()->set(self::spacename.'foo', 'bar'))
                ->string($redis->onOneRandomServer()->get(self::spacename.'foo'))
                    ->isEqualTo('bar');
    }

    public function testNoRandomServerAvailable()
    {
        $server_config = $this->getServerConfig('unavailable');

        $this->assert
            ->if($redis = new redis\Multi([
                'timeout' => 0.1,
                'server_config' => $server_config
            ]))
            ->then
                ->exception(
                    function() use ($redis) {
                        $redis->onOneRandomServer()->get(self::spacename.'foo');
                    }
                )
                ->isInstanceOf('\M6Web\Component\Redis\Exception')
                    ->hasMessage("Can't connect to a random redis server");
    }

    public function testManyWrong()
    {
        $server_config = $this->getServerConfig('manywrong');
        $this->assert
            ->if($redis = new redis\Multi([
                'timeout' => 0.1,
                'server_config' => $server_config
            ]))
            ->then()
            ->exception(
                function() use ($redis) {
                    $redis->onAllServer()->ping();
                }
            )
            ->isInstanceOf('\M6Web\Component\Redis\Exception')
            ->string($redis->onAllServer($strict = false)->ping());
    }

    public function testBadUsage()
    {
        $server_config = $this->getServerConfig('many');
        $this->assert
            ->if($redis = new redis\Multi([
                    'timeout' => 0.1,
                    'server_config' => $server_config
                ]))
            ->then()
            ->exception(
                function() use ($redis) {
                    $redis->ping();
                }
            )
            ->isInstanceOf('\M6Web\Component\Redis\Exception');
    }

    public function tearDown()
    {
        $server_config = $this->getServerConfig('many');
        $r = new redis\Multi(array(
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
} 