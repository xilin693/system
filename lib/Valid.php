<?php

namespace king\lib;

use king\core\Instance;
use king\lib\Lang;
use king\lib\exception\BadRequestHttpException;

class Valid extends Instance
{
    private static $instance;
    private $error = false;
    private $rules = [];
    private $class;
    private $scene;
    private $current;
    public $data;
    protected $hide_scene = [];

    public function __construct($data = '', $current = '')
    {
        $this->data = $data ?: P();
        $this->current = $current;
    }

    public function setScene($scene)
    {
        $this->scene = $scene;
    }

    public function addRule($field, $rules, $label = '')
    {
        if ($this->current) {
            if (!is_array($this->scene)) {
                throw new BadRequestHttpException('调用场景时, 必须设置验证场景规则');
            }

            if (!isset($this->scene[$this->current])) {
                throw new BadRequestHttpException($this->current . '验证场景不存在');
            }

            $keys = $this->scene[$this->current] ?? '';
            if (!is_array($keys)) {
                throw new BadRequestHttpException('场景包含的字段必须为数组');
            }

            if (isset($this->hide_scene[$this->current])) {
                $remove_fields = $this->hide_scene[$this->current];
                if (!in_array($field, $remove_fields)) {
                    $this->rules[] = [$field, $rules, $label];
                }
            } else {
                if (in_array($field, $keys)) {
                    $this->rules[] = [$field, $rules, $label];
                }
            }
        } else {
            $this->rules[] = [$field, $rules, $label];
        }
    }

    public function hideSceneField($scene, $field)
    {
        $this->scene[$scene] = [];
        if (!is_array($field)) {
            $field = [$field];
        }

        $this->hide_scene[$scene] = $field;
    }

    public function run($class = '')
    {
        $complex_rule = ['in', 'ext', 'requireIf', 'length'];
        foreach ($this->rules as $rule) {
            $field = $rule[0];
            $multi_rule = $rule[1];
            $label = $rule[2] ?: $field;
            if (isset($this->data[$field]) && (strpos($multi_rule, 'require') !== false || $this->data[$field] !== '')) {
                $rules = explode('|', $multi_rule);
                foreach ($rules as $one_rule) {
                    $real_rule = $one_rule;
                    $param = '';
                    if (strpos($one_rule, ',') !== false) {
                        $tmp_rule = explode(',', $one_rule);
                        $real_rule = $tmp_rule[0];
                        if (in_array($real_rule, $complex_rule)) {
                            array_shift($tmp_rule);
                            $param = $tmp_rule;
                            if (strpos($param[0], '[') !== false) {
                                $param_str = str_replace(['[', ']'], '', implode(',', $param));
                                $param = array_map('trim', explode(',', $param_str));
                            }
                        } else {
                            $param = $tmp_rule[1];
                        }
                    }

                    if (method_exists($this, $real_rule)) {
                        if ($param !== '') {
                            $this->$real_rule($this->data[$field], $param, $label);
                        } else {
                            $this->$real_rule($this->data[$field], $label);
                        }
                    } else {
                        $class = $class ?: (C('valid_path') ? 'app\validate\\' . ucfirst(C('valid_path')) : \king\core\Loader::$run_class);
                        if (method_exists($class, $real_rule)) {
                            $class = new $class;
                            $class->$real_rule($this->data[$field], $this, $label);
                        } else {
                            $this->setError(Lang::get(['valid rule not found', [$real_rule]]));
                        }
                    }

                    if ($this->getError() != '') { // 如果有问题直接返回
                        return false;
                    }
                }
            } else {
                if (!isset($this->data[$field])) {
                    $label = $label ?: $field;
                    $pos = strpos($multi_rule, 'require');
                    $alert = true;
                    if ($pos !== false) {
                        $next_pos_text = substr($multi_rule, $pos + 7, 1);
                        $find_str = substr($multi_rule, $pos);
                        $values = explode(',', $find_str);
                        if (isset($values[1])) {
                            $valid_field = explode('|', $values[1]);
                            if ($next_pos_text == 'I') {
                                $v2 = $values[2] ?? '';
                                $v2 = explode('|', $v2);
                                if (isset($this->data[$valid_field[0]]) && $this->data[$valid_field[0]] == $v2[0]) {
                                    $alert = true;
                                } else {
                                    $alert = false;
                                }
                            } elseif ($next_pos_text == 'W') {
                                if (isset($this->data[$valid_field[0]]) && !emptyNot0($this->data[$valid_field[0]])) {
                                    $alert = true;
                                } else {
                                    $alert = false;
                                }
                            }
                        }

                        if ($alert == true) {
                            return $this->setError(Lang::get(['valid field not set', [$label]])); // 字段问题直接返回
                        }
                    }
                } else {
                    $pos = strpos($multi_rule, 'notEmpty');
                    if ($pos !== false && $this->data[$field] === '') {
                        return $this->setError(Lang::get(['valid empty', [$label]]));
                    }
                }
            }
        }

        return true;
    }

