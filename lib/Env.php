<?php

namespace king\lib;

class Env
{
    public static function loadFile($file)
    {
        if (is_file($file)) {
            $env = parse_ini_file($file, true);

            foreach ($env as $key => $val) {
                $name = ENV_PREFIX . strtoupper($key);

                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $item = $name . '_' . strtoupper($k);
                        putenv("$item=$v");
                    }
                } else {
                    putenv("$name=$val");
                }
            }
        }
    }

    public static function get($name, $default = null)
    {
        $rs = getenv(ENV_PREFIX . strtoupper(str_replace('.', '_', $name)));
        if ($rs !== false) {
            return $rs;
        } else {
            return $default;
        }
    }
}


