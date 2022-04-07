<?php

namespace king\lib;

use king\lib\exception\BadRequestHttpException;

class Lang
{
    private static $cache_files = [];

    public static function get($attr, $lang = '')
    {
        if (isset($attr[1][0])) {
            $label = $attr[1][0];
            $pos = strpos($label, ',raw'); // 带,raw就不进行语言转换,直接输出
            if ($pos !== false) {
                return substr($label,0, -4);
            }
        }
        
        $lang_locate = C('lang');
        if (empty($lang_locate)) {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                preg_match('/^([a-z\d\-]+)/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches);
                $lang_locate = strtolower($matches[1]);
            } else {
                $lang_locate = 'zh-cn';
            }
        }

        $sys_lang_file = SYS_PATH . 'lib/lang/' . $lang_locate . EXT;
        if (is_file($sys_lang_file)) {
            $langs = require $sys_lang_file;
        } else {
            $langs = require SYS_PATH . 'lib/lang/zh-cn' . EXT;
        }

        $lang_file = APP_PATH . 'lang/' . $lang_locate . EXT;
        $langs_custom = [];
        if (is_file($lang_file)) {
            $langs_custom = require $lang_file;
        }

        $langs = array_merge($langs, $langs_custom);
        if (!is_array($attr)) {
            return $langs[$attr];
        } else {
            return vsprintf($langs[$attr[0]], $attr[1]);
        }
    }

    public static function locale($attr, $lang = '')
    {
        $locate = C('locale') ?: 'zh-cn';
        $lang_dir = APP_PATH . 'lang/' . $locate;
        $vars = explode('.', $attr);
        $file = $lang_dir . '/' . $vars[0] . EXT;
        if (!isset(self::$cache_files[$file])) {
            if (!is_file($file)) {
                throw new BadRequestHttpException('找不到语言包');
            } else {
                self::$cache_files[$file] = require $file;
            }
        }

        $lang_array = self::$cache_files[$file];
        if (isset($lang_array[$vars[1]])) {
            return $lang_array[$vars[1]];
        } else {
            return;
        }
    }
}