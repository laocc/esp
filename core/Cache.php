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
    private $cache_key;

    public function __construct(Dispatcher $dispatcher, array &$option)
    {
        $this->request = &$dispatcher->_request;
        $this->response = &$dispatcher->_response;
        $option += ['medium' => 'file', 'path' => ['cache' => _RUNTIME], 'ttl' => 0];
        $this->_option = &$option;

        if ($option['medium'] === 'file') {
            $this->cache_path = root($option['path']['cache']);
            if (!file_exists($this->cache_path)) mk_dir($this->cache_path . '/');
        } else if ($option['medium'] === 'redis') $this->redis = &$dispatcher->_config->_Redis;

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
        $r = $this->request;
        if (isset($this->_option[$r->controller][$r->action])) {
            $act = $this->_option[$r->controller][$r->action];
            if (is_bool($act) or $act === 0) $this->_option['run'] = boolval($act);
            else if (is_numeric($act)) $this->_option['ttl'] = abs(intval($act));
        }
        if (_CLI or !($this->_option['run'] ?? 0) or ($this->_option['ttl']) < 1) goto no_cache;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) goto no_cache;

        //不显示缓存
        if (isset($_GET['_CACHE_DISABLE']) or isset($_GET['_cache_disable'])) goto no_cache;

        $bud = array();
        if (!empty($_GET) and is_array($this->_option['params'] ?? null)) {
            //合并需要请求的值，并反转数组，最后获取与$_GET的交集
            $bud = array_intersect_key($_GET, array_flip($this->_option['params']));
        }
        $iso = intval($this->_option['isolation'] ?? 0);
        $keyValue = [
            'virtual' => $r->virtual,
            'module' => $r->module,
            'controller' => $r->controller,
            'action' => $r->action,
            'params' => $r->params,
            'query' => $bud,
            'host' => ['', _HOST, _DOMAIN][$iso] ?? '',
        ];
        $this->cache_key = urlencode(base64_encode(json_encode($keyValue, 320)));

        $array = $this->cache_read();
        if (!$array) goto no_cache;
        if (($array['create'] + $this->_option['ttl']) < time()) goto no_cache;

        header("Content-type: {$array['type']}; charset=UTF-8", true);
        $this->setHeader("ttl=" . ($array['expire'] - time()));
        echo($array['html']);
        return true;

        no_cache:
        $this->disable_header('disable');
        return false;
    }

    public function Save()
    {
        if (!($this->_option['run'] ?? 0) or ($this->_option['ttl']) < 1) return;
        if (defined('_CACHE_DISABLE') and _CACHE_DISABLE) return;
        if (isset($_GET['_CACHE_DISABLE']) or isset($_GET['_cache_disable'])) return;

        $value = $this->response->_display_Result;
        if (!$value) return;

        $compress = intval($this->_option['compress'] ?? 0);

        $replace = ['pnt' => [], 'to' => []];

        //连续两个以上空格变成一个
        if ($compress & 2) {
            $replace['pnt'][] = '/\x20{2,}/';
            $replace['to'][] = ' ';
        }

        //删除:所有HTML注释
        if ($compress & 4) {
            $replace['pnt'][] = '/\<\!--.*?--\>/';
            $replace['to'][] = '';
        }

        //删除:HTML之间的空格
        if ($compress & 8) {
            $replace['pnt'][] = '/\>([\s\x20])+\</';
            $replace['to'][] = '><';
        }

        //全部HTML归为一行
        if ($compress & 16) {
            $replace['pnt'][] = '/\s\/\/.+/';
            $replace['pnt'][] = '/[\n\t\r]/s';
            $replace['to'][] = '';
            $replace['to'][] = '';
        }

        //删除空行
        if ($compress & 1) {
            $replace['pnt'][] = '/\s*\n/s';
            $replace['to'][] = "\n";
        }

        if (!empty($replace['pnt'])) $value = preg_replace($replace['pnt'], $replace['to'], $value);

        $tag = date('Y-m-d H:i:s');
        $value = str_replace(['</html>', '{CACHE_KEY}', '{CACHE_TIME}'],
            ["<!-- \ncache saved `{$tag}`; by laocc/esp Cache\n-->\n</html>", $this->cache_key, time()], $value);

        //_disable_static是控制器在运行中$this->cache(false);临时设置的值
        if (!$this->request->get('_disable_static') and isset($this->_option['static'])) {
            if ($this->htmlSave($value)) return;
        }

        $array = [];
        $array['type'] = $this->response->_Content_Type;
        $array['expire'] = (time() + $this->_option['ttl']);
        $exp = date('Y-m-d H:i:s', $array['expire']);
        $array['html'] = str_replace('`; by laocc/esp Cache', "`; will expire `{$exp}`; by laocc/esp Cache", $value);

        $this->cache_save($array);
    }

    private function cache_read()
    {
        $key = md5($this->cache_key);
        if ($this->_option['medium'] === 'file') {
            if (!is_readable($pFile = "{$this->cache_path}/{$key}.php")) return null;
            $json = include $pFile;
            if (!$json) return null;
            return $json;
        } else {
            return $this->redis->get($key);
        }
    }

    private function cache_save(array $array)
    {
        $key = md5($this->cache_key);
        $array['create'] = time();
        if ($this->_option['medium'] === 'file') {
            $url = _URL;
            $php = "<?php
if ({$array['expire']} < time()) return null;
\$html = <<<HTML\n{$array['html']}\nHTML;

return array(
    'create' => {$array['create']},
    'expire' => {$array['expire']},
    'ttl' => {$this->_option['ttl']},
    'type' => '{$array['type']}',
    'url' => '{$url}',
    'html' => &\$html
);\n";
            return file_put_contents("{$this->cache_path}/{$key}.php", $php);
        } else {
            return $this->redis->set($key, $array, $this->_option['ttl']);
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

    /**
     * 保存静态HTML
     *
     * @param string $html
     * @return bool
     */
    private function htmlSave(string $html)
    {
        $hitPtn = false;
        foreach ($this->_option['static'] as $ptn) {
            if (preg_match($ptn, _URI)) {
                $hitPtn = true;
                break;
            }
        }
        if (!$hitPtn) return false;

        $path = rtrim($this->_option['path']['static'] ?? dirname(getenv('SCRIPT_FILENAME')), '/');
        mk_dir($path . _URI, 0740);
        $save = file_put_contents($path . _URI, $html, LOCK_EX);

        if ($save !== strlen($html)) {
            @unlink($path . _URI);
            return false;
        }

        return true;
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
        header(':esp Cache: no cache', true);
        if ($label) header('CacheLabel: ' . $label);
    }


}

