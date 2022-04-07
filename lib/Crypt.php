<?php

namespace king\lib;

use king\core\Instance;
use king\core\Error;

class Crypt extends Instance
{

    private static $key = 'gdaeb';//默认的key值
    public static $crypt_char = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.';

    public static function setKey($new_key)
    {
        self::$key = $new_key;
    }

    /**
     * 加密函数
     * @param 需要加密的字串 $txt
     * @param 密销 $key
     * @return string
     */
    public static function encrypt($txt)
    {
        $key = self::$key;
        $chars = self::$crypt_char;
        $nh1 = rand(0, 64);
        $nh2 = rand(0, 64);
        $nh3 = rand(0, 64);
        $ch1 = $chars[$nh1];
        $ch2 = $chars[$nh2];
        $ch3 = $chars[$nh3];
        $nhnum = $nh1 + $nh2 + $nh3;
        $knum = 0;
        $i = 0;
        while (isset($key[$i])) {
            $knum += ord($key[$i++]);
        }
        $md5_key = substr(md5(md5(md5($key . $ch1) . $ch2) . $ch3), $nhnum % 8, $knum % 8 + 16);
        $txt = base64_encode($txt);
        $txt = str_replace(['+', '/', '='], ['-', '_', '.'], $txt);
        $tmp = '';
        $j = 0;
        $k = 0;
        $tlen = strlen($txt);
        $klen = strlen($md5_key);
        for ($i = 0; $i < $tlen; $i++) {
            $k = $k == $klen ? 0 : $k;
            $j = ($nhnum + strpos($chars, $txt[$i]) + ord($md5_key[$k++])) % 64;
            $tmp .= $chars[$j];
        }
        $tmplen = strlen($tmp);
        $tmp = substr_replace($tmp, $ch3, $nh2 % ++$tmplen, 0);
        $tmp = substr_replace($tmp, $ch2, $nh1 % ++$tmplen, 0);
        $tmp = substr_replace($tmp, $ch1, $knum % ++$tmplen, 0);
        return $tmp;
    }

    /**
     * 解密函数
     * @param 需要解密的字串 $txt
     * @return string
     */
    public static function decrypt($txt)
    {
        $key = self::$key;
        $chars = self::$crypt_char;
        $knum = 0;
        $i = 0;
        $tlen = strlen($txt);
        if ($tlen < 3) {
            Error::showError('参数不合法');
        }

        while (isset($key[$i])) {
            $knum += ord($key[$i++]);
        }
        $ch1 = $txt[$knum % $tlen];
        $nh1 = strpos($chars, $ch1);
        $txt = substr_replace($txt, '', $knum % $tlen--, 1);
        $ch2 = $txt[$nh1 % $tlen];
        $nh2 = strpos($chars, $ch2);
        $txt = substr_replace($txt, '', $nh1 % $tlen--, 1);
        $ch3 = $txt[$nh2 % $tlen];
        $nh3 = strpos($chars, $ch3);
        $txt = substr_replace($txt, '', $nh2 % $tlen--, 1);
        $nhnum = $nh1 + $nh2 + $nh3;
        $md5_key = substr(md5(md5(md5($key . $ch1) . $ch2) . $ch3), $nhnum % 8, $knum % 8 + 16);
        $tmp = '';
        $j = 0;
        $k = 0;
        $tlen = strlen($txt);
        $klen = strlen($md5_key);
        for ($i = 0; $i < $tlen; $i++) {
            $k = $k == $klen ? 0 : $k;
            $j = strpos($chars, $txt[$i]) - $nhnum - ord($md5_key[$k++]);
            while ($j < 0) {
                $j += 64;
            }
            $tmp .= $chars[$j];
        }
        $tmp = str_replace(['-', '_', '.'], ['+', '/', '='], $tmp);
        return str_replace("\0", '', base64_decode($tmp));
    }
}