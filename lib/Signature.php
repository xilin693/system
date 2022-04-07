<?php

namespace king\lib;

use king\core\Instance;
use king\lib\exception\AccessDeniedHttpException;

class Signature extends Instance
{
    protected $sign_key;
    protected $sign_parameter;
    protected $expire_parameter;
    protected $expire_time;

    public function __construct($sign_config = 'sign.default')
    {
        $default_config = [
            'sign_key' => 'signature',
            'sign_parameter' => 'sign',
            'expire_parameter' => 'time',
            'expire_time' => 60,
        ];

        $config = C($sign_config);
        if (is_array($config)) {
            foreach ($default_config as $key => $value) {
                if (!isset($config[$key]) || empty($config[$key])) {
                    throw new AccessDeniedHttpException('The ' . $key . ' is empty.');
                } else {
                    $this->$key = $config[$key];
                }
            }
        }
    }

    public function validate($query = '')
    {
        if (!is_array($query)) {
            $method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
            if ($method == 'post') {
                $query = P();
            } elseif ($method == 'put') {
                $query = json_decode(put(), true);
            } else {
                $query = G();
            }

            $query[$this->sign_parameter] = H($this->sign_parameter) ?? ($query[$this->sign_parameter] ?? '');
            $query[$this->expire_parameter] = H($this->expire_parameter) ?? ($query[$this->expire_parameter] ?? '');
        }

        if (empty($query[$this->sign_parameter]) || empty($query[$this->expire_parameter])) {
            return false;
        }

        if ($this->isRequestTimeOut($query)) {
            return false;
        }

        return $query[$this->sign_parameter] === $this->create($query) ? true : false;
    }

    public function create($query)
    {
        if (empty($query)) {
            return false;
        }

        ksort($query);
        $sign = array();
        foreach ($query as $key => $val) {
            if (!is_array($val) && $val !== null && $this->sign_parameter != $key) {
                $sign[] = $key . '=' . $val;
            }
        }

        return md5(implode(':', $sign) . $this->sign_key);
    }

    public function encryptString($query)
    {
        if (empty($query)) {
            return false;
        }

        ksort($query);
        $sign = [];
        foreach ($query as $key => $val) {
            if (!is_array($val) && $val !== null && $this->sign_parameter !== $key) {
                $sign[] = $key . '=' . $val;
            }
        }

        return implode(':', $sign) . $this->sign_key;
    }

    protected function isRequestTimeOut($query)
    {
        return time() - $query[$this->expire_parameter] >= $this->expire_time;
    }
}