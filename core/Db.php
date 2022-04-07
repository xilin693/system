<?php

namespace king\core;

use PDO;
use king\core\Error;
use king\lib\Log;

class Db
{
    protected $links = [];
    protected $trans_times = 0;
    protected $db;
    private $special_opt = false;
    protected $current_link;
    private $max_slave = 0;
    private $select = [];
    private $slave_name = '';
    protected $debug = [];
    public $attr;
    public $master;
    public $class;

    public function isBreak($e)
    {
        $reconnect = $this->db['reconnect'] ?? false;
        if (!$reconnect) {
            return false;
        }

        $info = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'failed with errno',
        ];

        $error = $e->getMessage();
        foreach ($info as $msg) {
            if (false !== stripos($error, $msg)) {
                return true;
            }
        }
        return false;
    }

    public function setDebug($level = 1) // level1不输出预处理,level2含预处理,level3不输出,直接写入日志
    {
        $this->debug[$this->class] = $level;
    }

    public function debugAll($level = 1)
    {
        $this->debug['all'] = $level;
    }

    public function __construct($db_set = 'default')
    {
        $this->dbs = C('database.*');
        $this->db_set = $db_set;
        if (isset($this->dbs[$db_set])) {
            $this->db = $this->dbs[$db_set];
        } else {
            Error::showError('db instance:' . $db_set . ' not found');
        }

        $this->slave_name = $db_set . '_slave';
        if (isset($this->dbs[$this->slave_name])) {
            $this->max_slave = count($this->dbs[$this->slave_name]);
        }
    }

    public function connect($select = '')
    {
        $options = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $this->slave_name = $this->db_set . '_slave';
        if (isset($this->dbs[$this->slave_name])) {
            $this->max_slave = count($this->dbs[$this->slave_name]);
        }

        if ($this->max_slave > 0 && $select == true && !$this->trans_times) {
            $rand = mt_rand(1, $this->max_slave) - 1;
            $this->db = $this->dbs[$this->slave_name][$rand];
            $link_id = $this->db_set . '_slave_' . $rand;
        } else {
            $link_id = $this->db_set . '_master';
            $this->db = $this->dbs[$this->db_set];
        }

        if (!isset($this->links[$link_id])) {
            $this->timestamp = $this->db['timestamp'] ?? false;
            $port = $this->db['port'] ?? '3306';
            $charset = $this->db['charset'] ?? 'utf8mb4';
            $dsn = 'mysql:host=' . $this->db['host'] . ';port=' . $port . ';dbname=' . $this->db['db'] . ';charset=' . $charset;

            try {
                $this->links[$link_id] = new PDO($dsn, $this->db['user'], $this->db['password'], $options);
            } catch (\PDOException $e) {
                Log::write($e->getMessage());
                Error::showError('连接失败,请查看系统日志');
            }
        }

        $this->current_link = $this->links[$link_id];
    }

    public function quote($value)
    {
        is_object($this->current_link) or $this->connect();

        return $this->current_link->quote($value);
    }

    public function escapeValue($value)
    {
        is_object($this->current_link) or $this->connect();
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $value = 'null';
        } else {
            $value = $this->quote($value);
        }

        return $value;
    }

    public function e($value)
    {
        return $this->escapeValue($value);
    }

    public function checkRaw($value)
    {
        if (!is_array($value)) {
            $values = explode(',', $value);
            if (end($values) == 'raw') {
                return str_replace(',raw', '', $value);
            } else {
                return '?';
            }
        } else {
            Error::showError('where字段参数有误');
        }
    }

    public function escapeTable($table)
    {
        $tables = explode(' ', $table);
        if (isset($tables[1])) {
            return '`' . str_replace('.', '`.`', $tables[0]) . '`' . ' ' . $tables[1];
        } else {
            return '`' . str_replace('.', '`.`', $tables[0]) . '`';
        }
    }

    public function startTrans()
    {
        $this->current_link = $this->links[$this->db_set . '_master'] ?? '';
        is_object($this->current_link) or $this->connect(false);
        ++$this->trans_times;
        try {
            if (1 == $this->trans_times) {
                $this->current_link->beginTransaction();
            } elseif ($this->trans_times > 1 && $this->supportSavepoint()) {
                $this->current_link->exec(
                    $this->parseSavepoint('trans' . $this->trans_times)
                );
            }
        } catch (\Throwable $e) {
            if ($this->isBreak($e)) {
                --$this->trans_times;
                return $this->close()->startTrans();
            }

            throw $e;
        }
    }

    public function endTrans()
    {
        $this->current_link = $this->links[$this->db_set . '_master'] ?? '';
        is_object($this->current_link) or $this->connect(false);
        if (1 == $this->trans_times) {
            $this->current_link->commit();
        }

        --$this->trans_times;
    }

    public function rollback()
    {
        $this->current_link = $this->links[$this->db_set . '_master'] ?? '';
        is_object($this->current_link) or $this->connect(false);
        if (1 == $this->trans_times) {
            $this->current_link->rollBack();
        } elseif ($this->trans_times > 1 && $this->supportSavepoint()) {
            $this->current_link->exec(
                $this->parseSavepointRollBack('trans' . $this->trans_times)
            );
        }

        $this->trans_times = max(0, $this->trans_times - 1);
    }

    public function escapeColumn($column, $raw = '')
    {
        $columns = explode(',', $column);
        if ($columns[0] == 'raw') {
            Error::showError('带raw的字段只能传数组');
        }

        if (end($columns) == 'raw') {
            $column = str_replace(',raw', '', $column);
            return $this->escapeColumn($column, true);
        }

        if ($column == '*' || $raw) {
            return $column;
        } else {
            $column = trim($columns[0]);
        }

        if (preg_match('/(avg|count|sum|max|min)\(\s*(.*)\s*\)(\s*as\s*(.+)?)?/i', $column, $matches)) {
            if (count($matches) == 3) {
                return $matches[1] . '(' . $this->escapeColumn($matches[2]) . ')';
            } else if (count($matches) == 5) {
                return $matches[1] . '(' . $this->escapeColumn($matches[2]) . ') AS ' . $this->escapeColumn($matches[4]);
            }
        }

        if (!preg_match('/\b(?:rand|all|distinct(?:row)?|high_priority|sql_(?:small_result|b(?:ig_result|uffer_result)|no_cache|ca(?:che|lc_found_rows)))\s/i', $column)) {
            if (stripos($column, ' AS ') !== false) {
                $column = str_ireplace(' AS ', ' AS ', $column);
                $column = array_map([$this, __FUNCTION__], explode(' AS ', $column));
                return implode(' AS ', $column);
            }

            return preg_replace('/[^.*]+/', '`$0`', $column);
        }
        $parts = explode(' ', $column);
        $column = '';

        for ($i = 0, $c = count($parts); $i < $c; $i++) {
            if ($i == ($c - 1)) {
                $column .= preg_replace('/[^.*]+/', '`$0`', $parts[$i]);
            } else {
                $column .= $parts[$i] . ' ';
            }
        }
        return $column;
    }

    public function query($sql, $param = '', $bind = [])
    {
        $debug = false;
        if (!empty($this->debug['all'])) {
            $debug = $this->debug['all'];
        }

        if (!empty($this->debug[$this->class])) {
            $debug = $this->debug[$this->class];
        }

        $select = false;
        $match = preg_match('#\b(?:INSERT|UPDATE|REPLACE|DELETE|SELECT)\b#i', $sql); // 只允许执行select,insert,update,delete,replace五种方法
        if (strtolower(substr(trim($sql), 0, 6)) == 'select' && !$this->master) {
            $select = true;
        }

        $this->connect($select);
        if ($debug == 2) {
            echo 'prepare:' . substr($sql, 0, 500) . '<br />';
        } elseif ($debug == 3) {
            Log::write('prepare:' . $sql);
        }
        try {
            $this->connect_times = 0;
            $this->stmt = $this->current_link->prepare($sql);
            $return = false;
            $get_attr = false;
            $rs = $this->stmt->execute($bind);

            if ($debug == 1) {
                echo $this->getLastQuery($sql, $bind) . '<br />';
            } elseif ($debug == 3) {
                Log::write($this->getLastQuery($sql, $bind));
            }

            if ($rs) {
                if (is_array($param)) {
                    switch ($param[0]) {
                        case 'insert':
                            $return = ($this->current_link->lastInsertId() > 0) ? $this->current_link->lastInsertId() : true;
                            break;
                        case 'update':
                            $return = $this->stmt->rowCount();
                            break;
                        case 'find':
                            $return = $this->stmt->fetch(PDO::FETCH_ASSOC);
                            $get_attr = true;
                            break;
                        case 'column':
                            $rs_all = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                            $return = array_column($rs_all, $param[1]);
                            break;
                        case 'chunk':
                            $return = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;
                        case 'value':
                            $return = $this->stmt->fetch(PDO::FETCH_COLUMN);
                            break;
                        case 'page':
                            $return = $this->stmt->fetch(PDO::FETCH_COLUMN);
                            break;
                        case 'delete':
                            $return = $rs;
                            break;
                        default:
                            break;
                    }
                } else {
                    $return = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                    $get_attr = true;
                }
            }
        } catch (\PDOException $e) {
            if ($this->isBreak($e)) {
                Log::write(date('H:i:s') . 'reconnect');
                return $this->close()->query($sql, $param);
            } else {
                throw new \RuntimeException($e->getMessage());
            }
        } catch (\Throwable | \Exception $e) {
            if ($this->isBreak($e)) {
                Log::write(date('H:i:s') . 'reconnect');
                return $this->close()->query($sql, $param);
            } else {
                throw new \RuntimeException($e->getMessage());
            }
        }

        if ($get_attr && $return && $this->attr) {
            $class = $this->class;
            foreach ($return as $key => &$value) {
                if (is_array($value)) {
                    if (isset($value[1])) {
                        Error::showError('获取器错误');
                    }

                    foreach ($value as $k => $v) {
                        $new_value = $this->getAttrValue($k, $v, $class);
                        if (is_array($new_value)) {
                            $return[$key][$new_value[1]] = $new_value[0];
                        } else {
                            $return[$key][$k] = $new_value;
                        }
                    }
                } else {
                    $new_value = $this->getAttrValue($key, $value, $class);
                    if (is_array($new_value)) {
                        $return[$new_value[1]] = $new_value[0];
                    } else {
                        $return[$key] = $new_value;
                    }
                }
            }
        }

        return $return;
    }

    private function getAttrValue($key, $value, $class)
    {
        if (!$class) {
            return $value;
        } else {
            $function = 'get' . camelize($key) . 'Attr';
            if (method_exists($class, $function)) {
                return $class::$function($value);
            } else {
                if (property_exists($class, 'date_time')) {
                    $date_time = $class::$date_time;
                    if (!is_array($date_time)) {
                        $date_time = explode(',', $date_time);
                    }

                    if (in_array($key, $date_time)) {
                        if ($value > 0) {
                            return date('Y-m-d H:i:s', $value);
                        } else {
                            return '';
                        }
                    }
                }

                return $value;
            }
        }
    }

    public function getLastQuery($sql, $bind)
    {
        if (is_array($sql)) {
            $sql = implode(';', $sql);
        }

        if (is_array($bind)) {
            foreach ($bind as $value) {
                $value = ($value === '') ? "''" : $value;
                if (!is_numeric($value)) {
                    $value = $this->quote($value);
                }
                $sql = substr_replace($sql, $value, strpos($sql, '?'), 1);
            }
        }

        return rtrim(substr($sql, 0, 500));
    }

    protected function supportSavepoint()
    {
        return true;
    }

    protected function parseSavepoint($name)
    {
        return 'SAVEPOINT ' . $name;
    }

    protected function parseSavepointRollBack($name)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    public function close()
    {
        $this->current_link = null;
        $this->stmt = null;
        $this->links = [];
        return $this;
    }

    public function __destruct()
    {
        $this->current_link = null;
        $this->stmt = null;
        $this->links = [];
    }
}
