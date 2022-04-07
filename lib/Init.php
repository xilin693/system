<?php

namespace king\lib;

use king\lib\template;

class Init
{
    private static $generate = [
        'folder' => ['public', 'application', 'application/cache', 'application/config', 'application/controller'
            , 'application/controller/www', 'application/helper', 'application/lang', 'application/log'
            , 'application/model', 'application/service', 'application/validate', 'application/view/common'],
        'file' => ['public/index.php', 'application/config/config.php', 'application/config/database.php',
            'application/config/route.php', 'application/config/jwt.php', 'application/controller/www/Test.php',
            'application/log/.gitignore', 'application/view/common/error.php', 'application/.env']
    ];

    public static function makeFile()
    {
        foreach (self::$generate as $key => $item) {
            if ($key == 'folder') {
                foreach ($item as $folder) {
                    $real_folder = ROOT_PATH . '/' . $folder;
                    if (!is_dir($real_folder)) {
                        if ($item == 'application/log') {
                            mkdir($real_folder, 0777, true);
                        } else {
                            mkdir($real_folder, 0755, true);
                        }
                    }
                }

            } else if ($key == 'file') {
                foreach ($item as $file) {
                    $real_file = ROOT_PATH . '/' . $file;
                    if (!is_file($real_file)) {
                        $filename = basename($real_file);
                        $template_folder = SYS_PATH . 'lib/template/';
                        $files = explode('.', $filename);
                        $template_file = strtolower($files[0] ?: $files[1]) . '.vm';
                        file_put_contents($real_file, file_get_contents($template_folder . $template_file));
                    }
                }
            }
        }
    }
}
