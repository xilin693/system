<?php

namespace king\lib;

class Cache
{
    private static $instances = [];

    public static function link($connection = '', $prefix = '')
    {
        $connection = $connection ?: 'cache.redis';
        if (!$prefix) {
            $prefix = C($connection)['prefix'] ?? '';
        }

        $cache_type = C($connection)['cache_type'] ?? 'redis';
        if (!isset(self::$instances[$connection])) {
            $class = 'king\lib\cache\\' . ucfirst($cache_type);
            self::$instances[$connection] = new $class($connection);
        }

        if ($cache_type == 'redis') {
            self::$instances[$connection]->setOption(\Redis::OPT_PREFIX, $prefix);
        }

        return self::$instances[$connection];
    }

    public static function __callStatic($method, $params)
    {
        $connection = static::$connection ?? '';
        if ($method == 'setConfig') {
            $connection = $params[0];
        }

        $prefix = static::$prefix ?? '';
        return call_user_func_array([self::link($connection, $prefix), $method], $params);
    }
}