<?php

namespace king\core;

use king\lib\Hook;

require SYS_PATH . 'core' . DS . 'functions.php';

class Loader
{
    private static $psr4 = [];
    public static $cache_file = [];
    public static $class_map = [];
    public static $method;
    public static $run_class;

    public static function autoload($class)
    {
        self::getPsr4();
        $file = self::findFile($class);
        if (is_file($file)) {
            require $file;
            return true;
        } else {
            return false;
        }
    }

    private static function getPsr4()
    {
        if (count(self::$psr4) < 1) {
            self::$psr4 = [
                'king' => realpath(SYS_PATH),
                'app' => realPath(APP_PATH),
                'tests' => realPath(TEST_PATH),
            ];

            if (C('use_composer') != false) {
                $file = VENDOR_PATH . 'composer' . DS . 'autoload_psr4' . EXT;
                if (is_file($file)) {
                    $array = require $file;
                    foreach ($array as $key => $value) {
                        self::$psr4[rtrim($key, '\\')] = $value[0];
                    }
                }
            }
        }
    }

    private static function findFile($class)
    {
        $prefix_dir = '';
        if (array_key_exists($class, self::$class_map)) {
            $file_path = self::$class_map[$class] . EXT;
        } else {
            $pos = strpos($class, '\\');
            $root_namespace = substr($class, 0, $pos);
            if (!isset(self::$psr4[$root_namespace])) {
                $root_namespace = substr($class, 0, strpos($class, '\\', ($pos + 1)));
            }

            if (!$root_namespace) {
                // Error::showError('Class : "' . $class . '" was not found');
            }
            $prefix_dir = self::$psr4[$root_namespace] ?? '';
            $file_path = substr($class, strlen($root_namespace)) . EXT;
        }
        return strtr($prefix_dir . $file_path, '\\', DS);
    }

    public static function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }

    public static function run($uri = '', $cli = '')
    {
        $segs = Route::getSegs($uri, $cli);
        if (is_array($segs)) {
            $class_name = $segs['class'];
            $method = $segs['method'] ?: 'index';
            $methods = explode('?', $method);
            self::$run_class = $class_name;
            try {
                $reflect = new \ReflectionClass($class_name);
            } catch (\ReflectionException $e) {
                Error::showError($e->getMessage());
            }

            $func = $methods[0];
            if ($ex = $reflect->hasMethod($func)) {
                if (!$ex) {
                    $func = '__call';
                }
                Loader::$method = $func;

                if (PHP_SAPI !== 'cli') {
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    ob_start();
                }

                Hook::begin(C('permission'));
                \king\core\Instance::make($class_name, $func, $segs['call_args']);
                $reponse = ['class' => $class_name, 'method' => $func, 'args' => $segs['call_args']];
                Hook::listen(C('app_end'), $reponse);
            } else {
                Error::showError('方法错误：' . $class_name . '.' . $func);
            }
        }
        else {
            Error::showError('方法获取失败：' . $segs);
        }
    }
}
