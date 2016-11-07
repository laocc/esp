<?php
namespace wbf\core;


abstract class Controller
{
    private $_kernel;
    private $_request;
    private $_view_val = [];
    private $_use_layout;
    private $_use_adapter;
    private $_content;


    private $_layout_val = [
        '_js_foot' => [],
        '_js_head' => [],
        '_js_body' => [],
        '_js_defer' => [],
        '_css' => [],
        '_meta' => ['keywords' => null, 'description' => null],
        '_title' => null,
        '_title_default' => true,
    ];

    final public function __construct(Kernel $kernel, $request)
    {
        $this->_kernel = $kernel;
        $this->_request = $request;
    }

    final public function json(array $value)
    {
        $this->_content = [
            'type' => 'json',
            'value' => $value,
        ];
    }

    final public function text($value)
    {
        $this->_content = [
            'type' => 'text',
            'value' => $value,
        ];
    }

    final public function xml($root, array $value = null)
    {
        if (is_array($root)) list($root, $value) = ['xml', $root];
        $this->_content = [
            'type' => 'xml',
            'root' => $root,
            'value' => $value,
        ];
    }


    /**
     * 设置视图文件，或获取对象
     * @return View
     */
    final public function view($file = null)
    {
        static $obj;
        if (!is_null($obj)) return $obj;
        $dir = rtrim($this->_request['directory'], '/') . '/' . $this->_request['module'] . '/views/';
        return $obj = new View($dir, $file);
    }

    final public function adapter($bool = null)
    {
        if ($bool === false) {
            return $this->_use_adapter = $bool;
        }

        static $_adapter;
        if (!is_null($_adapter)) return $_adapter;

        $conf = Config::get('adapter');
        if (!$conf) return null;
        $dir = rtrim($this->_request['directory'], '/') . '/' . $this->_request['module'] . '/views/';
        $this->_use_adapter = true;

        $conf['driver'] = '\\' . ucfirst($conf['driver']);
        $_adapter = new $conf['driver']();
        $_adapter->{'template_dir'} = $dir;//视图主目录
        $_adapter->{'compile_dir'} = $conf['compile_dir'];//解析器缓存目录
        $_adapter->{'cache_dir'} = $conf['cache_dir'];//缓存目录

        return $_adapter;
    }

    /**
     * 关闭，或获取layout对象，可同时指定框架文件
     * @param null $file
     * @return bool|View
     */
    final public function layout($layout_file = null)
    {
        if ($layout_file === false) {
            return $this->_use_layout = false;
        }

        static $obj;
        if (!is_null($obj)) return $obj;
        $layout = Config::get('layout.filename');
        $dir = rtrim($this->_request['directory'], '/') . '/' . $this->_request['module'] . '/views/';
        $layout_file = $layout_file ?: $this->_request['controller'] . '/' . $layout;
        if (stripos($layout_file, $dir) !== 0) $layout_file = $dir . ltrim($layout_file, '/');
        if (!is_file($layout_file)) $layout_file = $dir . $layout;
        if (!is_file($layout_file)) error('框架视图文件不存在');
        $this->_use_layout = true;
        return $obj = new View($dir, $layout_file);
    }


