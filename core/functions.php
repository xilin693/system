<?php

use king\core\Error;
use king\core\Route;
use king\lib\Input;
use king\View;
use king\lib\Request;
use king\lib\Response;
use king\core\Model as Db;

function C($name)//加载config目录下的文件
{
    static $config = [];
    $name = strtolower($name);
    if (!strpos($name, '.')) {
        $file = 'config';
        $key = $name;
    } else {
        $files = explode('.', $name);
        $file = $files[0];
        $key = $files[1];
    }

    $path = APP_PATH . 'config' . DS . $file . EXT;
    if (isset($config[$file])) {
        $array = $config[$file];
    } else {
        if (is_file($path)) {
            $array = require $path;
            $config[$file] = $array;
        } else {
            return false;
        }
    }

    if ($key == '*') {
        return $array;
    } else {
        return $array[$key] ?? '';
    }
}

function emptyOr0($var)
{
    return empty($var) || $var === '0';
}

function emptyAnd0($var)
{
    return empty($var) && $var === '0';
}

function emptyNot0($var)
{
    return empty($var) && $var !== '0';
}

function view($mix = '', $data = [])
{
    return View::getClass($mix, $data);
}

function P($key = '', $default = null, $xss = true)
{
    if (C('post_json')) {
        return Ps($key, $default);
    } else {
        return Input::post($key, $default, $xss);
    }
}

function G($key = '', $default = null, $xss = true)
{
    return Input::get($key, $default, $xss);
}

function H($name = '')
{
    return Request::header($name);
}

function put()
{
    return Response::put();
}

function Ps($key = '', $default = null)
{
    $content = file_get_contents('php://input');
    $data = json_decode($content, true);
    if ($key) {
        return $data[$key] ?? $default;
    }

    return $data;
}

function is_cli()
{
    return (PHP_SAPI === 'cli');
}

function steam($source = '')
{
    $data = json_decode(put(), true);
    if (!is_array($data)) {
        $data = [];
    }

    if ($source !== '') {
        $data = is_array($source) ? array_merge($data, $source) : array_merge($data, ['id' => $source]);
    }

    return $data;
}

function S($seg)  // Input::segment的简写
{
    return Input::segment($seg);
}

function A()  // Input::getArgs的简写
{
    return Input::getArgs();
}

function redirect($url = '')
{
    return Input::redirect($url);
}

function L($url = '')  // $this->input->site的简写
{
    return Input::site($url);
}

function M($table = '', $db = 'default') // 仅适合脚本场景
{
    static $dbs;
    if (!isset($dbs[$db])) {
        $dbs[$db] = new Db(false);
        $dbs[$db]->setDb($db);
    }

    if ($table) {
        $dbs[$db]->setTable($table);
    }
    return $dbs[$db];
}

function only($data, $name)
{
    if (!$name) {
        return $data;
    }

    if (is_string($name)) {
        $name = explode(',', $name);
    }

    $item = [];
    foreach ($name as $key) {
        if (isset($data[$key])) {
            $item[$key] = $data[$key];
        }
    }
    return $item;
}

function source($seg = '')
{
    $segs = Route::sourceSeg();
    if ($seg) {
        return $segs[$seg] ?? '';
    } else {
        return implode('/', $segs);
    }
}

function finalUri($array = false)
{
    $uri = Route::$seg_uri;
    return ($array == false) ? implode('/', $uri) : $uri;
}

function camelize($input, $separator = '_')
{
    return str_replace($separator, '', ucwords($input, $separator));
}

function underScore($camelCase)
{
    return strtolower(
        preg_replace(
            ["/([A-Z]+)/", "/_([A-Z]+)([A-Z][a-z])/"],
            ["_$1", "_$1_$2"],
            lcfirst($camelCase)
        ));
}

function getThrowMsg($e)
{
    if (is_object($e)) {
        return 'File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Error:' . $e->getMessage();
    }
}

function whiteList($list)
{
    if (is_array($list)) {
        $uri = source(2);
        if (in_array($uri, $list)) {
            return true;
        }
    }
}

function https()
{
    if ((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        return true;
    } else {
        return false;
    }
}

function U($url = '', $prefix = '') // prefix参数css,js会用到,一般就是component,可以直接调用UC方法
{
    $path = parse_url($url)['path'];
    $parts = pathinfo($path);
    if (isset($parts['extension']) && $parts['extension'] != C('suffix')) {
        $ext_str = substr($parts['extension'], 0, 2);
        $url_prefix = input::libUrl();
        if ($ext_str == 'cs') {
            $ext = 'css/';
        } elseif ($ext_str == 'js') {
            $ext = 'js/';
        } else {
            $ext = 'img/';
        }
    } else {
        $url_prefix = Input::site();
        $ext = '';
    }

    if (!empty($prefix)) {
        $return_url = $prefix . '/' . $url;
    } else {
        $segs = Route::sourceSeg();
        if ($ext && (in_array($segs[0], Route::getRootFolder()))) {
            $return_url = $segs[0] . '/' . $ext . $url;
        } elseif (in_array(S(1), Route::getRootFolder())) {
            $return_url = S(1) . '/' . $ext . $url;
        } else {
            $return_url = $ext . $url;
        }
    }

    if (!$ext) {
        $return_url = Route::reversePregUrl($return_url);
    }
    return $url_prefix . $return_url;
}

function UC($url = '')
{
    return U($url, 'component');
}