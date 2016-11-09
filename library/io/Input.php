<?php
namespace io;

/**
 * Class Input
 * @package io
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
    public static function get(string $param = null, $autoValue = null)
    {
        return self::requestParams($param, $autoValue, $_GET, 'get');
    }

    public static function post(string $param = null, $autoValue = null)
    {
        return self::requestParams($param, $autoValue, $_POST, 'post');
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


    //parse_str


    /**
     * 检查来路域名与当前域名是否相同
     */
    public static function check($host = null)
    {
        if (!_REFERER) return false;
        $ref = explode('/', _REFERER);
        return !!$host ? ($ref[2] === $host) : ($ref[2] === _DOMAIN or $ref[2] === _HOST);
    }


    /**
     * 读取并简单整理要获取的变量值
     * @param null $param
     * @param bool|FALSE $xss_clean
     * @param null $autoValue
     * @param array $dataValue
     * @return bool|null|string
     *
     * 当$xss_clean不是布尔型时，则为默认值，所以默认值若需要为布尔型，则需不可采用省略方式赋值
     */
    private static function requestParams(string $param = null, $autoValue = null, &$dataValue = [], $from)
    {
        if ($param === null) {
            $chk = self::check_data($dataValue, $from);
            if ($chk !== true) return [];
            return self::_XSS_CLEAN ? Xss::clear($dataValue) : $dataValue;
        } else {
            $value = isset($dataValue[$param]) ? $dataValue[$param] : $autoValue;
            if (is_int($autoValue)) return intval($value);
            if (is_bool($autoValue)) return !!($value);

            //autoValue是一个正则表达式，常用的如：/^\w+$/
            if (preg_match('/^\/(?:\^?)(.+)\/([imUuAsDSXxJ]{0,3})(?:\$?)$/', $autoValue, $matches)) {
                if (preg_match("/^{$matches[1]}$/{$matches[2]}", $value)) {
                    return $value;
                } else {
                    return null;
                }
            }

            return self::_XSS_CLEAN ? Xss::clear($value) : $value;
        }
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
     * 检查数据
     * @param array $value
     * @return bool
     * 键名只许\w+
     * 键值不允许是数组，也不允许含有引号
     */
    private static function check_data(array &$value, $from)
    {
        $safe = [
            //GET的值不能含有下列符号
            'get' => "/[\'|\"|\`|\(|\)]/i",
        ];

        foreach ($value as $k => &$v) {
            if (is_array($v)) return 'array in';//GET的值不能是数组
            if (!preg_match('/^\w+$/i', $k)) return 'key fail';//键名只能是\w+
            foreach ($safe as $d => &$p) {
                if ($from === $d and preg_match($p, $v)) return "value fail";
            }
        }
        return true;
    }


}