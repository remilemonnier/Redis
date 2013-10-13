# component used to access Redis

## install

```shell
$ curl -s http://getcomposer.org/installer | php
$ php composer.phar install
```

## feature

Throught event on each redis command when an eventDispatcher is setted.

```
$this->setEventDispatcher($eventDispatcher, $eventClass);
```

### cache mode

```php
M6\Component\Redis\Cache
```

Set redis command to different server with a simple consistent hashing algorithm.

```
    $server_config = array(
                'php50' => array (
                    'ip' => '127.0.0.1',
                    'port' => 6379,
                    ),
        'phpraoul' => array (  // bad server
            'ip' => '1.2.3.4',
            'port' => 6379,
            ),
        'php51' => array (
            'ip' => '127.0.0.1',
            'port' => 6379,
            )
        );
        $redis = new redis\Cache(array(
            'timeout' => self::TIMEOUT,
            'server_config' => $server_config,
            'namespace' => self::SPACENAME
            ),
        $redis->set('foo', 'bar');
        $foo = $redis->get('foo');
        if ($redis->exists('raoul')) die('ho mon dieu il existe vraiment !');
        $redis->multi()->set('foo', 'bar2');
        $redis->watch('raoul');
        $raoul = UnClasse::WorkHard();
        $redis->set('raoul', $raoul); // wont do anything if the raoul key is modified
        $redis->exec(); // execute all sets since the multi instructions
        $redis->incr('compteur');
        $redis-decr('compteur');
```

Check unit test for more informations.

### db mode

```php
M6\Component\Redis\DB
```

Access to the predis object simply calling the predis methods.

## test

```shell
$ ./vendor/bin/atoum -d tests
```

Unfortunatly you need redis setuped on localhost for all the tests working.



## TODO
* use SPLQueue
* refactor unit tests