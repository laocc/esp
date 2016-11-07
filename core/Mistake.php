<?php
namespace wbf\core;

final class Mistake
{
    public static function init()
    {
        $handler_yaf = function ($errNo, $errStr, $errFile, $errLine) {
            $err = [];
            if (in_array($errNo, [256, 512, 1024])) {
                $err = json_decode($errStr, 256);
            } else {
                $err['message'] = $errStr;
                $err['code'] = $errNo;
                $err['file'] = $errFile;
                $err['line'] = $errLine;
            }
            if (!$err) return;
            ksort($err);
            self::displayError('warn', $err);
        };

        $handler_error = function ($err) {
            if ($err instanceof \Error) $a = null;
            $arr = [];
            $arr['message'] = $err->getMessage();
            $arr['code'] = $err->getCode();
            $arr['file'] = $err->getFile();
            $arr['line'] = $err->getLine();
            ksort($arr);
            self::displayError('error', $arr);
        };

        /**
         * 处理类型：
         * 2，PHP原生错误比如：除以0，语法错误等；
         * 3，程序中error()抛出的错误；
         * 4，找不到控制器，找不到控制动作等；
         */
        set_error_handler($handler_yaf);

        /**
         * 注册【异常】处理方法，
         * 处理类型：
         * 1，调用了不存在的函数；
         * 2，函数参数不对；
         * 3，throw new \Exception抛出的异常
         */
        set_exception_handler($handler_error);
    }

    /**
     *
     * 产生一个错误信息，具体处理，由\plugins\Mistake处理
     * @param $str
     * @param int $level 错误级别，012，
     *
     * 0：系统停止执行，严重级别
     * 1：提示错误，继续运行
     * 2：警告级别，在生产环境中不提示，仅发给管理员
     *
     * error("{$filePath} 不是有效文件。");
     */
    public static function try_error($str, $level = 0, $trace = null)
    {
        if ($level < 0) $level = 0;
        if ($level > 2) $level = 2;
        $level = 256 << $level;
        if (is_string($str)) {
            $err = $trace ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            unset($err['function']);
            $err['message'] = $str;
            $err['code'] = $level;
            $str = json_encode($err, 256);
        }
        //产生一个用户级别的 error/warning/notice 信息
        trigger_error($str, $level);
    }

    private static function displayError($type, $err)
    {
        $str = "故障：{$err['message']};\n文件：{$err['file']};\n行：{$err['line']}";

        if (_CLI) {
            echo "\n\e[40;31;m================ERROR=====================\e[0m\n";
//            unset($err['route']);
            print_r($err);
            exit;
        }
        $echo = function ($err) use ($type) {
            if (is_array($err) or is_object($err)) $err = '<pre>' . print_r($err, true) . '</pre>';
            echo "<div style='clear:both;display:block;line-height:2em;border:2px solid red;padding:2em;background:#ffdfdf;'>";
            echo $type, $err, "</div>";
        };
        exit($echo($err));
    }


}