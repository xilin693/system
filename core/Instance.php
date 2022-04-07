<?php

namespace king\core;

class Instance
{
    protected static $bind;

    public static function getClass(...$vars)
    {
        $mix = md5(serialize($vars));
        $class  = static::class;
        $key = $class . '_' . $mix;
        if (!isset(self::$bind[$key])) {
            $reflect = new \ReflectionClass($class);
            $constructor = $reflect->getConstructor();
            $args = $constructor ? self::bindParams($constructor, $vars) : [];
            self::$bind[$key] = $reflect->newInstanceArgs($args);
        }
        return self::$bind[$key];
    }

    public static function getInstance($class_name)
    {
        $class_param = self::getMethodParams($class_name);

        return (new \ReflectionClass($class_name))->newInstanceArgs($class_param);
    }

    public static function make($class_name, $method_name, $params = [])
    {
        $instance = self::getInstance($class_name);
        $class_param = self::getMethodParams($class_name, $method_name);

        return $instance->{$method_name}(...array_merge($class_param, $params));
    }

    protected static function getMethodParams($class_name, $method_name = '__construct')
    {
        $class = new \ReflectionClass($class_name);
        $class_param = [];
        if ($class->hasMethod($method_name)) {
            $construct = $class->getMethod($method_name);
            $params = $construct->getParameters();
            if (count($params) > 0) {
                foreach ($params as $key => $param) {
                    if ($new_class = $param->getType()) {
                        $param_class_name = $new_class->getName();
                        $args = self::getMethodParams($param_class_name);
                        $class_param[] = (new \ReflectionClass($new_class->getName()))->newInstanceArgs($args);
                    }
                }
            }
        }

        return $class_param;
    }

    public static function bindParams($reflect, $vars = [])
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [];
        }

        reset($vars);
        $type   = key($vars) === 0 ? 1 : 0;
        $params = $reflect->getParameters();
        foreach ($params as $param) {
            $name      = $param->getName();
            $lowerName = Loader::parseName($name);
            if (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && isset($vars[$name])) {
                $args[] = $vars[$name];
            } elseif (0 == $type && isset($vars[$lowerName])) {
                $args[] = $vars[$lowerName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                Error::showError('method param miss:' . $name);
            }
        }

        return $args;
    }

}