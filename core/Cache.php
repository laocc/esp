<?php

namespace esp\core;

/**
 * 页面HTML缓存
 * Class Cache
 */
final class Cache
{
    private static $_option;

    public static function _init(array &$option)
    {
        self::$_option = &$option;
    }

    /**
     * 禁止保存
     */
    public function disable()
    {
        self::$_option['run'] = false;
    }

    /**
     * 保存静态HTML
     * @return bool
     */
    private static function htmlSave(): bool
    {
        if (Request::get('_disable_static')) return false;
        $pattern = self::$_option['static'];
        if (empty($pattern) or !$pattern) return false;
        $filename = null;
        foreach ($pattern as &$ptn) {
            if (preg_match($ptn, Request::getUri())) {
                $filename = dirname(getenv('SCRIPT_FILENAME')) . getenv('REQUEST_URI');
                break;
            }
        }
        if (is_null($filename)) return false;
        return save_file($filename, Response::getResult()) > 0;
    }

    /**
     * 保存
     * 仅由Dispatcher.run()调用
     */
    public static function save()
    {
        if (_CLI or !self::$_option['run'] or self::$_option['ttl'] < 1) return;
        if (self::htmlSave()) return;
        if (defined('_CACHE_DISABLE') and !!_CACHE_DISABLE) return;
        if (!$key = self::build_cache_key()) return;
        if (!$value = Response::getResult()) return;

        //连续两个以上空格变成一个
        if (self::$_option['space'] ?? 0) $value = preg_replace(['/\x20{2,}/'], ' ', $value);

        //删除:所有HTML注释
        if (self::$_option['notes'] ?? 0) $value = preg_replace(['/\<\!--.*?--\>/'], '', $value);

        //删除:HTML之间的空格
        if (self::$_option['tags'] ?? 0) $value = preg_replace(['/\>([\s\x20])+\</'], '><', $value);

        //全部HTML归为一行
        if (self::$_option['zip'] ?? 0) $value = preg_replace(['/[\n\t\r]/s'], '', $value);

        $array = [];
        $array['html'] = $value;
        $array['type'] = Response::getType();
        $array['expire'] = (_TIME + self::$_option['ttl']);

        if (Buffer::set($key, $array, self::$_option['ttl'])) {
            self::setHeader('by save');
        }
    }

    /**
     * 读取并显示     * 仅由Dispatcher.run()调用
     * @return bool
     */
    public static function Display()
    {
        if (_CLI or !self::$_option['run'] or self::$_option['ttl'] < 1) goto no_cache;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) goto no_cache;

        //生成key
        $key = self::build_cache_key();
        if (!$key) goto no_cache;

        //读取
        $response = Buffer::get($key);
        if (!$response) goto no_cache;

        header('Content-type:' . $response['type'], true);
        self::setHeader('by display');
        echo($response['html']);
        return true;

        no_cache:
        self::disable_header('disable');
        return false;
    }

    /**
     * 删除
     * @param $key
     * @return bool|int
     */
    public function Delete($key)
    {
        return Buffer::del($key);
    }

    /**
     * 创建用于缓存的key
     */
    private static function build_cache_key($_cache_set = null)
    {
        $bud = Array();
        if (!empty($_GET)) {
            $param = self::$_option['param'] ?? [];
            if (!is_array($param)) $param = Array();
            if (!is_array($_cache_set)) $_cache_set = Array();
            //合并需要请求的值，并反转数组，最后获取与$_GET的交集
            $bud = array_intersect_key($_GET, array_flip(array_merge($param, $_cache_set)));
        }
        //路由结果
        return (_MODULE . md5(Request::getActionPath() . json_encode(Request::getParams()) . json_encode($bud) . _ROOT));
    }


    /**
     * 设置缓存的HTTP头
     */
    private static function setHeader(string $label = null)
    {
        if (headers_sent()) return;
        $expires = self::$_option['ttl'];

        //判断浏览器缓存是否过期
        if (getenv('HTTP_IF_MODIFIED_SINCE') && (strtotime(getenv('HTTP_IF_MODIFIED_SINCE')) + $expires) > _TIME) {
            $protocol = getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1';
            header("{$protocol} 304 Not Modified", true, 304);
        } else {
            $Expires = _TIME + $expires;//过期时间
            $maxAge = $Expires - (getenv('REQUEST_TIME') ?: 0);//生命期
            header('Cache-Control: max-age=' . $maxAge . ', public');
            header('Expires: ' . gmdate('D, d M Y H:i:s', $Expires) . ' GMT');
            header('Pragma: public');
            if ($label) header('CacheLabel: ' . $label);
        }
    }

    /**
     * 禁止向浏览器缓存
     */
    private static function disable_header($label = null)
    {
        if (headers_sent()) return;
        header('Cache-Control: no-cache, must-revalidate, no-store', true);
        header('Pragma: no-cache', true);
        header('Cache-Info: no cache', true);
        if ($label) header('CacheLabel: ' . $label);
    }


}