    public function notEmpty($value, $label = '')
    {
        $value = trim($value);
        if ($value === '') {
            $this->setError(Lang::get(['valid empty', [$label]]));
        }
    }

    public function getError()
    {
        return $this->error;
    }

    public function setError($newError)
    {
        $this->error = $newError;
        return false;
    }

    public function response($class = '')
    {
        if (!$this->run($class)) {
            throw new BadRequestHttpException($this->getError());
        }
    }

    public function required($value, $label = '')
    {
        if (!is_array($value)) {
            if ((is_null($value) || trim($value) === '')) {
                $this->setError(Lang::get(['valid require', [$label]]));
            }
        }
    }

    public function minLength($value, $val, $label = '')
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (is_array($value)) {
            return (count($value) < $val) ? $this->setError(Lang::get(['valid minLength', [$label, $val]])) : true;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen($value) < $val) ? $this->setError(Lang::get(['valid minLength', [$label, $val]])) : true;
        }

        return (strlen($value) < $val) ? $this->setError(Lang::get(['valid minLength', [$label, $val]])) : true;
    }

    public function length($value, $val, $label = '')
    {
        if (!is_array($val) || count($val) < 2) {
            $this->setError(Lang::get(['valid param', [$label]]));
        } else {
            $match = $val[0] . $val[1];
            if (preg_match("/[^0-9]/", $match)) {
                return false;
            }

            if (is_array($value)) {
                $length = count($value);
            } else {
                $length = mb_strlen((string)$value);
            }

            if ($length < $val[0] || $length > $val[1]) {
                $this->setError(Lang::get(['valid length', [$label, $val[0], $val[1]]]));
            }
        }
    }

    public function maxLength($value, $val, $label = '')
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (is_array($value)) {
            return (count($value) > $val) ? $this->setError(Lang::get(['valid maxLength', [$label, $val]])) : true;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen($value) > $val) ? $this->setError(Lang::get(['valid maxLength', [$label, $val]])) : true;
        }

        return (strlen($value) > $val) ? $this->setError(Lang::get(['valid maxLength', [$label, $val]])) : true;
    }

    public function email($value, $label = '')
    {
        $label = $label ? $label : '邮箱';
        if (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $value)) {
            $this->setError(Lang::get(['valid email', [$label]]));
        }
    }

    public function mobile($value, $label = '')
    {
        $label = $label ? $label : '手机号';
        if (!preg_match("/^(13|14|15|16|17|18|19)[0-9]{9}$/", $value)) {
            $this->setError(Lang::get(['valid mobile', [$label]]));
        }
    }

    public function url($value, $label = '')
    {
        $label = $label ? $label : '网址';
        $return = filter_var($value, FILTER_VALIDATE_URL);
        if (!$return) {
            $this->setError(Lang::get(['valid url', [$label]]));
        }
    }

    public function ip($value, $label = '')
    {
        $label = $label ? $label : 'ip地址';
        $flags = FILTER_FLAG_NO_RES_RANGE;
        $return = filter_var($value, FILTER_VALIDATE_IP, $flags);
        if (!$return) {
            $this->setError(Lang::get(['valid ip', [$label]]));
        }
    }

    public function alpha($value, $label) // 字母
    {
        if (!preg_match("/^([a-zA-Z])+$/i", $value)) {
            $this->setError(Lang::get(['valid alpha', [$label]]));
        } else
            return true;
    }

    public function alphaDash($value, $label) // 数字字母下划线
    {
        if (!preg_match("/^([-a-zA-Z0-9_-])+$/i", $value)) {
            $this->setError(Lang::get(['valid alphaDash', [$label]]));
        }
    }

    public function regex($value, $regex, $label) {
        if (!preg_match($regex, $value)) {
            $this->setError($label);
        }
    }

    public function alphaNum($value, $label) // 数字字母
    {
        if (!preg_match("/^([a-zA-Z0-9])+$/i", $value)) {
            $this->setError(Lang::get(['valid alphaNum', [$label]]));
        }
    }

    public function isInt($value, $label)
    {
        if (!preg_match('/^[-+]?[0-9]+$/', $value)) {
            $this->setError(Lang::get(['valid isInt', [$label]]));
        }
    }

    public function int($value, $label)
    {
        return $this->isInt($value, $label);
    }

    public function gt($value, $min, $label)
    {
        if (!is_numeric($value)) {
            $this->setError(Lang::get(['valid numeric', [$label]]));
        } elseif ($value <= $min) {
            $this->setError(Lang::get(['valid gt', [$label, $min]]));
        }
    }

    public function gte($value, $min, $label)
    {
        if (!is_numeric($value)) {
            $this->setError(Lang::get(['valid numeric', [$label]]));
        } elseif ($value < $min) {
            $this->setError(Lang::get(['valid gte', [$label, $min]]));
        }
    }

    public function lt($value, $max, $label)
    {
        if (!is_numeric($value)) {
            $this->setError(Lang::get(['valid numeric', [$label]]));
        } elseif ($value >= $max) {
            $this->setError(Lang::get(['valid lt', [$label, $max]]));
        }
    }

    public function ext($value, $exts, $label)
    {
        $ext = pathinfo($value, PATHINFO_EXTENSION);
        if (!is_array($exts)) {
            throw new BadRequestHttpException('后缀范围必须为数组');
        }

        if (!in_array($ext, $exts)) {
            $this->setError(Lang::get(['valid ext', [$label]]));
        }
    }

    public function requireIf($value, $rule, $label)
    {
        if (isset($this->data[$rule[0]]) && ($this->data[$rule[0]] == $rule[1])) {
            if (emptyNot0($value)) {
                $this->setError(Lang::get(['valid require', [$label]]));
            }
        }
    }

    public function requireWith($value, $field, $label)
    {
        if (isset($this->data[$field])) {
            $val = $this->data[$field];
            if (!emptyNot0($val) && emptyNot0($value)) {
                $this->setError(Lang::get(['valid require', [$label]]));
            }
        }
    }

    public function lte($value, $max, $label)
    {
        if (!is_numeric($value)) {
            $this->setError(Lang::get(['valid numeric', [$label]]));
        } elseif ($value > $max) {
            $this->setError(Lang::get(['valid lte', [$label, $max]]));
        }
    }

    public function size($value, $size, $label)
    {
        $value = trim($value);
        if (is_array($value)) {
            $length = count($value);
        } else {
            $length = strlen((string)$value);
        }
        if ($length != $size) {
            $this->setError(Lang::get(['valid size', [$label, $size]]));
        }
    }

    public function json($value, $label)
    {
        $data = @json_decode((string)$value, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->setError(Lang::get(['valid json', [$label]]));
        } else {
            if (!is_array($data)) {
                $this->setError(Lang::get(['valid json', [$label]]));
            }
        }
    }

    public function confirm($value, $val, $label)
    {
        if (!isset($this->data[$val])) {
            $this->setError(Lang::get(['valid data not found', [$label]]));
        } else {
            $val = $this->data[$val];
            if ($value <> $val) {
                $this->setError(Lang::get(['valid confirm', [$label]]));
            }
        }
    }

    public function equal($value, $val, $label)
    {
        if ($value <> $val) {
            $this->setError(Lang::get(['valid equal', [$label, $val]]));
        }
    }

    public function chs($value, $label)
    {
        if (!preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $value)) {
            $this->setError(Lang::get(['valid chs', [$label]]));
        }
    }

    public function chsAlpha($value, $label)
    {
        if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z]+$/u', $value)) {
            $this->setError(Lang::get(['valid chsAlpha', [$label]]));
        }
    }

    public function chsAlphaNum($value, $label)
    {
        if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9]+$/u', $value)) {
            $this->setError(Lang::get(['valid chsAlphaNum', [$label]]));
        }
    }

    public function idCard($value, $label)
    {
        if (!preg_match('/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/', $value)) {
            $this->setError(Lang::get(['valid idCard', [$label]]));
        }
    }

    public function isArray($value, $label)
    {
        if (!is_array($value)) {
            $this->setError(Lang::get(['valid isArray', [$label]]));
        }
    }

    public function array($value, $label)
    {
        return $this->isArray($value, $label);
    }

    public function in($value, $val, $label)
    {
        if (!in_array($value, $val)) {
            $this->setError(Lang::get(['valid in', [$label]]));
        }
    }

    public function dateTime($val, $label)
    {
        if (!preg_match("/^\d{4}-\d{1,2}-\d{1,2} \d{2}:\d{2}:\d{2}$/", $val)) {
            $this->setError(Lang::get(['valid dateTime', [$label]]));
        }
    }

    public function isDate($val, $label)
    {
        if (!preg_match("/^\d{4}-\d{1,2}-\d{1,2}$/", $val)) {
            $this->setError(Lang::get(['valid date', [$label]]));
        }
    }

    public function date($val, $label)
    {
        $this->isDate($val, $label);
    }

    public function dateFormat($value, $val, $label)
    {
        $rs = date_parse_from_format($val, $value);
        if ($rs['warning_count'] > 0 || $rs['error_count'] > 0) {
            $this->setError(Lang::get(['valid dateFormat', [$label]]));
        }
    }

    public function isFloat($val, $label)
    {
        if (!(preg_match('/^[-+]?[0-9]*\.?[0-9]+$/', $val))) {
            $this->setError(Lang::get(['valid isFloat', [$label]]));
        }
    }

    public function float($val, $label)
    {
        return $this->isFloat($val, $label);
    }

    public function numeric($val, $label)
    {
        if (!is_numeric($val)) {
            $this->setError(Lang::get(['valid numeric', [$label]]));
        }
    }
}
