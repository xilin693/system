<?php

namespace king\lib\permission;

use king\lib\Input;
use king\lib\Cache;
use king\lib\exception\BadRequestHttpException;

class LoginCache extends Cache
{
    public static function check($username, $max_retry_times, $max_retry_period = '')
    {
        $key = 'loginFail:' . $username;
        $times = parent::get($key);
        if (!$times) {
            parent::set($key, 0, intval($max_retry_period));
        }

        if ($times > $max_retry_times) {
            throw new BadRequestHttpException("验证次数超过上限");
        }
    }

    public static function incrTimes($username)
    {
        $key = 'loginFail:' . $username;
        parent::incr($key);
    }

    public static function checkToken($token)
    {
        $id = self::getId($token);
        if ($id) {
            self::expireToken($token);
            return true;
        } else {
            return false;
        }
    }

    public static function getId($token)
    {
        return parent::get($token);
    }

    public static function expireToken($token, $expire = '')
    {
        return parent::expire($token, ($expire ?: intval(C('permission.token_expire'))));
    }
}