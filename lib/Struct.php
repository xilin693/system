<?php

namespace king\lib;

class Struct
{
    public function g($key = '')
    {
        return G($key);
    }

    public function p($key = '')
    {
        return P($key);
    }

    public function ps($key = '')
    {
        return Ps($key);
    }

    public function put()
    {
        return put();
    }

    public function steam($key = '')
    {
        return steam($key);
    }

    public function h($header = '')
    {
        return H($header) ?: ($_SERVER[$header] ?? '');
    }

    public function finalUri()
    {
        return finalUri();
    }

    public function source($seg = '')
    {
        return source($seg);
    }

    public function file($param = '')
    {
        return $_FILES[$param];
    }
}