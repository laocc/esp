<?php
namespace esp\extend;
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

    /**
     * 读取并简单整理要获取的变量值
     * @param array $data
     * @param $param
     * @param null $autoValue
     * @return array|bool|float|int|mixed|null
     */
    private static function requestParams(array $data, $params, $autoValue = null)
    {
        $value = [];
        $index = 0;
        if (!is_array($params)) $params = [$params => $autoValue];
        foreach ($params as $param => $autoValue) {
            if (is_int($param) and is_string($autoValue)) list($param, $autoValue) = [$autoValue, null];
            if (!isset($data[$param])) $value[$index] = $autoValue;
            elseif (is_int($autoValue)) $value[$index] = intval($data[$param]);
            elseif (is_float($autoValue)) $value[$index] = floatval($data[$param]);
            elseif (is_bool($autoValue)) $value[$index] = boolval($data[$param]);
            elseif (is_array($autoValue)) $value[$index] = json_decode($data[$param], true);
            else {
                $value[$index] = $data[$param];
                //autoValue是一个正则表达式，常用的如：/^\w+$/
                if ($autoValue and preg_match('/^\/\^?.+\$?\/[imUuAsDSXxJ]{0,3}$/', $autoValue)) {
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
    public static function php()
    {
        $val = file_get_contents("php://input");
        parse_str($val, $arr);
        return $arr;
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


}