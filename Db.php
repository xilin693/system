<?php

namespace king;

use king\core\Model as Instance;
use king\core\Loader;

class Db
{
    public static $instance = [];
    public static $debug = false;

    public static function connect()
    {
        $db_set = static::$db_set ?? 'default';
        $class = static::class ?? '';
        $table = static::$table ?? self::getClassName(static::class);
        $key = static::$key ?? 'id';
        if (!isset(self::$instance[$class])) {
            self::$instance[$class] = new Instance($class);
            self::$instance[$class]->setDb($db_set);
        }

        self::$instance[$class]->setTable($table);
        self::$instance[$class]->setKey($key);
        return self::$instance[$class];
    }

    private static function getClassName($class)
    {
        $array = explode('\\', $class);
        return strtolower(end($array));
    }

    public static function __callStatic($method, $params)
    {
        return call_user_func_array([self::connect(), $method], $params);
    }
}
