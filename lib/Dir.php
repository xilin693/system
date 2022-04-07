<?php

namespace king\lib;

use king\core\Instance;
use king\lib\File;

class Dir extends Instance
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function changeDirExt($new_ext)
    {
        $files = self::getFiles($this->path);
        foreach ($files as $file) {
            File::changeExt($file, $new_ext);
        }
    }

    public static function getFiles($dir)
    {
        static $result;
        foreach (new \DirectoryIterator($dir) as $file_info) {
            if (!$file_info->isDot()) {
                $file_path = $dir . '/' . $file_info->getFilename();
                if (is_file($file_path)) {
                    $result[] = $file_path;
                }

                if ($file_info->isDir()) {
                    self::getFiles($file_info->getPathname());
                }
            }
        }
        return $result;
    }
}
