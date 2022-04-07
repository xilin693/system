<?php

namespace king\core;

use king\core\Db;
use king\lib\swoole\Connection as Pool;

class Link
{
    private static $instances = [];

    public static function init($db_set, $class, $attr, $master)
    {
        if (!isset(self::$instances[$db_set])) {
            self::$instances[$db_set] = new Db($db_set);
        }

        self::$instances[$db_set]->class = $class;
        self::$instances[$db_set]->attr = $attr;
        self::$instances[$db_set]->master = $master;
        return self::$instances[$db_set];
    }
}