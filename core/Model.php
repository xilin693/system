<?php

namespace king\core;

use PDO;
use king\core\Error;
use king\lib\Pagination;
use king\lib\Log;
use king\core\Link;
use king\lib\exception\BadRequestHttpException;

class Model
{
    private $select = [];
    protected $table;
    protected $key = 'id';
    protected $fetch_type = PDO::FETCH_ASSOC;
    protected $stmt;
    protected $bind = [];
    protected $wheres;
    protected $bind_where = [];
    protected $bind_update = [];
    protected $class;
    public $db_set;
    protected $master = false;
    protected $attr = false;
    private $field = '*';

    public function __construct($class)
    {
        $this->class = $class;
        if (C('auto_attr')) {
            $this->attr = true;
        }
    }

    public function __call($method, $args)
    {
        $this->handle = Link::init($this->db_set, $this->class, $this->attr, $this->master);
        if (method_exists($this->handle, $method)) {
            return call_user_func_array([$this->handle, $method], $args);
        } else {
            Error::showError('method ' . $method . ' does not exists');
        }
    }

    public function setDb($db_set)
    {
        $this->db_set = $db_set;
    }

    public function startMaster()
    {
        $this->master = true;
        return $this;
    }

    public function setTable($table)
    {
        $this->table = $this->getTablePrefix() . $table;
        $this->initSelect();
        return $this;
    }

    public function allowSpecialOpt()
    {
    }

    public function attr($attr = true) // 兼容2.2版本
    {
        $this->attr = $attr;
        return $this;
    }

    private function getBind($item)
    {
        return isset($this->bind[$item]);
    }

    public function field($fields = '*')
    {
        $fields = $fields ? $this->escapeFields($fields) : '*';
        $this->field = $fields;
        $this->select['select'] = 'SELECT ' . $fields;
        return $this;
    }

    protected function escapeFields($fields)
    {
        if ($fields == '*') {
            return $fields;
        }

        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }

        $field_array = array_map([$this, 'escapeColumn'], $fields);

