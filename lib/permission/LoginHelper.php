<?php

namespace king\lib\permission;

use king\lib\Cache;
use king\lib\Jwt;
use king\core\Error;

class LoginHelper
{
    public static function crypt($password)
    {
        $encrypt_key = C('permission.password_salt');
        return md5($encrypt_key . $password);
    }

    public static function makeToken($id, $salt, $expire)
    {
        $token = md5($id . ':' . time() . ':' . $salt);
        $rs = Cache::set($token, $id, intval($expire));
        if (!$rs) {
            Error::showError("token写入缓存失败");
        }
        return $token;
    }
}