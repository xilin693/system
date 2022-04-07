<?php

namespace king;

use king\core\Loader;
use king\lib\Es as Instance;
use king\lib\Env;

class Es
{
    public static $instance = [];

    public static function connect()
    {
        $set = static::$set ?? '';
        $config = static::setConfig($set);
        $key = md5(serialize($config));
        if (!isset(self::$instance[$key])) {
            self::$instance[$key] = new Instance($config);
        }

        return self::$instance[$key];
    }

    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::connect(), $method], $params);
    }
}