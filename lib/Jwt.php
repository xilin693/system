<?php

namespace king\lib;

use king\lib\Request;
use king\lib\Response;
use Firebase\JWT\JWT as JwtLib;

class Jwt
{
    public static function getToken($data, $exp = 7200)
    {
        $time = time();
        $token['data'] = $data;
        $token['iat'] = $time;
        $token['exp'] = $time + $exp;
        if (empty(C('jwt.key'))) {
            Response::sendResponseJson('400', 'jwt key was not found');
        }
        return JwtLib::encode($token, C('jwt.key'));
    }

    public static function checkToken($token = '', $key = '')
    {
        $token = $token ?: Request::header('Authorization');
        if (count(C('jwt.ban_token')) > 0) {
            $bans = C('jwt.ban_token');
            if (in_array($token, $bans)) {
                return false;
            }
        }

        try {
            $key = $key ?: C('jwt.key');
            $data = JwtLib::decode($token, $key, ['HS256']);
            $remain = $data->exp - time();
            if ($remain < 0) {
                return false;
            } else {
                if (isset($data->data)) {
                    $row = (array)$data->data;
                    $row['remain'] = $remain;
                    return $row;
                } else {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}