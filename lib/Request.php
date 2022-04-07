<?php

namespace king\lib;

use king\core\Instance;
use king\core\Error;
use king\lib\Jwt;
use king\lib\Response;

class Request extends Instance
{
    private $url;
    public $body;
    private $opt;
    public $username;
    public $password;
    private $curl;
    private $fp = false;
    public $response_body;
    public $response_info;
    public $auth_type = '';
    public $header;

    public function __construct($url, $opt = 'get')
    {
        $this->url = $url;
        $this->opt = $opt;
    }

    public function sendRequest()
    {
        $function = 'request' . ucfirst($this->opt);
        if (method_exists($this, $function)) {
            if ($this->opt == 'get' && $this->body) {
                $this->url = $this->url . '?' . http_build_query($this->body);
            }

            $this->curl = curl_init($this->url);
            $this->httpAuth($this->curl);
            $this->$function();
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            $this->response_body = curl_exec($this->curl);
            $this->response_info = curl_getinfo($this->curl);
        } else {
            Error::showError('请求对象不正确');
        }
    }

    private function requestGet()
    {
        $this->body = '';
    }

    private function buildUrl() {

    }

    private function requestPost()
    {
        if (is_array($this->body)) {
            $this->body = http_build_query($this->body, '', '&');
        }
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->body);
        curl_setopt($this->curl, CURLOPT_POST, 1);
    }

    private function requestPut()
    {
        if (is_array($this->body)) {
            $this->body = json_encode($this->body);
        }
        $this->fp = tmpfile();
        fwrite($this->fp, $this->body);
        fseek($this->fp, 0);
        curl_setopt($this->curl, CURLOPT_PUT, true);
        curl_setopt($this->curl, CURLOPT_INFILE, $this->fp);
        curl_setopt($this->curl, CURLOPT_INFILESIZE, strlen($this->body));
    }

    private function requestDelete()
    {
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    private function httpAuth()
    {
        $header = $this->getHeader();
        switch ($this->auth_type) {
            case 'http':
                curl_setopt($this->curl, CURLOPT_TIMEOUT, 15);
                curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                curl_setopt($this->curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
                break;
            case 'jwt':
                curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
                break;
            default:
                if ($header) {
                    curl_setopt($this->curl, CURLOPT_HTTPHEADER, $header);
                }
                break;
        }
    }

    private function getHeader()
    {
        if (is_array($this->header)) {
            $header = [];
            foreach ($this->header as $key => $value) {
                $header[] = $key . ':' . $value;
            }
            return $header;
        }
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
        return $this->response_info;
    }

    public function getHttpCode()
    {
        if (is_array($this->response_info)) {
            return $this->response_info['http_code'];
        } else {
            return NULL;
        }
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
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

    public static function digestHeader($realm, $allowUser)
    {
        if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5($realm) . '"');
            Response::sendResponse(401);
        } else {
            $data = self::parseDigest($_SERVER['PHP_AUTH_DIGEST']);
            if (!isset($allowUser[$data['username']])) {
                Response::sendResponse(401);
            } else {
                $A1 = md5($data['username'] . ':' . $realm . ':' . $allowUser[$data['username']]);//php手册范例
                $A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
                $validResponse = md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2);
                if ($data['response'] == $validResponse) {
                    return true;
                } else {
                    Response::sendResponse(401);
                }
            }
        }
        exit;
    }

    private static function parseDigest($txt)
    {
        $parts = ['nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1];
        $data = [];
        $keys = implode('|', array_keys($parts));
        preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $data[$m[1]] = $m[3] ? $m[3] : $m[4];
            unset($parts[$m[1]]);
        }
        return $parts ? false : $data;
    }

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }

        if(is_resource($this->curl)) {
            curl_close($this->curl);
        }
    }
}