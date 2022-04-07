<?php

namespace king;

use king\core\Error;
use king\core\Route;

class View
{
    protected $view_name;
    protected static $all_data = [];

    public static function getClass($name = null, $data = NULLL)
    {
        return new View($name, $data);
    }

    public function __construct($mix = null, $data = [])
    {
        $path = APP_PATH . 'view';

        if (is_array($data) && !empty($data)) {
            $mix = route::getViewSeg($mix);
            $this->view_name = $path . DS . $mix . EXT;
            self::$all_data = array_merge(self::$all_data, $data);
        } else {
            if (!empty($mix) && is_string($mix)) {
                $mix = Route::getViewSeg($mix);
                $this->view_name = $path . DS . $mix . EXT;
            } else {
                if (is_array($mix)) {
                    self::$all_data = array_merge(self::$all_data, $mix);
                }
                $segs = Route::sourceSeg();
                $folder = '';
                foreach ($segs as $seg) {
                    $folder .= DS . $seg;
                }
                $this->view_name = $path . $folder . EXT;
            }
        }
    }

    private function loadView($file, $data)
    {
        ob_start();
        if (is_array($data)) {
            extract($data, EXTR_SKIP);
        }
        try {
            require $file;
        } catch (Exception $e) {
            ob_end_clean();
            Error::showError($e->getMessage());
        }
        return ob_get_clean();
    }

    public function __set($key, $value)
    {
        self::$all_data[$key] = $value;
    }

    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            Error::showError($e->getMessage());
        }
    }

    public function render($render = true)
    {
        $output = $this->loadView($this->view_name, self::$all_data);
        if ($render) {
            echo $output;
            return (string)'';
        } else {
            return $output;
        }
    }
}