        return implode(',', $field_array);
    }

    protected function initSelect()
    {
        $this->bind = [];
        $this->bind_update = [];
        $this->bind_where = [];
        $this->select = [
            'select' => 'SELECT *',
            'from' => 'FROM ' . $this->getTable()
        ];
    }

    public function from($table)
    {
        $this->select['from'] = 'FROM ' . $this->getTable($table);
        return $this;
    }

    public function whereRaw($key, $value = [])
    {
        if (is_array($key)) { // 临时处理where空数组的场景
            return $this;
        }

        $this->bind_where[] = (count($this->bind_where) > 0) ? ' AND ' . $key : $key;
        if (count($value)) {
            foreach ($value as $v) {
                $this->bind[] = $v;
            }
        }

        return $this;
    }

    protected function setBind($value)
    {
        if ($this->checkRaw($value) == '?') {
            $this->bind[] = $value;
        }
    }

    public function where($key = '', $opera = null, $value = '', $union = '')
    {
        if (is_array($key) && count($key) > 0) {
            foreach ($key as $k => $v) {
                $match = $this->hasOpera($k);
                $mode = $match[0] ?? '=';
                if (!empty($match[0])) {
                    $k = str_replace($match[0], '', $k);
                }

                $this->express($k, $mode, $v);
            }
        } elseif ($key instanceof \Closure) {
            $this->express($key, '', '');
        } else {
            $arg_num = func_num_args();
            if ($arg_num > 1) {
                if ($arg_num == 2) {
                    $value = $opera;
                    $opera = '=';
                }

                $this->express($key, $opera, $value, $union);
            } else {
                $this->whereRaw($key);
            }
        }

        return $this;
    }

    public function express($key, $opera, $value, $union = '')
    {
        if ($key instanceof \Closure) {
            $this->bind_where[] = (count($this->bind_where) > 0) ? $union . '(' : '(';
            call_user_func($key, $this);
            $this->bind_where[] = ')';
        } else {
            $field = $this->escapeColumn($key);
            $pre_value = '';
            if ($opera == 'between') {
                $pre_value = '? AND ?';
                if (!is_array($value)) {
                    Error::showError('between 值必须为数组');
                } else {
                    $this->bind[] = $value[0];
                    $this->bind[] = $value[1];
                }
            } elseif (trim($opera) == 'in' || trim($opera) == 'not in') {
                if (is_array($value) && count($value) > 0) {
                    $pre_value = '(' . implode(',', array_fill(0, count($value), '?')) . ')';
                    $this->bind = array_merge($this->bind, array_values($value));
                } else {
                    $pre_value = $value ? '(' . $value . ')' : "('')";
                }
            } else {
                if (!is_null($value)) {
                    $pre_value = $this->checkRaw($value);
                    if ($pre_value == '?') {
                        $this->bind[] = $value;
                    }
                }
            }

            $pre_where = $field . ' ' . $opera . ' ' . $pre_value;
            if ($union == 'inner') {
                $where = 'OR ' . $pre_where;
                $union = false;
            } else {
                $where = (count($this->bind_where) > 0) ? ($union ?: ' AND ') . $pre_where : $pre_where;
            }

            if (!empty($union)) {
                if (count($this->bind_where) > 0) {
                    $this->bind_where[] = str_replace($union, $union . ' (', $where) . ')';
                } else {
                    $this->bind_where[] = $pre_where;
                }
            } else {
                $this->bind_where[] = $where;
            }
        }

        return $this;
    }

    public function andWhere($key = '', $opera = '=', $value = '')
    {
        return $this->express($key, $opera, $value, 'AND');
    }

    public function orWhere($key = '', $opera = '=', $value = '')
    {
        if ($key instanceof \Closure) {
            return $this->express($key, $opera, $value, 'OR');
        } else {
            if ($value === '') {
                $value = $opera;
                $opera = '=';
            }

            return $this->express($key, $opera, $value, 'inner');
        }
    }

    public function limit($start = 0, $limit = 10)
    {
        if (is_array($start)) {
            $limit = $start[1] ?? 0;
            $start = $start[0];
        }

        $this->select['limit'] = 'LIMIT ' . intval($start) . ($limit ? ',' . intval($limit) : '');

        return $this;
    }

    public function find($id = '')
    {
        if ($id) {
            $this->where([$this->key => $id]);
        }
        $this->limit(0, 1);
        $rs = $this->get(['find', $id]);
        $this->initSelect();
        return $rs;
    }

    public function get($param = '')
    {
        $sql = $this->buildSql();
        $rs = $this->query($sql, $param, $this->bind);
        $first_param = $param[0] ?? '';
        if ($first_param != 'chunk') {
            $this->initSelect();
        }

        return $rs;
    }

    public function value($field = '')
    {
        if ($field) {
            $this->field($field);
        } else {
            $field = str_replace('`', '', substr($this->select['select'], 7));
            if (!$field) {
                Error::showError('value方法必须定义字段');
            }
        }

        $this->limit(0, 1);

        return $this->get(['value', $field]);
    }

    public function column($column = '')
    {
        if ($column) {
            $this->field($column);
        } else {
            $column = $this->getSelectColumn();
            if (!$column) {
                Error::showError('value方法必须定义字段');
            }
        }

        return $this->get(['column', $column]);
    }

    protected function getSelectColumn()
    {
        return str_replace('`', '', $this->field);
    }

    protected function buildSql($select = '')
    {
        $sql = '';
        $params = $select ?: $this->select;
        if (count($this->bind_where) > 0) {
            $params['where'] = $this->getBindWhere();
            $params = $this->sortSql($params);
        }

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $sql .= $v . ' ';
                }
            } else {
                $sql .= $value . ' ';
            }
        }

        return trim($sql);
    }

    private function getBindWhere()
    {
        if (count($this->bind_where) > 0) {
            $where = 'WHERE ' . implode(' ', $this->bind_where);
            return str_replace('(  AND', '(', $where);
        }
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    private function hasOpera($str)
    {
        preg_match('/(>\=|<\=|!\=|<>)|[<>!=]|\s(IS(\s+NOT){0,1}|BETWEEN|LIKE|IN|OR|NOT\s(IN|LIKE|BETWEEN))/i', trim($str), $match);

        return $match;
    }

    public function update($upd, $allow_field = [])
    {
        $upd = only($upd, $allow_field);
        $upd = $this->supplyTime($upd, 'update_time');
        foreach ($upd as $key => $val) {
            $len = strlen($key) - 1;
            $fields = unpack('A' . $len . 'real_key/A1opera', $key);
            $judge = $this->checkRaw($val);
            if ($fields['opera'] == '+' || $fields['opera'] == '-') {
                $valstr[] = $this->escapeColumn($fields['real_key']) . '=' . $this->escapeColumn($fields['real_key']) . $fields['opera'] . floatval($val);
            } else {
                $valstr[] = $this->escapeColumn($key) . '= ' . $judge;
                if ($judge == '?') {
                    $this->bind_update[] = $val;
                }
            }
        }

        $this->bind = array_merge($this->bind_update, $this->bind);
        $where = $this->select['where'] ?? $this->getBindWhere();
        if (!$where) {
            Error::showError('update必须设置where条件');
        }

        if (empty($valstr)) {
            Error::showError('无更新的值');
        }


        $rs = $this->query('UPDATE ' . $this->getTable() . ' SET ' . implode(', ', $valstr) . ' ' . $where, ['update'], $this->bind);
        $this->initSelect();
        return $rs;
    }

    private function bindParams($params)
    {
        $rs = [];
        foreach ($params as $key => $value) {
            $rs['keys'][] = $this->escapeColumn($key);
            $rs['bind_keys'][] = '?';
            $this->bind[] = $value;
        }

        return $rs;
    }

    public function save($params, $allow = [])
    {
        if (isset($params[$this->key])) {
            $this->where([$this->key => $params[$this->key]]);
            unset($params[$this->key]);
            return $this->update($params, $allow);
        } else {
            return $this->insert($params, $allow);
        }
    }

    public function replace($params)
    {
        $array = $this->bindParams($params);
        $rs = $this->query('REPLACE INTO ' . $this->getTable() . ' (' . implode(', ', $array['keys']) . ') VALUES (' . implode(',', $array['bind_keys']) . ')', ['update'], $this->bind);
        $this->initSelect();
        return $rs;
    }

    public function insert($params, $allow = [])
    {
        $params = only($params, $allow);
        $params = $this->supplyTime($params, 'insert_time');
        $array = $this->bindParams($params);

        $rs = $this->query('INSERT INTO ' . $this->getTable() . ' (' . implode(', ', $array['keys']) . ') VALUES (' . implode(',', $array['bind_keys']) . ')', ['insert'], $this->bind);
        $this->initSelect();
        return $rs;
    }

    private function supplyTime($array, $time)
    {
        if (property_exists($this->class, $time)) {
            $times = $this->class::$$time;
            if (!is_array($times)) {
                $times = explode(',', $times);
            }

            $now = time();
            foreach ($times as $tmp_time) {
                $array[$tmp_time] = $now;
            }
        }


        return $array;
    }

    public function autoSave($params)
    {
        return $this->save($params, $this->class::$allow_fields);
    }

    public function autoInsert($params)
    {
        return $this->insert($params, $this->class::$allow_fields);
    }

    public function autoUpdate($params)
    {
        return $this->update($params, $this->class::$allow_fields);
    }

    public function autoBatchUpdate($params)
    {
        return $this->batchUpdate($params, $this->class::$allow_fields);
    }

    public function autoBatchInsert($params)
    {
        return $this->batchInsert($params, $this->class::$allow_fields);
    }

    protected function placeholders($text, $count = 0, $separator = ",")
    {
        $result = array();
        if ($count > 0) {
            for ($x = 0; $x < $count; $x++) {
                $result[] = $text;
            }
        }

        return implode($separator, $result);
    }

    public function batchInsert($params, $allow = [])
    {
        $new_array = [];
        $insert_values = [];
        $question_marks = [];
        if (count($allow) > 0) {
            foreach ($params as $value) {
                $inter_array = only($value, $allow);
                $inter_array = $this->supplyTime($inter_array, 'insert_time');
                $new_array[] = $inter_array;
            }
        } else {
            foreach ($params as $param) {
                $inter_array = $this->supplyTime($param, 'insert_time');
                $new_array[] = $inter_array;
            }
        }

        $keys = array_keys(current($new_array));
        $keys = array_map([$this, 'escapeColumn'], $keys);
        foreach ($new_array as $array) {
            $question_marks[] = '(' . $this->placeholders('?', sizeof($keys)) . ')';
            array_push($insert_values, ...array_values($array));
        }

        $this->bind = $insert_values;
        $rs = $this->query('INSERT INTO ' . $this->getTable() . ' (' . implode(', ', $keys) . ') VALUES ' . implode(',', $question_marks), ['insert'], $this->bind);
        $this->initSelect();
        return $rs;
    }

    public function batchUpdate($params, $allow = [])
    {
        $this->startTrans();
        try {
            foreach ($params as $param) {
                if (!isset($param[$this->key])) {
                    return false;
                }
                $param = $this->supplyTime($param, 'update_time');
                $this->save($param, $allow);
            }
            $this->endTrans();
            return true;
        } catch (\Throwable $e) {
            $this->rollback();
            return false;
        }
    }

    public function getTable($table = '')
    {
        if ($table) {
            return $this->escapeTable($this->getTablePrefix() . $table);
        } else {
            return $this->escapeTable($this->table);
        }
    }

    public function getTablePrefix()
    {
        return C('database.' . $this->db_set)['prefix'] ?? '';
    }

    public function delete($wh = ['id' => 0])
    {
        $where = '';

        if (count($this->bind_where) > 0) {
            $where = $this->getBindWhere();
        } else {
            if (is_array($wh) && count($wh) > 0) {
                $this->where($wh);
                $where = $this->select['where'];
            }
        }

        if ($where) {
            $rs = $this->query('DELETE FROM ' . $this->getTable() . ' ' . $where, ['delete'], $this->bind);
            $this->initSelect();
            return $rs;
        }
    }

    protected function convertOrder($order)
    {
        $prefix = 'ORDER BY';
        $orders = [];
        $types = ['ASC', 'DESC'];
        if (is_array($order)) {
            foreach ($order as $key => $value) {
                $value = strtoupper($value);
                if (in_array($value, $types)) {
                    $judge = $this->checkRaw($key);
                    if ($judge != '?') {
                        $orders[] = $judge . ' ' . $value;
                    } else {
                        $orders[] = $this->escapeColumn($key) . ' ' . $value;
                    }
                } else {
                    Error::showError('只能为正序或倒序');
                }
            }

            return $prefix . ' ' . implode(',', $orders);
        } else {
            return $prefix . ' ' . $order;
        }
    }

    public function orderby($order)
    {
        $this->select['orderby'] = $this->convertOrder($order);
        return $this;
    }

    public function lock($type = 'update')
    {
        ($type === 'update') ? $this->select['lock'] = ' FOR UPDATE' : $this->select['lock'] = ' LOCK IN SHARE MODE';
        return $this;
    }

    public function order($order)
    {
        $this->orderby($order);
        return $this;
    }

    public function having($mix)
    {
        $prefix = 'HAVING';
        $having = '';
        if (is_array($mix)) {
            foreach ($mix as $key => $value) {
                $having .= $key . $this->escapeValue($value);
            }
        } else {
            Error::showError('having 参数必须为数组');
        }

        $this->select['having'] = $prefix . ' ' . $having;
        return $this;
    }

    public function chunk($size, $func, $order = 'asc', $counter_break = '')
    {
        $table = $this->getTable();
        $times = 1;
        $this->orderby([$this->key => $order]);
        $rs = $this->limit([$size])->get(['chunk']);

        $options = $this->select;
        $column = $this->getSelectColumn($this->select['select']);
        $fields = explode(',', $column);
        if (!in_array($this->key, $fields) && $fields[0] != '*') {
            Error::showError('chunk方法查询字段必须带主键');
        }

        $where = str_replace('WHERE', '', $this->getBindWhere() ?: 'WHERE 1');
        $binds = $this->bind;
        while ($rs) {
            if (false === call_user_func($func, $rs)) {
                return false;
            }

            $times++;
            if ($counter_break && $times >= $counter_break) {
                return false;
            }

            $last_rs = end($rs);
            $opera = ($order == 'asc') ? '>' : '<';
            $this->bind_where = [$where . ' AND ' . $this->key . $opera . $last_rs[$this->key]];
            $this->bind = $binds;
            $options['limit'] = ' LIMIT ' . $size;
            $sql_array = $this->sortSql($options);
            $sql = $this->buildSql($sql_array);
            $rs = $this->query($sql, ['chunk'], $this->bind);
        }

        $this->initSelect();
        return true;
    }

    public function toSql()
    {
        $sql = $this->buildSql();
        return $this->getLastQuery($sql, $this->bind);
    }

    public function sortSql($options)
    {
        $sorts = ['select', 'from', 'join', 'left join', 'inner join', 'right join', 'outter join', 'where', 'groupby',
            'group', 'having', 'orderby', 'order', 'limit', 'union', 'lock'];
        $rs = [];
        foreach ($sorts as $value) {
            if (isset($options[$value])) {
                $rs[$value] = $options[$value];
            }
        }

        return $rs;
    }

    public function groupby($field)
    {
        $this->select['groupby'] = 'GROUP BY ' . $this->escapeFields($field);
        return $this;
    }

    public function group($field)
    {
        $this->groupby($field);
        return $this;
    }

    public function join($table, $on, $type = 'left')
    {
        $join = $type . ' join';
        $this->select[$join][] = $join . ' ' . $this->getTable($table) . ' ON ' . $on;
        return $this;
    }

    public function count($page = '')
    {
        $params = $this->select;
        $field = $page ? '*' : ($this->getSelectColumn() ?: '*');
        $params['select'] = 'SELECT COUNT(' . $field . ')';
        unset($params['limit'], $params['orderby']);
        $sql = $this->buildSql($params);
        if ($page) {
            $special = ['distinct', 'group by'];
            if (str_ireplace($special, '', $sql) != $sql) {
                return $this->query($sql, ['update', 'COUNT(*)'], $this->bind);
            } else {
                return $this->query($sql, ['page', 'COUNT(*)'], $this->bind);
            }
        } else {
            $rs = $this->query($sql, ['value', 'COUNT(*)'], $this->bind);
            $this->initSelect();
            return $rs;
        }
    }

    public function page($per_page, $current_page = '', $show_link = false)
    {
        $per_page = intval($per_page);
        $total = $this->count(true);
        $page = Pagination::getClass([
            'total' => $total, // 总条数
            'per_page' => $per_page, // 每页显示的条数
        ]);
        $current = $current_page ?: $page->getPage();
        $start = $per_page * ($current - 1);
        if ($show_link) {
            $data['links'] = $page->links();
        }

        $data['total'] = $total;
        $data['rs'] = ($total > 0) ? $this->limit(intval($start), intval($per_page))->get() : [];
        $this->initSelect();
        return $data;
    }

    public function pageData($per_page, $current)
    {
        $per_page = intval($per_page);
        $start = $per_page * ($current - 1);
        return $this->limit(intval($start), intval($per_page))->get();
    }
}
