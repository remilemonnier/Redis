<?php
/**
 * @author o_mansour
 */
namespace M6Web\Component\Redis;
include_once __DIR__.'/../../../../vendor/autoload.php';

/**
 * class used for unit testing U
 */
class CacheTest extends Cache
{
    /**
     * nophpdoc
     * @param int $key clé
     *
     * @return int
     */
    public function MyGetServerId($key)
    {
        return $this->getServerId($key);
    }
}
