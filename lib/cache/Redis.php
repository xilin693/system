<?php

namespace king\lib\cache;

use king\core\Error;

class Redis extends \Redis
{
    protected $redis;

    public function __construct($connection = 'cache.redis')
    {
        $default_config = [
            'host' => '127.0.0.1',
            'password' => '',
            'port' => 6379,
            'db' => 0
        ];
        $config = C($connection);
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                if (isset($default_config[$key])) {
                    $default_config[$key] = $value;
                }
            }
        }

        try {
            $success = $this->connect($default_config['host'], $default_config['port'], 3);
            if (!$success) {
                Error::showError('Cache: Redis 连接失败，请检查配置。');
            }

            if (!empty($default_config['password']) && !$this->auth($default_config['password'])) {
                Error::showError('Cache: Redis验证失败。');
            }

            $this->select($default_config['db']);
            return $this;
        } catch (\RedisException $e) {
            Error::showError('Cache: Redis 连接被拒绝 (' . $e->getMessage() . ')');
        }
    }

    public function sel($db)
    {
        $this->select($db);
        return $this;
    }

    public function setConfig($config)
    {
        return $this;
    }
}