<?php
declare(strict_types=1);

namespace esp\core;

use Redis;
use function esp\helper\mk_dir;
use function esp\helper\root;

/**
 * 页面HTML缓存
 * Class Cache
 */
final class Cache
{
    private Request $_request;
    private Response $_response;
    private Redis $_redis;
    private array $_option;
    private string $cache_path;//缓存目录基址，由path[cache]指定，不含URI
    private string $cache_file;//当前URI对应的缓存文件全路径
    private string $cache_key;//缓存标识，参与文件名与{CACHE_KEY}替换，不直接作文件名

    public function __construct(Dispatcher $dispatcher, array &$option)
    {
        $this->_request = &$dispatcher->_request;
        $this->_response = &$dispatcher->_response;
        $option += ['medium' => 'file', 'path' => ['cache' => _RUNTIME], 'ttl' => -1];
        $this->_option = $option;

        if ($option['medium'] === 'redis') $this->_redis = &$dispatcher->_config->_Redis;
    }

    /**
     * 禁止保存
     */
    public function disable(): void
    {
        $this->_option['run'] = false;
    }

    /**
     * 读取并显示   仅由Dispatcher.run()调用
     * @return bool
     */
    public function Display(): bool
    {
        $r = $this->_request;
        if (isset($this->_option[$r->controller][$r->action])) {
            $act = $this->_option[$r->controller][$r->action];
            if (is_bool($act) or $act === 0) $this->_option['run'] = boolval($act);
            else if (is_numeric($act)) $this->_option['ttl'] = intval($act);
        }
        if (_CLI or !($this->_option['run'] ?? 0) or ($this->_option['ttl']) < 0) goto no_cache;
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

        $this->cache_path = $this->cacheDir();

        $array = $this->cache_read();
        if (!$array) goto no_cache;
        if ($this->_option['ttl'] > 0 and ($array['create'] + $this->_option['ttl']) < time()) goto no_cache;

        header("Content-type: {$array['type']}; charset=UTF-8", true);
        $this->setHeader("ttl=" . ($array['expire'] - time()));
        echo($array['html']);
        return true;

        no_cache:
        $this->disable_header('disable');
        return false;
    }

    public function Save(): void
    {
        if (!($this->_option['run'] ?? 0) or ($this->_option['ttl'] < 1) or !$this->_response->cache) return;
        if (isset($_GET['_CACHE_DISABLE']) or isset($_GET['_cache_disable'])) return;

        $value = $this->_response->_display_Result;
        if (!$value or !preg_match('#<html.+</html>#s', $value)) return;

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

        $value = str_replace(['{CACHE_KEY}', '{CACHE_TIME}'], [$this->cache_key, time()], $value);

        //_disable_static是控制器在运行中$this->cache(false);临时设置的值
        if ($this->_response->cache and isset($this->_option['static'])) {
            if ($this->htmlSave($value)) return;
        }

        if ($this->_option['ttl'] < 5) return;

        $file = $this->cacheFile();
        mk_dir($this->cache_path . '/');
        if (!is_writable(dirname($file))) return;

        $array = [];
        $array['type'] = $this->_response->_Content_Type;
        $array['expire'] = (time() + $this->_option['ttl']);
        $tag = date('Y-m-d H:i:s');
        $exp = date('Y-m-d H:i:s', $array['expire']);
        $label = "<!--\ncache saved `{$tag}`; will expire `{$exp}`; by laocc/esp Cache\n-->";
        $array['html'] = str_replace('</html>', "{$label}\n</html>", $value);
        $array['create'] = time();
        if ($this->_option['medium'] === 'file') {
            $expire = intval($array['expire']);
            $create = intval($array['create']);
            $ttl = intval($this->_option['ttl']);
            //正文与PHP源码彻底解耦：HTML整体base64后以单引号字面量写入，
            //正文中任何字符（含换行、引号、<?php）都不可能截断文件结构，
            //结束标记也不再依赖可预测的md5(时间)，
            //标签仅作可读性用，url用var_export兜底转义
            $label = base64_encode($array['html']);
            $type = base64_encode((string)$array['type']);
            $url = var_export(base64_encode((string)_URL), true);
            $php = <<<CODE
<?php
if ({$expire} < time()) return null;

return array(
    'create' => {$create},
    'expire' => {$expire},
    'ttl' => {$ttl},
    'type' => base64_decode('{$type}'),
    'url' => base64_decode({$url}),
    'html' => base64_decode('{$label}')
);\n
CODE;
            @file_put_contents($file, $php, LOCK_EX);
        } else {
            $this->_redis->set(md5($this->cache_key), $array, $this->_option['ttl']);
        }
    }

