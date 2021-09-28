<?php
declare(strict_types=1);

namespace esp\core;

use function esp\helper\mk_dir;
use function esp\helper\root;

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
    private $cache_path;

    public function __construct(Dispatcher $dispatcher, array &$option)
    {
        $this->request = &$dispatcher->_request;
        $this->response = &$dispatcher->_response;
        $option += ['medium' => 'file', 'cache_path' => _RUNTIME . '/cache'];
        $this->_option = &$option;

        if ($this->_option['medium'] === 'file') {
            $this->cache_path = root($option['cache_path']);
            if (!file_exists($this->cache_path)) mk_dir($this->cache_path . '/');
        } else if ($this->_option['medium'] === 'redis') $this->redis = &$dispatcher->_config->_Redis;

    }

    /**
     * 禁止保存
     */
    public function disable()
    {
        $this->_option['run'] = false;
    }

    /**
     * 读取并显示   仅由Dispatcher.run()调用
     * @return bool
     */
    public function Display()
    {
        if (_CLI or !($this->_option['run'] ?? 0) or ($this->_option['ttl'] ?? 0) < 1) goto no_cache;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) goto no_cache;

        //不显示缓存
        if (isset($_GET['_CACHE_DISABLE']) or isset($_GET['_cache_disable'])) goto no_cache;

        //_cache_set是路由的设置，有可能是T/F，或需要组成KEY的数组
        $_cache_set = $this->request->get('_cache_set');
        if (is_null($_cache_set)) $_cache_set = true;
        if (!$_cache_set) goto no_cache;

        //生成key
        $key = $this->build_cache_key($_cache_set);
        if (!$key) goto no_cache;
        if (is_int($_cache_set)) $this->_option['ttl'] = $_cache_set;

        //读取
        $this->request->set('_cache_key', $key);
        if (isset($_GET['_cache_refresh'])) goto no_cache;

        $array = $this->cache_read($key);
        if (!$array) goto no_cache;

        header("Content-type: {$array['type']}; charset=UTF-8", true);
        $this->setHeader("ttl=" . ($array['expire'] - time()));
        echo($array['html']);
        return true;

        no_cache:
        $this->disable_header('disable');
        return false;
    }

    private function cache_read(string $key)
    {
        if ($this->_option['medium'] === 'file') {
            if (!is_readable("{$this->cache_path}/{$key}.php")) return null;
            $json = include("{$this->cache_path}/{$key}.php");
            if (!$json) return null;
            return $json;
        } else {
            return $this->redis->get($key);
        }
    }

    private function cache_save(string $key, array $array, int $ttl)
    {
        if ($this->_option['medium'] === 'file') {
            $crtTime = date('Y-m-d H:i:s');
            $php = "<?php
if({$array['expire']} < time()) return null;
return array(
    'create' => '{$crtTime}',
    'expire' => '{$array['expire']}',
    'ttl' => '{$this->_option['ttl']}',
    'type' => '{$array['type']}',
    'html' => '{$array['html']}'
);";
            return file_put_contents("{$this->cache_path}/{$key}.php", $php);
        } else {
            return $this->redis->set($key, $array, $ttl);
        }
    }

    /**
     * 删除
     * @param $key
     * @return bool|int
     */
    public function Delete($key)
    {
        if ($this->_option['medium'] === 'file') {
            return unlink("{$this->cache_path}/{$key}");
        } else {
            return $this->redis->del($key);
        }
    }

    public function Save(string $value)
    {
        if (!($this->_option['run'] ?? 0) or ($this->_option['ttl'] ?? 0) < 1) return;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) return;
//        if (isset($_GET['_CACHE_DISABLE']) or isset($_GET['_cache_disable'])) goto no_cache;

        $compress = intval($this->_option['compress'] ?? 0);

        //连续两个以上空格变成一个
        if ($compress & 2) $value = preg_replace(['/\x20{2,}/'], ' ', $value);

        //删除:所有HTML注释
        if ($compress & 4) $value = preg_replace(['/\<\!--.*?--\>/'], '', $value);

        //删除:HTML之间的空格
        if ($compress & 8) $value = preg_replace(['/\>([\s\x20])+\</'], '><', $value);

        //全部HTML归为一行
        if ($compress & 1) $value = preg_replace(['/\s\/\/.+/', '/[\n\t\r]/s'], '', $value);

        if ($this->htmlSave($value)) return;

        //这里的`_cache_key`是前面Display()生成的
        $key = $this->request->get('_cache_key');
        if (!$key) return;

        $array = [];
        $array['html'] = $value;
        $array['type'] = $this->response->_Content_Type;
        $array['expire'] = (time() + $this->_option['ttl']);

        $this->cache_save($key, $array, $this->_option['ttl']);
    }

    /**
     * 保存静态HTML
     *
     * @param string $html
     * @return bool
     */
    private function htmlSave(string $html)
    {
        if ($this->request->get('_disable_static')) return false;
        $pattern = $this->_option['static'] ?? null;
        if (empty($pattern) or !$pattern) return false;
        $filename = null;
        foreach ($pattern as &$ptn) {
            if (preg_match($ptn, _URI)) {
                $filename = getenv('REQUEST_URI');
                break;
            }
        }
        if (is_null($filename)) return false;

        $path = rtrim($this->_option['static_path'] ?? dirname(getenv('SCRIPT_FILENAME')), '/');
        mk_dir($path . $filename, 0740);
        $tag = 'cache saved ' . date('Y-m-d H:i:s');
        str_replace('</html>', "<!-- {$tag} -->\n</html>", $html);
        $save = file_put_contents($path . $filename, $html, LOCK_EX);

        if ($save !== strlen($html)) {
            @unlink($filename);
            return false;
        }

        return true;
    }

    /**
     * 创建用于缓存的key
     * @param $_cache_set
     * @return string
     */
    private function build_cache_key($_cache_set)
    {
        $bud = array();
        if (!empty($_GET)) {
            $param = $this->_option['param'] ?? [];
            if (!is_array($param)) $param = array();
            if (!is_array($_cache_set)) $_cache_set = array();
            //合并需要请求的值，并反转数组，最后获取与$_GET的交集
            $bud = array_intersect_key($_GET, array_flip(array_merge($param, $_cache_set)));
        }

        //路由结果
        $params = $this->request->getControllerKey();
        return md5($params . json_encode($bud));
    }


    /**
     * 设置缓存的HTTP头
     * @param string|null $label
     */
    private function setHeader(string $label = null)
    {
        if (headers_sent()) return;
        $expires = $this->_option['ttl'];
        $time = time();

        //判断浏览器缓存是否过期
        if (getenv('HTTP_IF_MODIFIED_SINCE') && (strtotime(getenv('HTTP_IF_MODIFIED_SINCE')) + $expires) > $time) {
            $protocol = getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1';
            header("{$protocol} 304 Not Modified", true, 304);
        } else {
            header("Cache-Control: max-age={$expires}, public");
            header('Expires: ' . gmdate('D, d M Y H:i:s', $time + $expires) . ' GMT');
            header('Pragma: public');
            if ($label) header("CacheLabel: {$label}");
        }
    }

    /**
     * 禁止向浏览器缓存
     * @param null $label
     */
    private function disable_header($label = null)
    {
        if (headers_sent()) return;
        header('Cache-Control: no-cache, must-revalidate, no-store', true);
        header('Pragma: no-cache', true);
        header('Cache-Info: no cache', true);
        header(':esp Cache: 无缓存', true);
        if ($label) header('CacheLabel: ' . $label);
    }


}

