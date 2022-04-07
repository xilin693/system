<?php

namespace king\lib;

use king\core\Instance;
use king\lib\cache\Redis;

class Limiter extends Instance
{
    protected $name;
    protected $max_request;
    protected $period;
    private $adapter;

    public function __construct($max_request = 100, $period = 60, $name = 'limiter', $config = 'cache.limiter')
    {
        $this->name = $name;
        $this->max_request = $max_request;
        $this->period = $period;
        $this->adapter = new Redis($config);
    }

    public function check($id, $use = 1.0)
    {
        $rate = $this->max_request / $this->period;
        $visit_time = $this->keyTime($id);
        $visit_num = $this->keyAllow($id);
        $ctime = time();
        if (!$this->adapter->exists($visit_time)) {
            $this->adapter->set($visit_time, $ctime, $this->period);
            $this->adapter->set($visit_num, ($this->max_request - $use), $this->period);
            return true;
        }
        $time_passed = $ctime - $this->adapter->get($visit_time);
        $this->adapter->set($visit_time, $ctime, $this->period);
        $allowance = $this->adapter->get($visit_num);
        $allowance += $time_passed * $rate;
        if ($allowance > $this->max_request) {
            $allowance = $this->max_request;
        }
        if ($allowance < $use) {
            $this->adapter->set($visit_num, $allowance, $this->period);
            return false;
        }
        $this->adapter->set($visit_num, $allowance - $use, $this->period);
        return true;
    }

    public function getAllow($id)
    {
        $this->check($id, 0.0);
        $visit_num = $this->keyAllow($id);
        if (!$this->adapter->exists($visit_num)) {
            return $this->max_request;
        }
        return (int) max(0, floor($this->adapter->get($visit_num)));
    }

    public function purge($id)
    {
        $this->adapter->del($this->keyTime($id));
        $this->adapter->del($this->keyAllow($id));
    }

    private function keyTime($id)
    {
        return $this->name . ":" . $id . ":time";
    }

    private function keyAllow($id)
    {
        return $this->name . ":" . $id . ":allow";
    }
}