    private function cache_read()
    {
        if ($this->_option['medium'] === 'file') {
            if (!is_readable($pFile = $this->cacheFile())) return null;
            $json = include $pFile;
            if (!$json) return null;
            return $json;
        } else {
            return $this->_redis->get(md5($this->cache_key));
        }
    }

    /**
     * 缓存目录基址
     *
     * 只作目录用，不含URI，因此可直接交给mk_dir()按目录创建，
     * 不再出现"把URI当目录建"的情况
     *
     * @return string
     */
    private function cacheDir(): string
    {
        $path = $this->_option['path']['cache'] ?? _RUNTIME;
        $path = root(strtr($path, [
            '{_HOST}' => _HOST,
            '{_DOMAIN}' => _DOMAIN,
            '{_VIRTUAL}' => _VIRTUAL,
        ]));
        return rtrim($path, '/');
    }

    /**
     * 当前URI对应的缓存文件
     *
     * URI整体折进文件名，一个URI一个.php文件，
     * 避免在缓存目录下展开成深目录，也避免URI参与目录拼接
     *
     * @return string
     */
    private function cacheFile(): string
    {
        if (!isset($this->cache_file)) {
            $this->cache_file = $this->cache_path . '/' . md5($this->cache_key) . '.php';
        }
        return $this->cache_file;
    }

    /**
     * 删除
     * @param string $path
     * @param string $key
     * @return bool|int
     */
    public function Delete(string $path, string $key): bool|int
    {
        if ($this->_option['medium'] === 'file') {
            return @unlink("{$path}/{$key}.php");
        } else {
            return $this->_redis->del($key);
        }
    }

    /**
     * 保存静态HTML
     *
     * @param string $html
     * @return bool
     */
    private function htmlSave(string $html): bool
    {
        $filename = null;
        $pntKey = '';
        foreach ($this->_option['static'] as $pntKey => $ptn) {
            if ($pntKey === 'index') {
                if (_URI === '/') {
                    $filename = $ptn;
                    break;
                } else {
                    continue;
                }
            }
            if (preg_match($ptn, _URI)) {
                $filename = _URI;
                break;
            }
        }
        if (!$filename) return false;

        $tag = date('Y-m-d H:i:s');
        $label = "<!--\nstatic[{$pntKey}] saved `{$tag}`; by laocc/esp Cache\n-->";
        $html = str_replace('</html>', "{$label}\n</html>", $html);

        $path = rtrim($this->_option['path']['static'] ?? dirname(getenv('SCRIPT_FILENAME')), '/');
        mk_dir($path . $filename, 0740);
        $save = @file_put_contents($path . $filename, $html, LOCK_EX);

        if ($save !== strlen($html)) {
            @unlink($path . $filename);
            return false;
        }

        return true;
    }


    /**
     * 设置缓存的HTTP头
     * @param string|null $label
     */
    private function setHeader(string $label = null): void
    {
        if (headers_sent()) return;
        $ttl = $this->_option['ttl'];
        $time = time();

        //判断浏览器缓存是否过期
        if (($ms = getenv('HTTP_IF_MODIFIED_SINCE')) && (strtotime($ms) + $ttl) > $time) {
            $protocol = getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1';
            header("{$protocol} 304 Not Modified", true, 304);
        } else {
            header("Cache-Control: max-age={$ttl}, public");
            header('Expires: ' . gmdate('D, d M Y H:i:s', $time + $ttl) . ' GMT');
            header('Pragma: public');
            if ($label) header("Cache-Label: {$label}");
        }
    }

    /**
     * 禁止向浏览器缓存
     * @param null $label
     */
    private function disable_header($label = null): void
    {
        if (headers_sent()) return;
        header('Cache-Control: no-cache, must-revalidate, no-store', true);
        header('Pragma: no-cache', true);
        header('Cache-Info: no cache', true);
        header('Cache-Esp: no cache', true);
        if ($label) header('Cache-Label: ' . $label);
    }


}
