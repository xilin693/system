<?php

namespace king\lib;

use king\lib\Input;
use king\core\Instance;

class Cookie extends Instance
{

    protected $cookie;
    protected $config = [
        'prefix' => '', // cookie 名称前缀
        'expire' => 0, // cookie 保存时间
        'path' => '/', // cookie 保存路径
        'domain' => '', // cookie 有效域名
        'secure' => false, //  cookie 启用安全传输
        'httponly' => false // httponly 设置
    ];

    public function __construct()
    {
        $config = C('cookie.*');
        if ($config) {
            $this->config = array_merge($this->config, array_change_key_case($config));
        }

        if (!empty($this->config['httponly'])) {
            ini_set('session.cookie_httponly', 1);
        }
    }


    public function set($name, $value, $option = null)
    {
        if (!is_null($option)) {
            if (is_numeric($option)) {
                $option = ['expire' => $option];
            } elseif (is_string($option)) {
                parse_str($option, $option);
            }

            $this->config = array_merge($this->config, array_change_key_case($option));
        }

        $config = $this->config;
        $name = $config['prefix'] . $name;
        $expire = ($config['expire'] == 0) ? 0 : time() + (int)$config['expire'];

        return setcookie($name, $value, $expire, $config['path'], $config['domain'],
            $config['secure'], $config['httponly']);
    }

    public function get($name)
    {
        return Input::cookie($this->config['prefix'] . $name);
    }

    public function delete($name)
    {
        $key = $this->config['prefix'] . $name;
        if (!isset($_COOKIE[$key])) {
            return false;
        }

        unset($_COOKIE[$key]);
        return cookie::set($key, '', -86400, $this->config['path'], $this->config['domain'],
            $this->config['secure'], $this->config['httponly']);
    }

}