<?php

namespace king\lib;

use king\core\Error;
use Cron\CronExpression;
use king\core\Loader;
use king\core\Instance;
use king\lib\swoole\Server;
use king\lib\swoole\Process;
use king\lib\swoole\ProcessPool;
use Pagon\ChildProcess;

class Console extends Instance
{
    private $cli;
    public $crons;
    public $segments;
    protected $special = ['cron', 'swoole', 'process', 'processPool', 'init'];

    public function __construct()
    {
        $this->crons = C('cron.*');
    }

    public function run()
    {
        $this->parseCommandLine();
        $this->getUri($this->segments);
    }

    public function cron()
    {
        if (isset($this->crons) && count($this->crons) > 0) {
            if (extension_loaded('pcntl') && class_exists('Pagon\ChildProcess')) {
                $manager = new ChildProcess();
                foreach ($this->crons as $func => $schedule) {
                    if (CronExpression::factory((string)$schedule)->isDue()) {
                        $manager->parallel(function () use ($func) {
                            ob_start();
                            $file_name = str_replace('/', '-', $func) . '.log';
                            Loader::run($func, true);
                            Log::write(ob_get_contents(), $file_name);
                        });
                    }
                }
            } else {
                foreach ($this->crons as $func => $schedule) {
                    if (CronExpression::factory((string)$schedule)->isDue()) {
                        ob_start();
                        $file_name = str_replace('/', '-', $func) . '.log';
                        Loader::run($func, true);
                        Log::write(ob_get_contents(), $file_name);
                    }
                }
            }
        }
    }

    public static function logExit($content = '')
    {
        $func = $_SERVER['argv'][1] ?? '';
        if (!$func) {
            echo 'function does not exist';
        } else {
            $file_name = str_replace('/', '-', $func) . '.log';
            Log::write($content, $file_name);
        }

        exit;
    }

    public function swoole($action = 'start')
    {
        return (new Server())->initialize($action);
    }

    public function process()
    {
        return new Process();
    }

    public function processPool()
    {
        return new ProcessPool();
    }

    public function init()
    {
        Init::makeFile();
    }

    public function parseCommandLine()
    {
        for ($i = 1; $i < $_SERVER['argc']; $i++) {
            $this->segments[] = $_SERVER['argv'][$i];
        }
    }

    protected function getUri($uri)
    {
        $file_name = str_replace('/', '-', $uri[0]) . '.log';
        if (strpos($uri[0], '/') === false) {
            $uri[0] .= '/index';
        }

        $param = $uri[1] ?? '';
        $run_uri = $uri[0] . ($param ? '/' . $param : '');
        $uris = explode('/', $uri[0]);
        if (in_array($uris[0], $this->special)) {
            $func = $uris[0];
            $this->$func($param);
        } else {
            ob_start();
            Loader::run($run_uri, true);
            Log::write(ob_get_contents(), $file_name);
        }
    }
}
