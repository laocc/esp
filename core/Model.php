<?php
namespace esp\core;

/**
 * Class Model
 * @package esp\core
 *
 * func_get_args()
 */
class Model
{

    /**
     * 加载模型
     * 关于参数：在实际模型类中，建议用func_get_args()获取参数列表，也可以直接指定参数
     * @param $model
     * @param null $params
     * @return mixed
     *
     */
    public static function &create(...$paras)
    {
        if (empty($paras)) return null;
        static $_Recode = [];
        $mod = $paras[0];
        $modKey = md5($mod);
        if (isset($_Recode[$modKey])) return $_Recode[$modKey];
        $from = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        if (is_file($mod)) {
            load($mod);
            $class = _MODULE . '\\' . ucfirst(strtolower(basename($mod, '.php'))) . Config::get('esp.modelExt');
            if (!class_exists($class)) error("模型 [{$class}] 不存在", $from);
        } else {
            if (!preg_match('/^\w+$/', $mod)) error('模型名只可以是字母数字组合', $from);

            $dir = dirname(dirname($from['file'])) . '/models/';
            $model = ucfirst(strtolower($mod));
            $class = _MODULE . '\\' . $model . Config::get('esp.modelExt');
            load("{$dir}{$model}.php");
            if (!class_exists($class)) error("模型 [{$class}] 不存在", $from);
        }
        array_shift($paras);
        $_Recode[$modKey] = new $class(...$paras);
        if (!($_Recode[$modKey] instanceof Model)) {
            exit("模型 [{$class}] 须继承自 [esp\\core\\Model]");
        }
        return $_Recode[$modKey];
    }


}