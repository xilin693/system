<?php

namespace king\lib;

use king\core\Instance;
use king\core\Route;
use king\core\Error;

class Input extends Instance
{
    private static $auto_xss;
    public static $source_get;
    public static $source_post;
    public static $source_cookie;

    public function __construct()
    {
        $_REQUEST = [];
    }

    public static function closeXss()
    {
        self::$auto_xss = 'none';
    }

    public static function getXss()
    {
        if (self::$auto_xss == 'none') {
            return false;
        } else {
            return C('auto_xss');
        }
    }

    public static function site($file = '', $index = false)//返回网站路径
    {
        $domain = C('domain');
        if (substr($domain, 0, 4) != 'http') {
            $domain = https() ? 'https://' . $domain : 'http://' . $domain;
        }
        $domain = rtrim($domain, '/') . '/';
        if (!$index) {
            return $domain . $file;
        } else {
            return $domain . 'index.php/' . $file;
        }
    }

    public static function getArgs()//取得网址所有分区
    {
        return Route::$args;
    }

    public static function libUrl($file = '')//返回library地址
    {
        return self::site('library/' . $file);
    }

    public static function segment($seg, $default = null, $xss = TRUE)//返回网址片区
    {
        $segs = Route::$seg_uri;
        if (is_integer($seg) && $seg >= 1)//如果是数字就直接返回该数字片区对应的值
        {
            $index = $seg - 1;
        } else {
            $index = array_search($seg, $segs);
            if ($index === false) {
                return '';
            }
            $index += 1;
        }

        if (isset($segs[$index])) {
            return $xss ? self::clearValue($segs[$index]) : $segs[$index];
        } else
            return $default;
    }

    public static function redirect($uri = '', $method = '302')//网址跳转
    {
        $codes = [
            'refresh' => 'Refresh',
            '300' => 'Multiple Choices',
            '301' => 'Moved Permanently',
            '302' => 'Found',
            '303' => 'See Other',
            '304' => 'Not Modified',
            '305' => 'Use Proxy',
            '307' => 'Temporary Redirect'
        ];

        $method = isset($codes[$method]) ? (string)$method : '302';

        if ($method === 'refresh') {
            header('Refresh: 0; url=' . $uri);
        } else {
            header('HTTP/1.1 ' . $method . ' ' . $codes[$method]);
            header('Location: ' . input::site($uri));
        }
        exit;
    }

    public static function direct($uri = '', $method = '302')//网址跳转
    {
        $codes = array(
            'refresh' => 'Refresh',
            '300' => 'Multiple Choices',
            '301' => 'Moved Permanently',
            '302' => 'Found',
            '303' => 'See Other',
            '304' => 'Not Modified',
            '305' => 'Use Proxy',
            '307' => 'Temporary Redirect'
        );

        $method = isset($codes[$method]) ? (string)$method : '302';

        if ($method === 'refresh') {
            header('Refresh: 0; url=' . $uri);
        } else {
            header('HTTP/1.1 ' . $method . ' ' . $codes[$method]);
            header('Location: ' . $uri);
        }
        exit;
    }

    private static function clearValue($str)
    {
        if (is_array($str)) {
            $array = [];
            foreach ($str as $key => $val) {
                $array[self::clearKey($key)] = self::clearValue($val);
            }
            return $array;
        } else {
            if (self::$auto_xss) {
                $str = self::basicClean($str);
            }
            return $str;
        }
    }

    public static function get($key = '', $default = null, $xss = true)
    {
        if (is_array($_GET)) {
            self::$source_get = $_GET;
            foreach ($_GET as $k => $v) {
                $_GET[self::clearKey($k)] = self::clearValue($v);
            }
        }

        if (!isset($key) || empty($key)) {
            return $_GET;
        } else {
            if (isset($_GET[$key])) {
                return $xss ? $_GET[$key] : self::$source_get[$key];
            } else {
                return $default;
            }
        }
    }

