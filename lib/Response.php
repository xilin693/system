<?php

namespace king\lib;

class Response
{
    public static $code;
    public static $mock_put_data = '';
    protected static $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entry',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    ];

    public static function put()
    {
        if (ENV == 'testing' || defined('SWOOLE_STATUS')) {
            return self::$mock_put_data;
        } else {
            return file_get_contents('php://input');
        }
    }

    public static function sendResponseHtml($status, $body = '')
    {
        if (!$body) {
            $body = self::$codes[$status];
        }
        $header = 'HTTP/1.1 ' . $status . ' ' . self::$codes[$status];
        header($header);
        header('Content-type: text/html');
        echo $body;
        exit;
    }

    public static function sendResponseJson($status, $body = '', $type = '')
    {
        self::$code = $status;
        if ($body === '') {
            $body = self::$codes[$status];
        }

        $header = 'HTTP/1.1 ' . $status . ' ' . self::$codes[$status];
        if (!$body) {
            $return = ($body === false) ? '{}' : (($body === 0) ? json_encode(['msg' => 0]) : '[]');
            if ($type == 'object' && $return == '[]') {
                $return = '{}';
            }
        } else {
            if (!is_array($body)) {
                $return = json_encode(['msg' => $body]);
            } else {
                if ($type == 'noBool') {
                    array_walk_recursive($body, function (&$v) {
                        $v = ($v === false) ? '' : $v;
                    });
                } elseif ($type == 'noNumeric') {
                    array_walk_recursive($body, function (&$v) {
                        if (!is_array($v) && !is_object($v)) {
                            $v = strval($v);
                        }
                    });
                }

                $return = json_encode($body);
            }
        }

        if (defined('SWOOLE_STATUS')) {
            if ($type == 'exception') {
                return;
            } else {
                echo $return;
            }
        } else {
            header($header);
            echo $return;

            if ($status != 200) {
                exit;
            }
        }
    }

    public static function sendResponse($status = 200, $body = '', $content_type = 'text/html')
    {
        $header = 'HTTP/1.1 ' . $status . ' ' . self::$codes[$status];
        header($header);
        header('Content-type: ' . $content_type);
        if ($body != '' || $content_type != 'text/html') {
            echo $body;
            exit;
        } else {
            $message = '';
            switch ($status) {
                case 401:
                    $message = '访问被拒绝.';
                    break;
                case 403:
                    $message = '禁止访问.';
                    break;
                case 404:
                    $message = '请求的网址不存在.';
                    break;
                case 500:
                    $message = '服务器错误.';
                    break;
                case 501:
                    $message = '执行错误.';
                    break;
            }

            $signature = 'Api Server at ' . ($_SERVER['SERVER_NAME'] ?? '') . ' Port 80';
            $body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">  
	                        <html>  
	                            <head>  
	                                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">  
	                                <title>' . $status . ' ' . self::$codes[$status] . '</title>  
	                            </head>  
	                            <body>  
	                                <h1>' . self::$codes[$status] . '</h1>  
	                                ' . $message . '  
	      
	                                <hr />  
	                                <address>' . $signature . '</address>  
	                            </body>  
	                        </html>';
            echo $body;
            exit;
        }
    }
}