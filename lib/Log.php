<?php

namespace king\lib;

use king\core\Instance;
use king\lib\Es as esLib;
use king\lib\Env;

class Log extends Instance
{
    private static $custom_log_prefix = APP_PATH . 'log/';
    public $default_config = [];
    public $es;

    public function __construct($config)
    {
        $this->es = esLib::getClass($config);
    }

    public static function write($str, $file = 'custom.log', $log_dir = '')
    {
        if (!empty($log_dir)) {
            $dir = self::$custom_log_prefix . rtrim($log_dir ,'/') . '/';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        } else {
            $dir = self::$custom_log_prefix;
        }

        $file_path = $dir . date('Y-m-d') . '_' . $file;
        if (!$fp = fopen($file_path, 'a')) {
            return false;
        }

        fwrite($fp, $str . "\r\n");
        fclose($fp);
    }

    public static function read($file = 'custom.log')
    {
        $file_path = self::$custom_log_prefix . date('Y-m-d') . '_' . $file;
        if (is_file($file_path)) {
            $file = file($file_path);
            $log = '';
            foreach ($file as $line => $str) {
                $log .= $line . ' ' . $str . '<br>';
            }
            return $log;
        } else {
            exit('日志不存在');
        }
    }

    public function esWrite($str)
    {
        if (!$this->es->existIndex()) {
            $properties = [
                'id' => [
                    'type' => 'integer',
                ],
                'host' => [
                    'type' => 'keyword',
                ],
                'content' => [
                    'type' => 'text',
                ],
                'created_time' => [
                    'type' => 'date',
                    'format' => 'YYYY-mm-dd HH:mm:ss'
                ]
            ];
            $this->es->createIndex($properties, 1);
        }

        $params = [
            'host' => $_SERVER['REMOTE_ADDR'],
            'content' => $str,
            'created_time' => date('Y-m-d H:i:s')
        ];

        return $this->es->addDoc($params);
    }
}