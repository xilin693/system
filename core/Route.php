<?php

namespace king\core;

class Route
{
    public static $seg_uri;
    public static $query_str;
    public static $file_path;
    public static $args;
    public static $call_args = [];
    private static $route;
    private static $default_namespace = 'app\controller\\';


    public static function getSegs($uri = '', $cli = '')
    {
        self::$route = C('route.*');
        if (!empty($_SERVER['QUERY_STRING'])) {
            self::$query_str = '?' . trim($_SERVER['QUERY_STRING'], '&/');
        }

        if (empty($uri)) {
            $uri = '';
            if (is_cli()) {
                if (isset($_SERVER['argv'][1])) {
                    $uri = $_SERVER['argv'][1];
                }
            } else {
                if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO']) {
                    $uri = $_SERVER['PATH_INFO'];
                } elseif (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']) {
                    $parts = parse_url('http://king' . $_SERVER['REQUEST_URI']);
                    $uri = $parts['path'] ?? '';
                } else {
                    $uri = '';
                }
            }
        }

        $uri = str_replace(C('suffix'), '', trim($uri, '/'));
        if ($uri == '' || $uri == '\\') {
            $uri = C('default_page');
        }

        $preg_uri = $cli ? $uri : self::pregUrl($uri);
        self::$seg_uri = explode('/', $uri);
        $real_seg_uri = self::getRealSeg($preg_uri);
        $default_full_uri = self::$default_namespace . $real_seg_uri;
        $offset = 3;
        if (current(explode('/', $real_seg_uri)) == C('default_folder')) {
            $offset -= 1;
        }

        self::$args = array_slice(explode('/', $preg_uri), $offset);
        $replace_full_uri = str_replace('/', '\\', $default_full_uri);
        $full_uris = explode('\\', $replace_full_uri);
        $method = end($full_uris);
        $pos = strrpos($replace_full_uri, $method);
        $class = rtrim(substr_replace($replace_full_uri, '', $pos, strlen($method)), '\\');
        $args = (self::$call_args) ? array_merge(self::$call_args, self::$args) : self::$args;
        return ['class' => $class, 'method' => $method, 'call_args' => $args];
    }

    public static function pregUrl($uri)
    {
        $source_uri = '';
        if (isset(self::$route[$uri])) {
            $uri = self::$route[$uri];
            $source_uri = $uri;
        } else {
            $routes = [];
            foreach (self::$route as $key => $value) {
                $rs = self::hasMethod($key);
                $routes[$rs['method']][$rs['url']] = $value;
            }

            $method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
            if (!empty($routes[$method])) {
                foreach ($routes[$method] as $key => $val) {
                    $key = trim($key, '/');
                    $val = trim($val, '/');
                    if (preg_match('@^' . $key . '$@u', $uri, $matches)) {
                        array_shift($matches);
                        self::$call_args = $matches;
                        if (strpos($val, '$') !== false) {
                            $uri = preg_replace('@^' . $key . '$@u', $val, $uri);
                        } else {
                            $uri = $val;
                        }
                        $source_uri = $uri;
                        break;
                    }
                }
            }
        }

        if (!$source_uri && C('only_route') && !is_cli()) {
            \king\lib\Response::sendResponse(404);
        }
        return $uri;
    }

    public static function hasMethod($url)
    {
        $method = 'get::';
        $requests = ['get::', 'post::', 'put::', 'delete::'];
        foreach ($requests as $r) {
            $pos = strpos($url, $r);
            if ($pos !== false) {
                $method = $r;
                break;
            }
        }

        return ['method' => substr($method, 0, -2), 'url' => str_replace($method, '', $url)];
    }

    public static function getRealSeg($seg)
    {
        $segs = explode('/', $seg);
        if (C('default_folder') && !in_array($segs[0], self::getRootFolder())) {
            $seg = C('default_folder') . '/' . $seg;
        }
        $match_segs = explode('/', $seg);
        if (!isset($match_segs[2])) {
            $match_segs[2] = 'index';
        }
        return $match_segs[0] . '/' . ucfirst($match_segs[1]) . '/' . $match_segs[2];
    }

    public static function getRootFolder()
    {
        $folder = APP_PATH . 'controller/';
        $dirs = scandir($folder);
        $real_dir = [];
        foreach ($dirs as $dir) {
            $pos = strpos($dir, '.');
            if ($pos === false) {
                $real_dir[] = $dir;
            }
        }
        return $real_dir;
    }

    public static function getViewSeg($seg)
    {
        $segs = self::sourceSeg();
        $view_segs = explode('/', $seg);
        if ($view_segs[0] == 'common') {
            return $seg;
        } else {
            if ($segs[0] == $view_segs[0]) {
                return $seg;
            } else {
                return $segs[0] . '/' . $seg;
            }
        }
    }

    public static function sourceSeg()//取得route前的网址片区
    {
        $seg_str = self::pregUrl(implode('/', self::$seg_uri));
        $real_seg_str = route::getRealSeg($seg_str);
        return explode('/', $real_seg_str);
    }

    public static function reversePregUrl($uri)
    {
        $reverse_uris = trim($uri, '/');
        $uris = explode('/', $reverse_uris);
        $preg_url = false;
        foreach (self::$route as $key => $val) {
            $key = trim($key, '/');
            $val = trim($val, '/');
            $vals = explode('/', $val);
            if (count($uris) == count($vals)) {
                $diff = array_diff($vals, $uris);
                $reverse_diff = array_diff($uris, $vals);
                $diff_count = substr_count(implode(' ', $diff), '$');
                $i = 0;
                if (count($diff) == $diff_count) {
                    $i++;
                    $key = preg_replace('/\(.*\)/', '\$' . $i, $key);
                    $newKey = str_replace($diff, $reverse_diff, $key);
                    $preg_url = true;
                    return $newKey;
                }
            }
        }
        if (!$preg_url) {
            return $uri;
        }
    }
}
	