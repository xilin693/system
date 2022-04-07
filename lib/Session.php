<?php

namespace king\lib;

use king\core\Instance;

class Session extends Instance
{
    public static $mock_session_set;
    public static $mock_session_get;

    public function __construct()
    {
        $config = C('session.*');
        if ($config['driver'] == 'redis') {
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path', $config['save_path']);
        }

        if (isset($config['expire'])) {
            ini_set('session.gc_maxlifetime', $config['expire']);
            ini_set('session.cookie_lifetime', $config['expire']);
        }

        session_start();
    }

    public function session_id($id)
    {
        session_id($id);
        session_start();
    }

    public static function set($name, $value = '', $prefix = null)
    {
        if (ENV == 'testing') {
            return self::$mock_session_set[$name];
        } else {
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                $_SESSION[$name1][$name2] = $value;
            } else {
                $_SESSION[$name] = $value;
            }
        }
    }

    public static function get($name = '')
    {
        if (ENV == 'testing') {
            return self::$mock_session_get[$name];
        } else {
            if (strpos($name, '.')) {
                list($name1, $name2) = explode('.', $name);
                $value = isset($_SESSION[$name1][$name2]) ? $_SESSION[$name1][$name2] : null;
            } else {
                $value = isset($_SESSION[$name]) ? $_SESSION[$name] : null;
            }
            return $value;
        }
    }

    public static function delete($name)
    {
        if (is_array($name)) {
            foreach ($name as $key) {
                self::delete($key);
            }
        } elseif (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            unset($_SESSION[$name1][$name2]);
        } else {
            unset($_SESSION[$name]);
        }
    }

    public function clear($prefix = null)
    {
        $_SESSION = [];
        return;
    }

    public function has($name, $prefix = null)
    {
        if (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            return isset($_SESSION[$name1][$name2]);
        } else {
            return isset($_SESSION[$name]);
        }
    }

    public static function destroy()
    {
        if (!empty($_SESSION)) {
            $_SESSION = [];
        }
        session_unset();
        session_destroy();
    }

}