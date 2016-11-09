<?php
namespace esp\core;

use \esp\library\db\Memcache;
use \esp\library\db\Redis;

/**
 * 页面HTML缓存
 * Class Cache
 */
final class Cache
{
    private $_block = '/#CONTENT_TYPE#/';
    private $_token = 'esp';

    public function __construct(Request &$request, Response &$response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function cacheSave()
    {
        if ($this->htmlSave()) return;
        if (_CLI or $this->cache_expires() < 1) return;
        if (defined('_CACHE_DISABLE') and !!_CACHE_DISABLE) return;
        if (!$key = $this->request->get('_cache_key')) return;
        if (!$value = $this->response->render()) return;

        $type = $this->response->type();
        $zip = 0;

        //连续两个以上空格变成一个
//        $value = preg_replace(['/\x20{2,}/'], ' ', $value);

        //删除:所有HTML注释
        $value = preg_replace(['/\<\!--.*?--\>/'], '', $value);

        //删除:HTML之间的空格
        $value = preg_replace(['/\>([\s\x20])+\</'], '><', $value);

        //全部HTML归为一行
        if ($zip) $value = preg_replace(['/[\n\t\r]/s'], '', $value);

        if ($this->cache_medium()->set($key, "{$value}{$this->_block}{$type}", $this->cache_expires())) {
            $this->cache_header('by save');
        }
    }

    private function htmlSave()
    {
        if ($this->request->get('_disable_static')) return false;
        $pattern = Config::get('cache.static');
        if (empty($pattern) or !$pattern) return false;
        $filename = null;
        foreach ($pattern as &$ptn) {
            if (preg_match($ptn, $this->request->uri)) {
                $filename = dirname(server('SCRIPT_FILENAME')) . server('REQUEST_URI');
                break;
            }
        }
        if (is_null($filename)) return false;
        $html = $this->response->render();
        $save = save_file($filename, $html);
        if ($save !== strlen($html)) {
            @unlink($filename);
            return false;
        }
        return true;
    }

    public function cacheDisplay()
    {
        if (_CLI or $this->cache_expires() < 1) goto no_cache;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) goto no_cache;

        //_cache_set是路由的设置，有可能是T/F，或需要组成KEY的数组
        $_cache_set = $this->request->get('_cache_set');
        if (is_bool($_cache_set) and !$_cache_set) goto no_cache;

        //生成key
        $this->build_cache_key($key, $_cache_set);
        if (!$key) goto no_cache;

        //读取
        $this->request->set('_cache_key', $key);
        $cache = $this->cache_medium()->get($key);
        if (!$cache) goto no_cache;

        $cache = explode($this->_block, $cache);
        if (!!$cache[0]) {
            if (isset($cache[1]) and $cache[1]) header('Content-type:' . $cache[1], true);
            $this->cache_header('by display');
            exit($cache[0]);
        }
        return;

        no_cache:
        $this->cache_disable_header('disable');
    }

    public function cacheDelete($key)
    {
        return $this->cache_medium()->del($key);
    }

    /**
     * 创建用于缓存的key
     */
    private function build_cache_key(&$key, $_cache_set)
    {
        $bud = [];
        //共公key
        if (!empty($_GET)) {
            $param = Config::get('cache.param');
            if (!is_array($param)) $param = [];
            if (!is_array($_cache_set)) $_cache_set = [];
            //合并需要请求的值，并反转数组，最后获取与$_GET的交集
            $bud = array_intersect_key($_GET, array_flip(array_merge($param, $_cache_set)));
        }
        //路由结果
        $params = $this->request->getParams();
        $key = (_MODULE . md5(json_encode($params) . json_encode($bud) . $this->_token));
    }

    private function cache_expires()
    {
        static $ttl;
        if (!is_null($ttl)) return $ttl;
        return $ttl = intval(Config::get('cache.expires'));
    }

    /**
     * 选择存储介质
     * @return Memcache|Redis
     */
    private function cache_medium()
    {
        $driver = Config::get('cache.driver');
        if ($driver === 'redis') {
            return new Redis(Config::get($driver));
        } else {
            return new Memcache(Config::get($driver));
        }
    }


    /**
     * 设置缓存的HTTP头
     */
    private function cache_header($label = null)
    {
        $NOW = time();//编辑时间
        $expires = $this->cache_expires();

        //判断浏览器缓存是否过期
        if (server('HTTP_IF_MODIFIED_SINCE') && (strtotime(server('HTTP_IF_MODIFIED_SINCE')) + $expires) > $NOW) {
            $protocol = server('SERVER_PROTOCOL', 'HTTP/1.1');
            header("{$protocol} 304 Not Modified", true, 304);
        } else {
            $Expires = time() + $expires;//过期时间
            $maxAge = $Expires - server('REQUEST_TIME', 0);//生命期
            header('Cache-Control: max-age=' . $maxAge . ', public');
            header('Expires: ' . gmdate('D, d M Y H:i:s', $Expires) . ' GMT');
            header('Pragma: public');
            if ($label) header('CacheLabel: ' . $label);
        }
    }

    /**
     * 禁止向浏览器缓存
     */
    private function cache_disable_header($label = null)
    {
        header('Cache-Control: no-cache, must-revalidate, no-store', true);
        header('Pragma: no-cache', true);
        header('Cache-Info: no cache', true);
        if ($label) header('CacheLabel: ' . $label);
    }


}

