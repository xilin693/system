<?php

namespace king;

use king\lib\Input;
use king\lib\Struct;

class Controller
{
    protected $request;

    public function __construct($req = '')
    {
        $this->request = new Struct();
    }
    
    public function ipAddr()
    {
        return Input::ipAddr();
    }
}