    public static function post($key = '', $default = null, $xss = true)
    {
        if (is_array($_POST)) {
            self::$source_post = $_POST;
            foreach ($_POST as $k => $v) {
                $_POST[self::clearKey($k)] = self::clearValue($v);
            }
        }

        if (!isset($key) || empty($key)) {
            return $_POST;
        } else {
            if (isset($_POST[$key])) {
                return $xss ? $_POST[$key] : self::$source_post[$key];
            } else {
                return $default;
            }
        }
    }

    public static function cookie($key = [])
    {
        if (is_array($_COOKIE)) {
            self::$source_cookie = $_COOKIE;
            foreach ($_COOKIE as $k => $v) {
                $_COOKIE[self::clearKey($k)] = self::clearValue($v);
            }
        }

        if (!isset($key) || empty($key)) {
            return $_COOKIE;
        } else {
            if (isset($_COOKIE[$key]))
                return $_COOKIE[$key];
            else
                return false;
        }
    }

    private static function clearKey($str)//安全性处理
    {
        if (!preg_match('/^[a-zA-Z0-9:_.-]+$/u', $str)) {
            Error::showError('Sorry,not allowed');
        }
        return $str;
    }

    public static function ipAddr()//取得ip地址
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ipaddress = $ips[0];
            } else {
                $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        if (!self::validIp($ipaddress)) {
            $ipaddress = '0.0.0.0';
        }

        return $ipaddress;
    }


    private static function validIp($ip, $ipv6 = false, $allow_private = TRUE)
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        if ($allow_private === TRUE) {
            $flags = FILTER_FLAG_NO_RES_RANGE;
        }

        if ($ipv6 === TRUE) {
            return (bool)filter_var($ip, FILTER_VALIDATE_IP, $flags);
        }

        return (bool)filter_var($ip, FILTER_VALIDATE_IP);
    }

    private static function basicClean($string)
    {
        if (get_magic_quotes_gpc()) {
            $string = stripslashes($string);
        }
        $string = str_replace(["&amp;", "&lt;", "&gt;"], ["&amp;amp;", "&amp;lt;", "&amp;gt;"], $string);
        // fix &entitiy\n;
        $string = preg_replace('#(&\#*\w+)[\x00-\x20]+;#u', "$1;", $string);
        $string = preg_replace('#(&\#x*)([0-9A-F]+);*#iu', "$1$2;", $string);
        $string = html_entity_decode($string, ENT_COMPAT, "UTF-8");

        // remove any attribute starting with "on" or xmlns
        $string = preg_replace('#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>#iUu', "$1>", $string);

        // remove javascript: and vbscript: protocol
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu', '$1=$2nojavascript...', $string);
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu', '$1=$2novbscript...', $string);
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*-moz-binding[\x00-\x20]*:#Uu', '$1=$2nomozbinding...', $string);
        $string = preg_replace('#([a-z]*)[\x00-\x20\/]*=[\x00-\x20\/]*([\`\'\"]*)[\x00-\x20\/]*data[\x00-\x20]*:#Uu', '$1=$2nodata...', $string);

        //remove any style attributes, IE allows too much stupid things in them, eg.
        //<span style="width: expression(alert('Ping!'));"></span>
        // and in general you really don't want style declarations in your UGC

        $string = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $string);
        $string = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $string);
        $string = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $string);


        //remove namespaced elements (we do not need them...)
        $string = preg_replace('#</*\w+:\w[^>]*>#i', "", $string);
        //remove really unwanted tags

        do {
            $oldstring = $string;
            $string = preg_replace('#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>#i', "", $string);
        } while ($oldstring != $string);

        return $string;
    }

    private static function removeMagicQuotes($data)
    {
        if (get_magic_quotes_gpc()) {
            $newdata = [];
            foreach ($data as $name => $value) {
                $name = stripslashes($name);
                if (is_array($value)) {
                    $newdata[$name] = self::removeMagicQuotes($value);
                } else {
                    $newdata[$name] = stripslashes($value);
                }
            }
            return $newdata;
        }
        return $data;
    }

}