    /**
     * 检查来路是否本站相同域名
     * 本站_HOST，总是被列入查询，另外自定义更多的host，
     * 若允许本站或空来路，则用：$this->check_host('');
     *
     * @param array ...$host
     */
    final public function check_host(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host(_REFERER), array_merge([_HOST], $host))) error(Config::get('error.host'));
    }

    /**
     * @param $request
     */
    final public function setRequest($request)
    {
        $this->_request = $request;
    }


    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    final public function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->_view_val[$k] = $v;
            }
        } else {
            $this->_view_val[$name] = $value;
        }
    }

    final public function __set($name, $value)
    {
        $this->_view_val[$name] = $value;
    }

    final public function __get($name)
    {
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
    }

    final public function set($name, $value)
    {
        $this->_view_val[$name] = $value;
    }

    final public function get($name)
    {
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
    }


    final public function js($file, $pos = 'foot')
    {
        $pos = in_array($pos, ['foot', 'head', 'body', 'defer']) ? $pos : 'foot';
        if (is_array($file)) {
            array_push($this->_layout_val["_js_{$pos}"], ...$file);
        } else {
            $this->_layout_val["_js_{$pos}"][] = $file;
        }
        return $this;
    }


    final public function css($file)
    {
        if (is_array($file)) {
            array_push($this->_layout_val['_css'], ...$file);
        } else {
            $this->_layout_val['_css'][] = $file;
        }
        return $this;
    }


    final public function meta($name, $value)
    {
        $this->_layout_val['_meta'][$name] = $value;
        return $this;
    }


    final public function title($title, $default = true)
    {
        $this->_layout_val['_title'] = $title;
        if (!$default) $this->_layout_val['_title_default'] = false;
        return $this;
    }


    final public function keywords($value)
    {
        $this->_layout_val['_meta']['keywords'] = $value;
        return $this;
    }


    final public function description($value)
    {
        $this->_layout_val['_meta']['description'] = $value;
        return $this;
    }


    /**
     * 最后显示内容
     */
    final public function display_response()
    {
        if (!is_null($this->_content)) {
            $mime = Config::mime($this->_content['type']);
            header('Content-type:' . $mime, true);

            switch (strtolower($this->_content['type'])) {
                case 'json':
                    $value = json_encode($this->_content['value'], 256);
                    if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                        $value = "{$match[1]}({$value});";
                    }
                    echo $value;
                    break;
                case 'text':
                    print_r($this->_content['value']);
                    break;
                case 'xml':
                    echo (new \wbf\library\Xml($this->_content['value'], $this->_content['root']))->render();
                    break;
                default:
                    error("未知页面显示方式：{$this->_content['type']}");
            }
            return;
        }

        $view = $this->view();
        $file = $this->_request['controller'] . '/' . $this->_request['action'] . '.' . ltrim(Config::get('wbf.viewExt'), '.');
        $this->cleared_resource();

        if (is_null($this->_use_layout)) $this->_use_layout = Config::get('layout.autoRun');
        if (is_null($this->_use_adapter)) $this->_use_adapter = Config::get('adapter.autoRun');

        //送入框架对象
        if ($this->_use_layout) {
            $layout = $this->layout();
            $layout->assign($this->_layout_val);
            $this->_layout_val = null;
            $view->layout($layout);
        } else {
            $this->assign($this->_layout_val);
            $this->_layout_val = null;
        }

        if ($this->_use_adapter) $view->adapter($this->adapter());

        $view->display($file, $this->_view_val);
    }

    /**
     * 整理layout中的变量
     */
    final private function cleared_resource()
    {
        $resource = Config::get('resource');
        $dom = rtrim($resource['domain'], '/');
        $rand = $resource['rand'] ? ('?' . time()) : null;

        $domain = function ($item) use ($dom, $resource, $rand) {
            if (substr($item, 0, 4) === 'http') return $item;
            if ($item === 'jquery') $item = $resource['jquery'];
            return $dom . '/' . ltrim($item, '/') . $rand;
        };

        if ($resource['concat']) {
            foreach (['foot', 'head', 'body', 'defer'] as $pos) {
                if (!empty($this->_layout_val["_js_{$pos}"])) {
                    $defer = ($pos === 'defer') ? ' defer="defer"' : null;
                    $concat = [];
                    $http = [];
                    foreach ($this->_layout_val["_js_{$pos}"] as &$js) {
                        if ($js === 'jquery') $js = $resource['jquery'];
                        if (substr($js, 0, 4) === 'http') {
                            $http[] = "<script type=\"text/javascript\" src=\"{$js}\" charset=\"utf-8\" {$defer} ></script>";
                        } else {
                            $concat[] = $js;
                        }
                    }
                    $concat = empty($concat) ? null : ($dom . '??' . implode(',', $concat) . $rand);
                    $this->_layout_val["_js_{$pos}"] = '';
                    if ($concat) $this->_layout_val["_js_{$pos}"] .= "<script type=\"text/javascript\" src=\"{$concat}\" charset=\"utf-8\" {$defer} ></script>\n";
                    if (!empty($http)) $this->_layout_val["_js_{$pos}"] .= implode("\n", $http) . "\n";
                } else {
                    $this->_layout_val["_js_{$pos}"] = null;
                }
            }
            if (!empty($this->_layout_val['_css'])) {
                $concat = [];
                $http = [];
                foreach ($this->_layout_val['_css'] as &$css) {
                    if (substr($css, 0, 4) === 'http') {
                        $http[] = "<link rel=\"stylesheet\" href=\"{$css}\" charset=\"utf-8\" />";
                    } else {
                        $concat[] = $css;
                    }
                }
                $concat = empty($concat) ? null : ($dom . '??' . implode(',', $this->_layout_val['_css']) . $rand);
                $this->_layout_val['_css'] = '';
                if ($concat) $this->_layout_val['_css'] .= "<link rel=\"stylesheet\" href=\"{$concat}\" charset=\"utf-8\" />\n";
                if (!empty($http)) $this->_layout_val['_css'] .= implode("\n", $http) . "\n";

            } else {
                $this->_layout_val['_css'] = null;
            }
        } else {
            foreach (['foot', 'head', 'body', 'defer'] as $pos) {
                $defer = ($pos === 'defer') ? ' defer="defer"' : null;
                foreach ($this->_layout_val["_js_{$pos}"] as $i => &$js) {
                    $js = "<script type=\"text/javascript\" src=\"{$domain($js)}\" charset=\"utf-8\" {$defer} ></script>";
                }
                $this->_layout_val["_js_{$pos}"] = implode("\n", $this->_layout_val["_js_{$pos}"]) . "\n";
            }
            foreach ($this->_layout_val['_css'] as $i => &$css) {
                $css = "<link rel=\"stylesheet\" href=\"{$domain($css)}\" charset=\"utf-8\" />";
            }
            $this->_layout_val['_css'] = implode("\n", $this->_layout_val['_css']) . "\n";
        }

        $this->_layout_val['_meta']['keywords'] = $this->_layout_val['_meta']['keywords'] ?: $resource['keywords'];
        $this->_layout_val['_meta']['description'] = $this->_layout_val['_meta']['description'] ?: $resource['description'];

        foreach ($this->_layout_val['_meta'] as $i => &$meta) {
            $this->_layout_val['_meta'][$i] = "<meta name=\"{$i}\" content=\"{$meta}\" />";
        }
        $this->_layout_val['_meta'] = implode("\n", $this->_layout_val['_meta']) . "\n";

        if (is_null($this->_layout_val['_title'])) {
            $this->_layout_val['_title'] = $resource['title'];
        } elseif ($this->_layout_val['_title_default']) {
            $this->_layout_val['_title'] .= ' - ' . $resource['title'];
        }
        unset($this->_layout_val['_title_default']);
    }


}