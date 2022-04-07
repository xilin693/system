<?php

namespace king\lib;

use Yurun\Util\HttpRequest;

class Guzzle
{
    private $url;
    public $body;
    private $opt;
    public $response_body;
    public $http_code;
    public $header = [];

    public function __construct($url, $opt)
    {
        $this->url = $url;
        $this->opt = $opt;
    }

    public function getClass($url, $opt = 'get')
    {
        return new Guzzle($url, $opt);
    }

    public function sendRequest()
    {
        $config = $this->prepare();
        $client = new HttpRequest();
        $client->headers($config['headers']);
        $response = $client->send($config['url'], $config['body'], strtoupper($this->opt));
        $this->http_code = $response->getStatusCode();
        $this->response_body = $response->getBody();
    }

    public function prepare()
    {
        $config = [];
        $config['key'] = 'body';
        if ($this->opt == 'get' || $this->opt == 'delete') {
            if (is_array($this->body)) {
                $this->url .= '?' . http_build_query($this->body);
            }
            $config['body'] = '';
        } elseif ($this->opt == 'post') {
            $config['body'] = $this->body;
            $config['key'] = 'form_params';
        } elseif ($this->opt == 'put') {
            $config['body'] = is_array($this->body) ? json_encode($this->body) : $this->body;
        } else {
            return ;
        }

        $config['url'] = $this->url;
        $config['headers'] = $this->header;
        return $config;
    }

    public static function header($name = '')
    {
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $headers = array_change_key_case($headers, CASE_LOWER);
        } else {
            foreach ($_SERVER as $key => $val) {
                if (0 === strpos($key, 'HTTP_')) {
                    $key = str_replace('_', '-', strtolower(substr($key, 5)));
                    $headers[$key] = $val;
                }
            }
        }

        $name = str_replace('_', '-', strtolower($name));
        return $name ? ($headers[$name] ?? '') : $headers;
    }

    public function getResponseBody()
    {
        return $this->response_body;
    }

    public function getResponseInfo()
    {
        return ['http_code' => $this->http_code];
    }

    public function getHttpCode()
    {
        return $this->http_code;
    }

    public static function jwtHeader($token = '')
    {
        $token = $token ?: self::header('authorization');
        if (!empty($token)) {
            $rs = Jwt::checkToken($token);
            if (!$rs) {
                Response::sendResponseJson(401);
            } else {
                return $rs;
            }
        } else {
            Response::sendResponseJson(400);
        }
    }
}
