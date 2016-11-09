<?php
namespace esp\core;

use \Yaf\Registry;
use \Yaf\Request_Abstract;

/**
 * 页面HTML缓存
 * Class Cache
 */
trait Cache
{
    private $_ttl;
    private $_block = '/#CONTENT_TYPE#/';
    private $_token = 'blog';

    public function cache_set(&$key, &$value, &$type)
    {
        if (_CLI or !$key or !$value or $this->cache_ttl() < 1) return;
        if (defined('_CACHE_DISABLE') and !!_CACHE_DISABLE) return;
        $zip = 0;

        //连续两个以上空格变成一个
//        $value = preg_replace(['/\x20{2,}/'], ' ', $value);

        //删除:所有HTML注释
        $value = preg_replace(['/\<\!--.*?--\>/'], '', $value);

        //删除:HTML之间的空格
        $value = preg_replace(['/\>([\s\x20])+\</'], '><', $value);

        //全部HTML归为一行
        if ($zip) $value = preg_replace(['/[\n\t\r]/s'], '', $value);

        if ($this->cache_medium()->set($key, "{$value}{$this->_block}{$type}", $this->cache_ttl())) {
            $this->cache_header();
        }
    }

    public function cache_get(Request_Abstract $request, &$key)
    {
        if (_CLI or $this->cache_ttl() < 1) goto no_cache;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) goto no_cache;

        //路由设置
        $_cache_set = $request->getParam('_cache_set');
        if (is_bool($_cache_set) and !$_cache_set) goto no_cache;

        //生成key
        $this->build_cache_key($request, $key, $_cache_set);
        if (!$key) goto no_cache;

        //读取
        $cache = $this->cache_medium()->get($key);
        if (!$cache) goto no_cache;

        $cache = explode($this->_block, $cache);
        if (!!$cache[0]) {
            if (isset($cache[1]) and $cache[1]) header('Content-type:' . $cache[1], true);
            $this->cache_header();
            exit($cache[0]);
        }
        return;

        no_cache:
        $this->cache_disable_header();
    }

    /**
     * 创建用于缓存的key
     */
    private function build_cache_key(Request_Abstract $request, &$key, $_cache_set)
    {
        $bud = [];

        //共公key
        if (!empty($_GET)) {
            $param = Registry::get('config')->cache->param;
            if (!is_array($param)) $param = [];
            if (!is_array($_cache_set)) $_cache_set = [];
            //合并需要请求的值，并反转数组，最后获取与$_GET的交集
            $bud = array_intersect_key($_GET, array_flip(array_merge($param, $_cache_set)));
        }

        //路由结果
        $params = $request->getParams();
        unset_request($params);

        $key = (_APP . _SITE . md5(json_encode($params) . json_encode($bud) . $this->_token));
    }


    public function cache_del($key)
    {
        return $this->cache_medium()->del($key);
    }

    private function cache_ttl()
    {
        if ($this->_ttl !== null) return $this->_ttl;
        return $this->_ttl = intval(Registry::get('config')->cache->expires);
    }

    /**
     * 选择存储介质
     * @return \db\Memcache|\db\Redis
     */
    private function cache_medium()
    {
        $set = Registry::get('config')->cache;
        $conf = $set->{$set->driver};

        if ($set->driver === 'redis') {
            return new \db\Redis($conf);
        } else {
            return new \db\Memcache($conf);
        }
    }


    /**
     * 设置缓存的HTTP头
     */
    private function cache_header()
    {
        $NOW = time();//编辑时间
        $Expires = time() + $this->cache_ttl();//过期时间
        $maxAge = $Expires - $_SERVER['REQUEST_TIME'];//生命期

        //判断浏览器缓存是否过期
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) + $this->cache_ttl()) > $NOW) {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            header("{$protocol} 304 Not Modified", true, 304);
        } else {
            header('Cache-Control: max-age=' . $maxAge . ', public');
            header('Expires: ' . gmdate('D, d M Y H:i:s', $Expires) . ' GMT');
            header('Pragma: public');
        }
    }

    /**
     * 禁止向浏览器缓存
     */
    private function cache_disable_header()
    {
        header('Cache-Control: no-cache, must-revalidate, no-store', true);
        header('Pragma: no-cache', true);
        header('Cache-Info: no cache', true);
    }


}

