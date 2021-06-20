<?php
declare(strict_types=1);

namespace esp\lost;

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
    public static function get(string $param, $autoValue = null)
    {
        return self::requestParam($_GET, $param, $autoValue);
    }

    public static function post(string $param, $autoValue = null)
    {
        return self::requestParam($_POST, $param, $autoValue);
    }

    public static function any(string $param, $autoValue = null)
    {
        return self::requestParam($_REQUEST, $param, $autoValue);
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


    private static function requestParam(array $data, string $param, $autoValue = null)
    {
        if (!isset($data[$param])) {
            if ($param === 'date_zone') return self::date_zone($autoValue);
            else if ($autoValue === 'date_time') return _TIME;
            return $autoValue;
        }
        $value = ($data[$param]);
        if ($param === 'date_zone') {
            $date = $value;
            if (!empty($date)) {
                $date = str_replace('%3A', ':', $date);
                $date = str_replace(['+~+', '+-+', ' - '], '~', $date);
                $day = explode('~', $date);
                if (!isset($day[1])) $day[1] = $day[0];
                $day[0] = trim($day[0]);
                $day[1] = trim($day[1]);
                $time = [strtotime($day[0]), strtotime($day[1])];

                if (!$time[0] or !$time[1]) {//可能是非法数据
                    $value = self::date_zone($autoValue);
                } else {
                    $value = [$day[0], $day[1], $time[0], $time[1], 'auto' => false];
                }
            } else {//基本是初始页面状态
                $value = self::date_zone($autoValue);
            }
            if (!isset($value['auto'])) $value['auto'] = true;
            return $value;
        }

        switch (true) {
            case is_string($autoValue):
                if ($autoValue === '') {
                    //\%\&\^\$\#\(\)\[\]\{\}\?
                    $value = preg_replace('#["\']#', '', trim($value));
//                    if ($value && self::_XSS_CLEAN) Xss::clear($value);

                } elseif ($autoValue === 'real') {
                } elseif ($autoValue === 'html') {

                } elseif ($autoValue === 'json') {
                    if (is_string($value)) $value = json_decode($value, true);
                    $value = json_encode(array_map(function ($v) {
                        return ($v);//trim
                    }, $value), 256 | 64);

                } elseif ($autoValue === 'sum') {
                    if (is_string($value)) $value = json_decode($value, true);
                    if (!is_array($value) or empty($value)) $value = [];
                    $sum = 0;
                    foreach ($value as $v) $sum = $sum | intval($v);
                    $value = $sum;

                } elseif ($autoValue === 'array') {
                    if (is_string($value)) $value = json_decode($value, true);
                    if (empty($value)) $value = [];

                } else if ($autoValue === 'date_time') {
                    $date = $value;
                    if (!!$date) {
                        $date = str_replace('+', ' ', $date);
                        $date = str_replace('%3A', ':', $date);
                        $value = strtotime($date);
                    } else {
                        $value = 0;
                    }

                } else if (\esp\helper\is_match($autoValue)) {
                    //autoValue是一个正则表达式，常用的如：/^\w+$/
                    if (!preg_match($autoValue, trim($value))) $value = null;

                }
                break;

            case is_int($autoValue):
                $value = intval(trim($value));
                break;

            case is_float($autoValue):
                $value = floatval(trim($value));
                break;

            case is_bool($autoValue):
                $value = boolval(trim($value));
                break;

            case is_array($autoValue):
                if (!is_array($value)) $value = json_decode(trim($value), true);
                if (isset($autoValue[1]) and ($autoValue[1] === 'bit')) {
                    $sum = 0;
                    foreach ($value as $v) $sum = $sum | intval($v);
                    $value = $sum;
                } else if (isset($autoValue[0]) and is_int($autoValue[0])) {
                    $value = array_map(function ($v) {
                        return intval($v);
                    }, $value);
                }
                break;

            case is_array($value):
                $value = json_encode(trim($value), 256 | 64);
                break;

            default:
                if (!is_null($autoValue) && $value && self::_XSS_CLEAN) Xss::clear(trim($value));

        }
        return $value;
    }

    /**
     * 读取并整理要获取的变量值
     * @param array $data
     * @param $params
     * @param null $autoValue
     * @return array|bool|float|int|mixed|null
     */
    private static function requestParams(array &$data, $params, $autoValue = null)
    {
        $value = array();
        $index = 0;
        if (!is_array($params)) $params = [$params => $autoValue];
        foreach ($params as $param => $autoValue) {
            if (is_int($param) and is_string($autoValue)) list($param, $autoValue) = [$autoValue, null];

            switch (true) {

                case $param === 'date_zone':
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
                            $value[$index] = [$day[0], $day[1], $time[0], $time[1], 'auto' => false];
                        }
                    } else {//基本是初始页面状态
                        $value[$index] = self::date_zone($autoValue);
                    }
                    if (!isset($value[$index]['auto'])) $value[$index]['auto'] = true;
                    break;

                case !isset($data[$param]):
                    if ($autoValue === 'date_time') {
                        $value[$index] = strtotime(date('Y-m-d'));
                    } else {
                        $value[$index] = $autoValue;
                    }
                    break;

                case $autoValue === ''://过滤几个符号
                    $value[$index] = preg_replace('/[\"\'\%\&\^\$\#\(\)\[\]\{\}\?]/', '', trim($data[$param]));
                    break;

                case $autoValue === 'date_time':
                    $date = trim($data[$param] ?? '');
                    if (!!$date) {
                        $date = str_replace('+', ' ', $date);
                        $date = str_replace('%3A', ':', $date);
                        $value[$index] = @strtotime($date);
                    } else {
                        $value[$index] = 0;
                    }
                    break;

                case is_int($autoValue):
                    $value[$index] = intval(trim($data[$param]));
                    break;
                case is_float($autoValue):
                    $value[$index] = floatval(trim($data[$param]));
                    break;
                case is_bool($autoValue):
                    $value[$index] = boolval(trim($data[$param]));
                    break;
                case is_array($autoValue):
                    if (is_array($data[$param])) {
                        $value[$index] = $data[$param];
                    } else {
                        $value[$index] = json_decode($data[$param], true);
                    }
                    break;

                default:
                    if (is_array($data[$param])) {
                        $value[$index] = json_encode($data[$param], 256);
                    } else {
                        $value[$index] = trim($data[$param]);
                    }
                    //autoValue是一个正则表达式，常用的如：/^\w+$/
                    if ($autoValue and \esp\helper\is_match($autoValue)) {
                        if (!preg_match($autoValue, $value[$index])) $value[$index] = null;
                    } elseif (self::_XSS_CLEAN) {
                        Xss::clear($value[$index]);
                    }
            }
            $index++;
        }
        return $index === 1 ? $value[0] : $value;
    }

    public static function quote(string $value)
    {
        return preg_replace('/[\"\'\%\&\^\$\#\(\)\[\]\{\}\?]/', '', trim($value));
    }

    /**
     * 读取post数据流
     * @param string|null $type
     *
     * $type: 指受理的数据是什么类型，
     * json/xml将按相应格式解析
     * 其他类型都按parse_str方式解析
     * 如果解析失败，返回的是空数组
     *
     * @return array
     */
    public static function php(string $type = null): array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) return [];
        switch (true) {
            case $type === 'json':
            case ($input[0] === '{' and $input[-1] === '}'):
            case ($input[0] === '[' and $input[-1] === ']'):
                $arr = json_decode($input, true);
                break;

            case $type === 'xml':
            case ($input[0] === '<' and $input[-1] === '>'):
                $arr = \esp\helper\xml_decode($input, true);
                break;

            case $type === 'string':
                parse_str($input, $arr);
                break;
            default:
                parse_str($input, $arr);
        }

        return $arr ?: [];
    }

    /**
     * 目录
     * @param string $path
     * @param bool $fullPath
     * @return array
     */
    public static function path(string $path, bool $fullPath = false)
    {
        if (!is_dir($path)) return [];
        $array = array();
        $dir = new \DirectoryIterator($path);
        foreach ($dir as $f) {
            $name = $f->getFilename();
            if ($name === '.' or $name === '..') continue;
            if ($f->isDir()) {
                if ($fullPath) {
                    $array[] = $f->getPathname();
                } else {
                    $array[] = $name;
                }
            }
        }
        return $array;
    }

    /**
     * 文件
     * @param string $path
     * @param string $ext
     * @return array
     */
    public static function file(string $path, string $ext = '')
    {
        if (!is_dir($path)) return [];
        $array = array();
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