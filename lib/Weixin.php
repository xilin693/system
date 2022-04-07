<?php

namespace king\lib;

use king\core\Error;
use king\core\Instance;
use king\lib\Cache;
use king\lib\exception\BadRequestHttpException;

class Weixin extends Instance
{
    public $token;
    protected $app_id;
    protected $app_secret;
    public $access_token;
    private $wx_url_prefix;
    protected $second = 7000;

    public function __construct($app_id = '', $app_secret = '')
    {
        $this->config = C('weixin.*');
        foreach ($this->config as $key => $value) {
            $this->$key = $value;
        }

        if ($app_id) {
            $this->app_id = $app_id;
        }

        if ($app_secret) {
            $this->app_secret = $app_secret;
        }
    }

    public function getAccessToken()
    {
        $cache_key = md5('weixin:token:' . $this->app_id);
        $token = Cache::get($cache_key);
        if (!$token) {
            $url = $this->wx_url_prefix . 'cgi-bin/token?grant_type=client_credential&appid=' . $this->app_id . '&secret=' . $this->app_secret;
            $rs = $this->requestUrl($url, '', 'get');
            if (!empty($rs->access_token)) {
                $token = $rs->access_token;
                Cache::set($cache_key, $token, $this->second);
            } else {
                throw new BadRequestHttpException('access_token get failed');
            }
        }

        $this->access_token = $token;
    }

    /**
     * 获取小程序用户信息
     * @param $code
     * @return string
     */
    public function getUserInfo($code)
    {
        $openid = $this->getWebAccessToken($code, 'openid');
        if ($openid) {
            return $this->getOneUser($openid);
        } else {
            throw new BadRequestHttpException('openid get failed');
        }
    }

    /**
     * 获取公众号用户信息
     * @param $code
     * @return string
     */
    public function getUserBaseInfo($code)
    {
        $rs = $this->getWebAccessToken($code, false);
        if ($rs) {
            return $this->getOneUserBaseInfo($rs->openid, $rs->access_token);
        } else {
            throw new BadRequestHttpException('openid get failed');
        }
    }

    public function getWebAccessToken($code, $field)
    {
        $url = $this->wx_url_prefix . 'sns/oauth2/access_token?appid=' . $this->app_id . '&secret=' . $this->app_secret . '&code=' . $code . '&grant_type=authorization_code';
        $rs = $this->requestUrl($url, '', 'get');
        return $field ? $rs->$field : $rs;
    }

    public function getOneUser($openid)
    {
        $url = $this->wx_url_prefix . 'cgi-bin/user/info?access_token=' . $this->access_token . '&openid=' . $openid . '&lang=zh_CN';
        return $this->requestUrl($url, '', 'get');
    }

    public function getOneUserBaseInfo($openid, $access_token)
    {
        $url = $this->wx_url_prefix . 'sns/userinfo?access_token=' . $access_token . '&openid=' . $openid . '&lang=zh_CN';
        return $this->requestUrl($url, '', 'get');
    }

    public function authMiniProgram($code)
    {
        $url = $this->wx_url_prefix . 'sns/jscode2session?appid=' . $this->app_id . '&secret=' . $this->app_secret . '&js_code=' . $code . '&grant_type=authorization_code';
        $rs = $this->requestUrl($url, '', 'get');
        if (isset($rs->openid)) {
            return $rs;
        } else {
            throw new BadRequestHttpException('openid get failed');
        }
    }

    public function getSign()
    {
        $timestamp = time();
        $noncestr = $this->getRandChar(16);
        $jsapi_ticket = $this->getTicket($this->app_id, $this->app_secret);
        $sign_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $string = sprintf("jsapi_ticket=%s&noncestr=%s&timestamp=%s&url=%s", $jsapi_ticket, $noncestr, $timestamp, $sign_url);
        $signature = sha1($string);
        return ['appid' => $this->app_id, 'timestamp' => $timestamp, 'noncestr' => $noncestr, 'signature' => $signature];
    }

    private function getTicket()
    {
        if (!$this->access_token) {
            throw new BadRequestHttpException('access_token未设置');
        }

        $cache_key = md5('weixin:ticket:' . $this->app_id);
        $ticket = Cache::get($cache_key);
        if (!$ticket) {
            $url = $this->wx_url_prefix . 'cgi-bin/ticket/getticket?access_token=' . $this->access_token . '&type=jsapi';
            $rs = $this->requestUrl($url, '', 'get');
            if (isset($rs->errcode) && $rs->errcode == 0) {
                Cache::set($cache_key, $rs->ticket, $this->second);
            } else {
                throw new BadRequestHttpException('get ticket failed,error code:' . $rs->errcode);
            }
        }

        return $ticket;
    }

    private function getRandChar($length)
    {
        $str = '';
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[rand(0, $max)];
        }
        return $str;
    }

    public function checkSignature($signature, $time, $nonce)
    {
        $tmpArr = [$this->token, $time, $nonce];
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 发送post,或get请求
     * @param string $url
     * @param json $json
     * @return string
     */
    private function requestUrl($url, $data, $type = 'post')
    {
        $req = Request::getClass($url, $type);
        if ($type == 'post') {
            $req->setBody($data);
        }
        $req->sendRequest();
        return json_decode($req->getResponseBody());
    }
}