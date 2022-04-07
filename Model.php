<?php

namespace king;

use king\core\Model as Instance;
use king\core\Loader;

class Model
{
    public static $debug = false;

    public static function connect()
    {
        $db_set = static::$db_set ?? 'default';
        $class = static::class ?? '';
        $table = static::$table ?? self::getClassName(static::class);
        $key = static::$key ?? 'id';
        $db = new Instance($class);
        $db->setDb($db_set);
        $db->setTable($table);
        $db->setKey($key);
        return $db;
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
