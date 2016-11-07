<?php
namespace wbf\core;

/**
 *
 * 此处读取/config/config.php中的设置
 *
 * Class Config
 * @package wbf\core
 */
class Config
{
    const _DIRECTORY = 'application';   //网站主程序所在路径，不含模块名
    const _CONTROL = 'Controller';      //控制器名后缀，注意：文件名不含这部分
    const _ACTION = 'Action';           //动作名后缀
    const _VIEW_EXT = 'phtml';          //视图文件后缀
    const _DEFAULT_MODULE = 'www';      //默认模块
    const _DEFAULT_CONTROL = 'index';   //默认控制器
    const _DEFAULT_ACTION = 'index';    //默认动作
    const _LAYOUT = 'layout.phtml';     //框架视图文件名

    static private $_conf = [];

    public static function load()
    {
        if (!empty(self::$_conf)) return;

        $file = ['config.php'];
        foreach ($file as $fil) {
            $_conf = self::load_file(root("config/{$fil}"));
            if (is_array($_conf) && !empty($_conf)) {
                self::$_conf = array_merge(self::$_conf, $_conf);
            }
        }
    }

    /**
     * 读取config，可以用get('key1.key2')的方式读取多维数组值
     * @param null $key
     * @param null $auto
     * @return array|mixed|null
     */
    public static function get($key = null, $auto = null)
    {
        if (is_null($key)) return self::$_conf;
        $key = preg_replace('/[\.\,\_\/\\\]+/', '.', $key);
        if (strrpos($key, '.')) {
            $keys = explode('.', trim($key, '.'));
            $conf = self::$_conf;
            foreach ($keys as $k) {
                $conf = isset($conf[$k]) ? $conf[$k] : null;
                if (is_null($conf)) return $auto;
            }
            return $conf;
        }
        return isset(self::$_conf[$key]) ? self::$_conf[$key] : $auto;
    }

    public static function has($key)
    {
        return self::get($key, "__Test_Config_{$key}__") !== "__Test_Config_{$key}__";
    }

    public static function set($key, $value)
    {
        self::$_conf[$key] = $value;
    }

    private static function load_file($file)
    {
        if (!$file) return false;
        return @include root($file);
    }

    public static function states($code)
    {
        switch ($code) {
            case 200:
                return 'OK';
            case 201:
                return 'Created';
            case 202:
                return 'Accepted';
            case 203:
                return 'Non-Authoritative Information';
            case 204:
                return 'Not Content';
            case 205:
                return 'Reset Content';
            case 206:
                return 'Partial Content';
            case 300:
                return 'Multiple Choices';
            case 301:
                return 'Moved Permanently';
            case 302:
                return 'Found';
            case 303:
                return 'See Other';
            case 304:
                return 'Not Modified';
            case 305:
                return 'Use Proxy';
            case 307:
                return 'Temporary Redirect';
            case 400:
                return 'Bad Request';
            case 401:
                return 'Unauthorized';
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not Found';
            case 405:
                return 'Method Not Allowed';
            case 406:
                return 'Not Acceptable';
            case 407:
                return 'Proxy Authentication Required';
            case 408:
                return 'Request Timeout';
            case 409:
                return 'Conflict';
            case 410:
                return 'Gone';
            case 411:
                return 'Length Required';
            case 412:
                return 'Precondition Failed';
            case 413:
                return 'Request Entity Too Large';
            case 414:
                return 'Request-URI Too Long';
            case 415:
                return 'Unsupported Media Type';
            case 416:
                return 'Requested Range Not Satisfiable';
            case 417:
                return 'Expectation Failed';
            case 422:
                return 'Unprocessable Entity';
            case 500:
                return 'Internal Server Error';
            case 501:
                return 'Not Implemented';
            case 502:
                return 'Bad Gateway';
            case 503:
                return 'Service Unavailable';
            case 504:
                return 'Gateway Timeout';
            case 505:
                return 'HTTP Version Not Supported';
            default:
                return null;
        }
    }


}