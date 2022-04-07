<?php

namespace king\lib\cache;

class File
{
    private $cache_dir = APP_PATH . 'lite';
    private $expire = 86400;

    public function __construct($cache_config = 'cache.file')
    {
        $options = ['expire', 'cache_dir'];
        $config = C($cache_config);
        if (!empty($config['file'])) {
            $available_options = $config['file'];
            foreach ($available_options as $key => $value) {
                if (in_array($key, $options)) {
                    $this->$key = $available_options[$key];
                }
            }
        }
    }

    public function get($id)
    {
        $file_name = $this->getFileName($id);
        if (!is_file($file_name) || !is_readable($file_name)) {
            return false;
        }

        $lines    = file($file_name);
        $lifetime = array_shift($lines);
        $lifetime = (int) trim($lifetime);
        if ($lifetime !== 0 && $lifetime < time()) {
            @unlink($file_name);
            return false;
        }

        $serialized = join('', $lines);
        $data       = unserialize($serialized);
        return $data;
    }

    public function delete($id)
    {
        $file_name = $this->getFileName($id);
        return unlink($file_name);
    }

    public function save($id, $data, $lifetime = '')
    {
        $lifetime = ($lifetime !== '') ? $lifetime : $this->expire;
        $dir = $this->getDirectory($id);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
        }

        $file_name  = $this->getFileName($id);
        $lifetime   = ($lifetime === 0) ? 0 : (time() + $lifetime);
        $serialized = serialize($data);
        $result     = file_put_contents($file_name, $lifetime . PHP_EOL . $serialized);
        if ($result === false) {
            return false;
        }
        return true;
    }

    protected function getDirectory($id)
    {
        $hash = sha1($id, false);
        $dirs = [
            $this->getCacheDirectory(),
            substr($hash, 0, 2),
            substr($hash, 2, 2)
        ];
        return join(DIRECTORY_SEPARATOR, $dirs);
    }

    protected function getCacheDirectory()
    {
        return $this->cache_dir;
    }

    protected function getFileName($id)
    {
        $directory = $this->getDirectory($id);
        $hash      = sha1($id, false);
        $file      = $directory . DIRECTORY_SEPARATOR . $hash . '.cache';
        return $file;
    }

    public function setConfig($config)
    {
        return $this;
    }
}
