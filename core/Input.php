<?php

namespace esp\core;
use esp\library\ext\Xss;

/**
 * Class Input
 * http://php.net/manual/zh/wrappers.php.php
 */

final class Input
{
    const _XSS_CLEAN = true;//是否默认进行防注检查

    /**
     * 获取get
     * @param string $param
     * @param null $autoValue 如果有指定默认值，则返回数据也根据该值类型强制转换：int,bool,或是正则表达式
     * @return bool|null|string
     */
    public static function get($param, $autoValue = null)
    {
        return self::requestParams($_GET, $param, $autoValue);
    }

    public static function post($param, $autoValue = null)
    {
        return self::requestParams($_POST, $param, $autoValue);
    }

    public static function any($param, $autoValue = null)
    {
        return self::requestParams($_REQUEST, $param, $autoValue);
    }

    private static function date_zone($autoValue)
    {
        if (is_null($autoValue)) {
            $d = date('Y-m-d');
            $t = strtotime($d);
            $value = [$d, $d, $t, $t];
        } else if (is_array($autoValue)) {
            if (is_int($autoValue[0])) {
                $t0 = $autoValue[0];
                $d0 = date('Y-m-d', $autoValue[0]);
            } else {
                $d0 = $autoValue[0];
                $t0 = strtotime($d0);
                if (!$t0) {
                    $d0 = date('Y-m-d');
                    $t0 = strtotime($d0);
                }
            }
            if (is_int($autoValue[1])) {
                $t1 = $autoValue[1];
                $d1 = date('Y-m-d', $t1);
            } else {
                $d1 = $autoValue[1];
                $t1 = strtotime($d1);
                if (!$t1) {
                    $d1 = date('Y-m-d');
                    $t1 = strtotime($d1);
                }
            }
            $value = [$d0, $d1, $t0, $t1];

        } else if (is_int($autoValue)) {
            $d = date('Y-m-d', $autoValue);
            $value = [$d, $d, $autoValue, $autoValue];
        } else {
            $t = strtotime($autoValue);
            if (!$t) {
                $autoValue = date('Y-m-d');
                $t = strtotime($autoValue);
            }
            $value = [$autoValue, $autoValue, $t, $t];
        }
        return $value;
    }

    /**
     * 读取并简单整理要获取的变量值
     * @param array $data
     * @param $param
     * @param null $autoValue
     * @return array|bool|float|int|mixed|null
     */
    private static function requestParams(array &$data, $params, $autoValue = null)
    {
        $value = Array();
        $index = 0;
        if (!is_array($params)) $params = [$params => $autoValue];
        foreach ($params as $param => $autoValue) {
            if (is_int($param) and is_string($autoValue)) list($param, $autoValue) = [$autoValue, null];

            if ($param === 'date_zone') {
                $date = $data[$param] ?? '';
                if (!empty($date)) {
                    $date = str_replace('%3A', ':', $date);
                    $date = str_replace(['+~+', '+-+', ' - '], '~', $date);
                    $day = explode('~', $date);
                    if (!isset($day[1])) $day[1] = $day[0];
                    $day[0] = trim($day[0]);
                    $day[1] = trim($day[1]);
                    $time = [strtotime($day[0]), strtotime($day[1])];

                    if (!$time[0] or !$time[1]) {//可能是非法数据
                        $value[$index] = self::date_zone($autoValue);
                    } else {
                        $value[$index] = [$day[0], $day[1], $time[0], $time[1]];
                    }
                } else {//基本是初始页面状态
                    $value[$index] = self::date_zone($autoValue);
                }
            } else if (!isset($data[$param])) {
                if ($autoValue === 'date_time') {
                    $value[$index] = strtotime(date('Y-m-d'));
                } else {
                    $value[$index] = $autoValue;
                }
            } elseif (is_int($autoValue)) $value[$index] = intval(trim($data[$param]));
            elseif (is_float($autoValue)) $value[$index] = floatval(trim($data[$param]));
            elseif (is_bool($autoValue)) $value[$index] = boolval(trim($data[$param]));
            elseif (is_array($autoValue)) {
                if (is_array($data[$param])) {
                    $value[$index] = $data[$param];
                } else {
                    $value[$index] = json_decode($data[$param], true);
                }
            } elseif ($autoValue === '') {//过滤几个符号
                $value[$index] = preg_replace('/[\"\'\%\&\^\$\#\(\)\[\]\{\}\?]/', '', trim($data[$param]));
            } elseif ($autoValue === 'date_time') {
                $date = trim($data[$param] ?? '');
                if (!!$date) {
                    $date = str_replace('+', ' ', $date);
                    $date = str_replace('%3A', ':', $date);
                    $value[$index] = @strtotime($date);
                } else {
                    $value[$index] = 0;
                }

            } else {
                if (is_array($data[$param])) {
                    $value[$index] = json_encode($data[$param], 256);
                } else {
                    $value[$index] = trim($data[$param]);
                }
                //autoValue是一个正则表达式，常用的如：/^\w+$/
                if ($autoValue and is_match($autoValue)) {
                    if (!preg_match($autoValue, $value[$index])) $value[$index] = null;
                } elseif (self::_XSS_CLEAN) {
                    Xss::clear($value[$index]);
                }
            }
            $index++;
        }
        return $index === 1 ? $value[0] : $value;
    }

    /**
     * 获取php:://input
     */
    public static function php($key = null)
    {
        $val = file_get_contents("php://input");
        parse_str($val, $arr);
        return $key ? $arr[$key] : $arr;
    }

    /**
     * 在POST表单中，submit用
     * <input type="image" src="<?=Url::admin()?>res/img/credit/visa.png" name="submit" />
     * 这里检查点击时鼠标位置
     * 若都为0，则可能是由JS控制提交
     * $sub为 name="submit"的值
     *
     * */
    public static function isClick($sub = 'submit')
    {
        if (isset($_POST[$sub . '_x']) and isset($_POST[$sub . '_y'])) {
            return $_POST[$sub . '_x'] > 0 or $_POST[$sub . '_y'] > 0;
        }
        return true;
    }

    /**
     * 读取文件目录所有文件
     * @param string $path
     * @param string $ext 只读取指定文件类型
     * @return array
     */
    public static function path(string $path, string $ext = '')
    {
        $array = Array();
        $dir = new \DirectoryIterator($path);
        if ($ext) $ext = ltrim($ext, '.');
        foreach ($dir as $f) {
            if ($f->isFile()) {
                if ($ext) {
                    if ($f->getExtension() === $ext) $array[] = $f->getFilename();
                } else {
                    $array[] = $f->getFilename();
                }
            }
        }
        return $array;
    }

}