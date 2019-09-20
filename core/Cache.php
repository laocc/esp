<?php

namespace esp\core;

/**
 * 页面HTML缓存
 * Class Cache
 */
final class Cache
{
    private $_option;
    private $request;
    private $response;
    private $redis;

    public function __construct(Dispatcher $dispatcher, array &$option)
    {
        $this->request = &$dispatcher->_request;
        $this->response = &$dispatcher->_response;
        $this->redis = Config::Redis();
        $this->_option = &$option;
    }

    /**
     * 禁止保存
     */
    public function disable()
    {
        $this->_option['run'] = false;
    }

    /**
     * 读取并显示     * 仅由Dispatcher.run()调用
     * @return bool
     */
    public function Display()
    {
        if (_CLI or !$this->_option['run'] or $this->_option['ttl'] < 1) goto no_cache;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) goto no_cache;

        //_cache_set是路由的设置，有可能是T/F，或需要组成KEY的数组
        $_cache_set = $this->request->get('_cache_set');
        if (!$_cache_set) goto no_cache;
        //生成key
        $key = $this->build_cache_key($_cache_set);
        if (!$key) goto no_cache;
        if (is_int($_cache_set)) $this->_option['ttl'] = $_cache_set;

        //读取
        $this->request->set('_cache_key', $key);
        $array = $this->redis->get($key);
        if (!$array) goto no_cache;

        header("Content-type: {$array['type']}", true);
        $this->setHeader('ttl=' . $this->redis->ttl($key));
        echo($array['html']);
        return true;

        no_cache:
        $this->disable_header('disable');
        return false;
    }

    /**
     * 保存
     * 仅由Dispatcher.run()调用
     */
    public function Save()
    {
        if (_CLI or !$this->_option['run'] or $this->_option['ttl'] < 1) return;
//        if ($this->htmlSave()) return;
        if (defined('_CACHE_DISABLE') and !!_CACHE_DISABLE) return;

        //这里的_cache_key是前面Display()生成的
        $key = $this->request->get('_cache_key');
        if (!$key) return;
        $value = $this->response->render();

        if (!$value) return;
        //连续两个以上空格变成一个
        if ($this->_option['space'] ?? 0) $value = preg_replace(['/\x20{2,}/'], ' ', $value);

        //删除:所有HTML注释
        if ($this->_option['notes'] ?? 0) {
            $value = preg_replace(['/\<\!--.*?--\>/'], '', $value);
        }

        //删除:HTML之间的空格
        if ($this->_option['tags'] ?? 0) $value = preg_replace(['/\>([\s\x20])+\</'], '><', $value);

        //全部HTML归为一行
        if ($this->_option['zip'] ?? 0) {
            $value = preg_replace(['/\s\/\/.+/', '/[\n\t\r]/s'], '', $value);
        }

//        $value .= print_r($this->_option, true);
        $array = [];
        $array['html'] = $value;
        $array['type'] = $this->response->getType();
        $array['expire'] = (time() + $this->_option['ttl']);
        $this->redis->set($key, $array, $this->_option['ttl']);
    }

    /**
     * 删除
     * @param $key
     * @return bool|int
     */
    public function Delete($key)
    {
        return $this->redis->del($key);
    }

    /**
     * 保存静态HTML
     * @return bool
     * @throws \Exception
     */
    private function htmlSave()
    {
        if ($this->request->get('_disable_static')) return false;
        $pattern = $this->_option['static'];
        if (empty($pattern) or !$pattern) return false;
        $filename = null;
        foreach ($pattern as &$ptn) {
            if (preg_match($ptn, $this->request->uri)) {
                $filename = dirname(getenv('SCRIPT_FILENAME')) . getenv('REQUEST_URI');
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

    /**
     * 创建用于缓存的key
     */
    private function build_cache_key($_cache_set)
    {
        $bud = Array();
        //共公key
        if (!empty($_GET)) {
            $param = $this->_option['param'] ?? [];
            if (!is_array($param)) $param = Array();
            if (!is_array($_cache_set)) $_cache_set = Array();
            //合并需要请求的值，并反转数组，最后获取与$_GET的交集
            $bud = array_intersect_key($_GET, array_flip(array_merge($param, $_cache_set)));
        }

        //路由结果
        $params = $this->request->getControllerKey();
        return md5($params . json_encode($bud));
    }


    /**
     * 设置缓存的HTTP头
     */
    private function setHeader(string $label = null)
    {
        if (headers_sent()) return;
        $NOW = time();//编辑时间
        $expires = $this->_option['ttl'];

        //判断浏览器缓存是否过期
        if (getenv('HTTP_IF_MODIFIED_SINCE') && (strtotime(getenv('HTTP_IF_MODIFIED_SINCE')) + $expires) > $NOW) {
            $protocol = getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1';
            header("{$protocol} 304 Not Modified", true, 304);
        } else {
//            $Expires = time() + $expires;//过期时间
//            $maxAge = $Expires - (getenv('REQUEST_TIME') ?: 0);//生命期
            header("Cache-Control: max-age={$expires}, public");
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
            header('Pragma: public');
            if ($label) header("CacheLabel: {$label}");
        }
    }

    /**
     * 禁止向浏览器缓存
     */
    private function disable_header($label = null)
    {
        if (headers_sent()) return;
        header('Cache-Control: no-cache, must-revalidate, no-store', true);
        header('Pragma: no-cache', true);
        header('Cache-Info: no cache', true);
        if ($label) header('CacheLabel: ' . $label);
    }


}

