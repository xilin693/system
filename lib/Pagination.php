<?php

namespace king\lib;

use king\core\Instance;
use king\core\Loader;
use king\core\Route;

class Pagination extends Instance
{
    protected $base_url = '';
    protected $segment = '';
    protected $query_str = '';
    protected $per_page = 20;
    protected $total = 0;
    protected $style = 'pagination';
    protected $auto_hide = false;
    protected $urls;
    protected $current_page;
    protected $total_pages;
    protected $current_first_item;
    protected $current_last_item;
    protected $first_page;
    protected $last_page;
    protected $previous_page;
    protected $next_page;
    protected $suffix;

    public function __construct($config = [])
    {
        $this->segment = Loader::$method;
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        $this->urls = ($this->base_url == '') ? Route::$seg_uri : explode('/', trim($this->base_url, '/'));// 取得url结构,以便确定分页片区

        if (is_string($this->segment)) {
            $seg = $this->segment;
            if (($key = array_search($this->segment, $this->urls)) === false) { // 找不到片区,说明是自定义分页
                $match = str_replace('{page}', '(\d+)', $seg);
                if (!preg_match('/' . $match . '/', end(Route::$seg_uri), $matches)) {
                    $current = 1;
                } else {
                    if (!$this->base_url) {
                        array_pop($this->urls);
                    }
                    $current = $matches[1] ?? 1;
                }
                $this->urls[] = $this->segment;
            } else {
                $this->segment = $key + 2;
            }
        }

        if (strpos($seg, '{page}') === false) {  // 非自定义分页时,需要插入{page}符,以便展示view时替换
            $this->urls[intval($this->segment) - 1] = '{page}';
        }
        $pre_url = implode('/', $this->urls);
        if (strpos($pre_url, 'http:') === false) {
            $this->urls = Input::site($pre_url) . Route::$query_str;
        } else {
            $this->urls = $pre_url . Route::$query_str;  // 有http时表示网址前缀也是自定义的,不需要使用input::site();
        }

        if ($this->suffix) {
            $this->urls .= C('suffix');
        }
        $this->current_page = isset($current) ? $current : S($this->segment);  // 有设置current时使用current值
        $this->total = (int)max(0, $this->total);
        $this->per_page = (int)max(1, $this->per_page);
        $this->total_pages = (int)ceil($this->total / $this->per_page);
        $this->current_page = (int)min(max(1, $this->current_page), max(1, $this->total_pages));
        $this->current_first_item = (int)min((($this->current_page - 1) * $this->per_page) + 1, $this->total);
        $this->current_last_item = (int)min($this->current_first_item + $this->per_page - 1, $this->total);
        $this->first_page = ($this->current_page === 1) ? false : 1;
        $this->last_page = ($this->current_page >= $this->total_pages) ? false : $this->total_pages;
        $this->previous_page = ($this->current_page > 1) ? $this->current_page - 1 : false;
        $this->next_page = ($this->current_page < $this->total_pages) ? $this->current_page + 1 : false;
    }

    public function links($render= false)
    {
        if ($this->auto_hide === true and $this->total_pages <= 1) {
            return '';
        }
        $this->style = $this->style ? $this->style : 'pagination';
        return view('common/' . $this->style, get_object_vars($this))->render($render);
    }

    public function getPage()
    {
        return $this->current_page ? $this->current_page : '';
    }

    public function __get($key)
    {
        if (isset($this->$key)) {
            return $this->$key;
        }
    }

    public function __call($func, $args = null)
    {
        return $this->__get($func);
    }

}