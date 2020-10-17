<?php
declare(strict_types=1);

namespace esp\core\ext;

use esp\library\ext\Xss;

/**
 * Class Input
 * http://php.net/manual/zh/wrappers.php.php
 */
final class Input
{
    private $_XSS_CLEAN = true;//是否默认进行防注检查

    public function __construct()
    {
    }

    /**
     * 获取get
     * @param string $param
     * @param null $autoValue 如果有指定默认值，则返回数据也根据该值类型强制转换：int,bool,或是正则表达式
     * @return bool|null|string
     */
    public function get($param, $autoValue = null)
    {
        return $this->requestParam($_GET, $param, $autoValue);
    }

    public function post($param, $autoValue = null)
    {
        return $this->requestParam($_POST, $param, $autoValue);
    }

    public function any($param, $autoValue = null)
    {
        return $this->requestParam($_REQUEST, $param, $autoValue);
    }

    private function date_zone($autoValue)
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


    private function requestParam(array $data, string $param, $autoValue = null)
    {
        if (!isset($data[$param])) {
            if ($param === 'date_zone') return $this->date_zone($autoValue);
            if ($autoValue === 'date_time') return strtotime(date('Y-m-d'));
            return $autoValue;
        }
        $value = $data[$param];

        switch (true) {
            case $param === 'date_zone':
                $date = trim($value);
                if (!empty($date)) {
                    $date = str_replace('%3A', ':', $date);
                    $date = str_replace(['+~+', '+-+', ' - '], '~', $date);
                    $day = explode('~', $date);
                    if (!isset($day[1])) $day[1] = $day[0];
                    $day[0] = trim($day[0]);
                    $day[1] = trim($day[1]);
                    $time = [strtotime($day[0]), strtotime($day[1])];

                    if (!$time[0] or !$time[1]) {//可能是非法数据
                        $value = $this->date_zone($autoValue);
                    } else {
                        $value = [$day[0], $day[1], $time[0], $time[1], 'auto' => false];
                    }
                } else {//基本是初始页面状态
                    $value = $this->date_zone($autoValue);
                }
                if (!isset($value['auto'])) $value['auto'] = true;
                break;

            case is_string($autoValue):
                if ($autoValue === '') {
                    //\%\&\^\$\#\(\)\[\]\{\}\?
                    $value = preg_replace('/[\"\']/', '', trim($value));

                } elseif ($autoValue === 'real') {
                } elseif ($autoValue === 'html') {

                } elseif ($autoValue === 'json') {
                    if (is_string($value)) $value = json_decode($value, true);
                    $value = json_encode(array_map(function ($v) {
                        return trim($v);
                    }, $value), 256);

                } elseif ($autoValue === 'array') {
                    if (is_string($value)) $value = json_decode($value, true);
                    if (empty($value)) $value = [];

                } else if ($autoValue === 'date_time') {
                    $date = trim($value);
                    if (!!$date) {
                        $date = str_replace('+', ' ', $date);
                        $date = str_replace('%3A', ':', $date);
                        $value = strtotime($date);
                    } else {
                        $value = 0;
                    }

                } else if (\esp\helper\is_match($autoValue)) {
                    //autoValue是一个正则表达式，常用的如：/^\w+$/
                    if (!preg_match($autoValue, $value)) $value = null;

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

            default:
                if (is_array($value)) $value = json_encode($value, 256);

                if ($this->_XSS_CLEAN) Xss::clear($value);

        }
        return $value;
    }


    public function quote(string $value)
    {
        return preg_replace('/[\"\'\%\&\^\$\#\(\)\[\]\{\}\?]/', '', trim($value));
    }


    /**
     * 在POST表单中，submit用
     * <input type="image" src="<?=Url::admin()?>res/img/credit/visa.png" name="submit" />
     * 这里检查点击时鼠标位置
     * 若都为0，则可能是由JS控制提交
     * $sub为 name="submit"的值
     *
     * */
    public function isClick($sub = 'submit')
    {
        if (isset($_POST[$sub . '_x']) and isset($_POST[$sub . '_y'])) {
            return $_POST[$sub . '_x'] > 0 or $_POST[$sub . '_y'] > 0;
        }
        return true;
    }

    /**
     * 目录
     * @param string $path
     * @param string $ext 只读取指定文件类型
     * @return array
     */
    public function path(string $path, bool $fullPath = false)
    {
        if (!is_dir($path)) return [];
        $array = Array();
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
    public function file(string $path, string $ext = '')
    {
        if (!is_dir($path)) return [];
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