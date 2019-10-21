<?php

namespace esp\core;

use esp\core\face\Adapter;
use esp\library\ext\Xml;

final class Response
{
    private $_display_value = Array();
    private $_request;
    private $_resource;
    private $_display_type;
    public $_display_Result;//最终的打印结果
    public $_Content_Type;
    private $_save_cache = false;
    private $_header = [];

    private $_view_val = Array();
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

    private $_view_set = [
        'view_use' => true,
        'view_file' => null,
        'view_path' => null,
        'layout_use' => true,
        'layout_file' => null,
    ];

    private $_autoRun = true;

    public function __construct(Request $request)
    {
        $this->_request = $request;
        $this->_resource = new Resources();
        $this->_autoRun = !_CLI;
    }

    /**
     * @return Resources
     */
    public function getResource()
    {
        return $this->_resource;
    }

    /**
     * 接受控制器设置页面特殊显示内容
     * @param string $name
     * @param $value
     * @return bool
     * @throws \Exception
     */
    public function set_value(string $name, $value): bool
    {
        switch ($name) {
            case 'json':
                $this->_display_type = 'json';
                $this->_display_value = $value;
                break;

            case 'xml':
                $this->_display_type = 'xml';
                $this->_display_value = $value;
                break;

            case 'php':
                $this->_display_type = 'php';
                $this->_display_value = $value;
                break;

            case 'text':
                $this->_display_type = 'text';
                $this->_display_value = $value;
                break;

            case 'md':
                $this->_display_type = 'md';
                $this->_display_value = $value;
                break;

            case 'html':
                if (is_null($value)) {
                    $this->_display_type = null;
                    $this->_display_value = null;
                } else {
                    $this->_display_type = 'html';
                    $this->_display_value = $value;
                }
                break;

            default:
                throw new \Exception("不接受{$name}类型的值");

        }
        return true;
    }


    final public function header(...$kv): void
    {
        if (is_array($kv[0])) $kv = $kv[0];
        $this->_header[] = $kv;
    }

    /**
     * 渲染视图并返回
     * @param void $value 控制器返回的值
     * @throws \Exception
     */
    public function display(&$value): void
    {
        if ($this->_autoRun === false) return;
//        if (!headers_sent()) header('sid: ' . ip2long(getenv('SERVER_ADDR')));
        if (!empty($this->_header)) {
            foreach ($this->_header as $kv) {
                header("{$kv[0]}: {$kv[1]}");
            }
        }

        if (is_null($value)) goto display;

        if (is_array($value)) {//直接显示为json/jsonP
            $this->_Content_Type = 'application/json';
            header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
            $this->_display_Result = json_encode($value, 256);
            if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                $this->_display_Result = "{$match[1]}({$this->_display_Result});";
            }
            echo $this->_display_Result;
            return;

        } else if (is_string($value)) {//直接按文本显示
            if (!empty($value) and $value[0] === '<') {
                $this->_Content_Type = 'text/html';
                header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
            } else {
                $this->_Content_Type = 'text/plain';
                header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
            }
            echo $this->_display_Result = &$value;
            return;

        } else if (is_int($value)) {//如果是某种错误代码，则显示为错误
            $this->_Content_Type = 'text/html';
            echo $this->_display_Result = Error::displayState($value);
            return;

        } else if (is_bool($value)) {//简单表示是否立即渲染
//            if ($value) goto display;
            return;
        }

        display:

        echo $this->_display_Result = $this->render();
    }

    public function redirect($val): void
    {
        $this->_display_Result = $val;
    }

    public function autoRun(bool $run): Response
    {
        $this->_autoRun = $run;
        return $this;
    }

    public function concat(bool $run): Response
    {
        $this->_resource->concat($run);
        return $this;
    }

    /**
     * 返回标签解析器
     * @return Adapter
     */
    public function getAdapter(): Adapter
    {
        return $this->getView()->getAdapter();
    }

    /**
     * 视图注册标签解析器
     * @param Adapter $adapter
     * @return View
     * @throws \Exception
     */
    public function registerAdapter(Adapter $adapter): View
    {
        if (!$this->_request->module) {
            throw new \Exception("registerAdapter要在routeAfter之后执行", 500);
        }
        return $this->getView()->registerAdapter($adapter);
    }


    public function viewPath(string $path = null)
    {
        if (is_null($path) or empty($path)) {
            if (is_null($this->_view_set['view_path'])) {
                return $this->_request->directory . '/' . $this->_request->module . '/views/';
            } else {
                return $this->_view_set['view_path'];
            }
        } else {
            if ($path[0] !== '/') $path = $this->_request->directory . '/' . $this->_request->module . "/{$path}/";
            $this->_view_set['view_path'] = $path;
            return $this;
        }
    }

    /**
     * 设置是否启用视图
     * 设置视图文件名
     * 获取视图对象
     * @param $file
     * @return bool|View
     */
    public function getView(): View
    {
        static $obj;
        if (!is_null($obj)) return $obj;
        $this->_view_set['view_use'] = true;
        return $obj = new View($this->viewPath(), $this->_view_set['view_file']);
    }

    public function setView($value): Response
    {
        if (is_bool($value)) {
            $this->_view_set['view_use'] = $value;
        } elseif (is_string($value)) {
            $this->_view_set['view_use'] = true;
            $this->_view_set['view_file'] = $value;
            $this->getView()->file($value);
        }
        return $this;
    }


    /**
     * @return View
     */
    public function getLayout(): View
    {
        static $obj;
        if (!is_null($obj)) return $obj;
        $this->_view_set['layout_use'] = true;
        return $obj = new View($this->viewPath(), $this->_view_set['layout_file']);
    }

    public function setLayout($value): Response
    {
        if (is_bool($value)) {
            $this->_view_set['layout_use'] = $value;
        } elseif (is_string($value)) {
            $this->_view_set['layout_use'] = true;
            $this->_view_set['layout_file'] = $value;
            $this->getLayout()->file($value);
        }
        return $this;
    }

    /**
     * 返回当前请求须响应的格式，json,xml,html,text等
     * @return mixed
     */
    public function getType()
    {
        return $this->_display_type;
    }

    /**
     * 渲染视图并返回
     * @return mixed|string
     * @throws \Exception
     */
    public function render()
    {
        static $html;
        if (!is_null($html)) return $html;

        switch (strtolower($this->_display_type)) {
            case 'json':
                $html = json_encode($this->_display_value, 256);
                if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                    $html = "{$match[1]}({$html});";
                }
                break;

            case 'php':
                $html = serialize($this->_display_value);
                break;

            case 'html':
                $html = print_r($this->_display_value, true);
                break;

            case 'text':
                $html = print_r($this->_display_value, true);
                break;

            case 'xml':
                if (is_array($this->_display_value[1])) {
                    $html = (new Xml($this->_display_value[1], $this->_display_value[0]))->render();
                } else {
                    $html = $this->_display_value[1];
                }
                break;

            case 'md':
            default:
                $html = $this->display_response();
        }
        if (is_null($html)) return '';

        if (is_null($this->_display_type)) $this->_display_type = 'html';

        $this->_Content_Type = Config::mime($this->_display_type);

        if (!headers_sent()) {
            header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
        }

        $res_domain = $this->_resource->host();
        $res_rand = $this->_resource->rand();
        $res_prev = $this->_resource->path();

        //合并js/css
        if ($this->_resource->concat()) {
            preg_match_all("/<link.*?href=['\"](.+?)['\"].*?\>/is", $html, $css, PREG_PATTERN_ORDER);
            $html = str_replace($css[0], '', $html);
            foreach ($css[1] as $i => $mch) {
                if (($w = strpos($mch, '?')) > 0) {
                    $css[1][$i] = substr($mch, 0, $w);
                }
                if (strpos($css[1][$i], $res_prev) === 0) $css[1][$i] = substr($css[1][$i], strlen($res_prev));
            }
            $import = $this->_resource->get('importcss');
            if ($import) array_push($css[1], ...$import);

            preg_match_all("/<script.*?src=['\"](.+?)['\"]\><\/script>/i", $html, $jss, PREG_PATTERN_ORDER);
            $html = str_replace($jss[0], '', $html);
            foreach ($jss[1] as $i => $mch) {
                if (($w = strpos($mch, '?')) > 0) {
                    $jss[1][$i] = substr($mch, 0, $w);
                }
                if (strpos($jss[1][$i], $res_prev) === 0) $jss[1][$i] = substr($jss[1][$i], strlen($res_prev));
            }

//            $html = str_replace(["    \n", "\t", "\t\n", "\t\r", "\n\n\n", "\r\r\r"], '', $html);

            $cssTag = implode(',', $css[1]);
            if (empty($css[0])) {
                $cssTag = '';
            } else {
                $cssTag = "<link media=\"all\" charset=\"utf-8\" rel=\"stylesheet\" href=\"{$res_domain}/??{$cssTag}?{$res_rand}\">";
            }
            $jssTag = implode(',', $jss[1]);
            if (empty($jss[0])) {
                $jssTag = '';
            } else {
                $jssTag = "<script type=\"text/javascript\" charset=\"utf-8\" merge=\"true\" src=\"{$res_domain}??{$jssTag}?{$res_rand}\"></script>";
            }
            $html = str_replace("</head>", "{$cssTag}\n\t{$jssTag}\n</head>", $html);
        }

        return $this->_resource->replace($html);
    }


    /**
     * 最后显示内容
     * @return null|string
     * @throws \Exception
     */
    private function display_response()
    {
        if ($this->_view_set['view_use'] === false) return null;

        $view = $this->getView();
        $this->cleared_layout_val();

        if ($this->_view_set['layout_use']) {
            $layout = $this->getLayout();
            $layout->assign($this->_layout_val);//送入layout变量
            $view->layout($layout);//为视图注册layout
        } else {
            $view->assign($this->_layout_val);//无layout，将这些变量送入子视图
        }
        $this->_layout_val = null;

        $viewFileExt = 'php';
        if (strtolower($this->_display_type) === 'md') $viewFileExt = 'md';
        if ($route = $this->_request->route_view) {
            if (isset($route['path']) and $route['path']) $view->dir($route['path']);
            if (isset($route['file']) and $route['file']) $view->file($route['file']);
        }
        $file = "{$this->_request->controller}/{$this->_request->action}.{$viewFileExt}";
        return $view->render(strtolower($file), $this->_view_val);
    }

    /**
     * 整理layout中的变量
     */
    private function cleared_layout_val(): void
    {
        $dom = $this->_resource->host();

        $domain = function ($item) use ($dom) {
            if (substr($item, 0, 4) === 'http') return $item;
            if (substr($item, 0, 1) === '.') return $item;
            if (substr($item, 0, 1) === '/') return $item;
            if (substr($item, 0, 2) === '//') return substr($item, 1);
            if ($item === 'jquery') $item = $this->_resource->get('jquery');
            return $dom . '/' . ltrim($item, '/');
        };

        if (0 and $this->_resource->concat()) {
            foreach (['foot', 'head', 'body', 'defer'] as $pos) {
                if (!empty($this->_layout_val["_js_{$pos}"])) {
                    $defer = ($pos === 'defer') ? ' defer="defer"' : null;
                    $conJS = Array();
                    $http = Array();
                    foreach ($this->_layout_val["_js_{$pos}"] as &$js) {
                        if ($js === 'jquery') $js = $this->_resource->get('jquery');
                        if (substr($js, 0, 4) === 'http') {
                            $http[] = "<script type=\"text/javascript\" src=\"{$js}\" charset=\"utf-8\" {$defer} ></script>";
                        } else {
                            $conJS[] = $js;
                        }
                    }
                    $conJS = empty($conJS) ? null : ($dom . '??' . implode(',', $conJS));
                    $this->_layout_val["_js_{$pos}"] = '';
                    if ($conJS) $this->_layout_val["_js_{$pos}"] .= "<script type=\"text/javascript\" src=\"{$conJS}\" charset=\"utf-8\" {$defer} ></script>\n";
                    if (!empty($http)) $this->_layout_val["_js_{$pos}"] .= implode("\n", $http) . "\n";
                } else {
                    $this->_layout_val["_js_{$pos}"] = null;
                }
            }
            if (!empty($this->_layout_val['_css'])) {
                $conCSS = Array();
                $http = Array();
                foreach ($this->_layout_val['_css'] as &$css) {
                    if (substr($css, 0, 4) === 'http') {
                        $http[] = "<link rel=\"stylesheet\" href=\"{$css}\" charset=\"utf-8\" />";
                    } else {
                        $conCSS[] = $css;
                    }
                }
                $conCSS = empty($conCSS) ? null : ($dom . '??' . implode(',', $this->_layout_val['_css']));
                $this->_layout_val['_css'] = '';
                if ($conCSS) $this->_layout_val['_css'] .= "<link rel=\"stylesheet\" href=\"{$conCSS}\" charset=\"utf-8\" />\n";
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

        $this->_layout_val['_meta']['keywords'] = $this->_layout_val['_meta']['keywords'] ?: $this->_resource->keywords();
        $this->_layout_val['_meta']['description'] = $this->_layout_val['_meta']['description'] ?: $this->_resource->description();

        foreach ($this->_layout_val['_meta'] as $i => &$meta) {
            $this->_layout_val['_meta'][$i] = "<meta name=\"{$i}\" content=\"{$meta}\" />";
        }
        $this->_layout_val['_meta'] = implode("\n    ", $this->_layout_val['_meta']) . "\n";

        if (is_null($this->_layout_val['_title'])) {
            $this->_layout_val['_title'] = $this->_resource->title();
        } else if ($this->_layout_val['_title_default']) {
            $this->_layout_val['_title'] .= ' - ' . $this->_resource->title();
        }
        unset($this->_layout_val['_title_default']);
    }

    /**
     * 向视图送变量
     * @param string $name
     * @param null $value
     */
    public function assign(string $name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->_view_val[$k] = $v;
            }
        } else {
            $this->_view_val[$name] = $value;
        }
    }

    public function get(string $name)
    {
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
    }

    public function set(string $name, $value)
    {
        $this->assign($name, $value);
    }


    public function js($file, $pos = 'foot')
    {
        $pos = in_array($pos, ['foot', 'head', 'body', 'defer']) ? $pos : 'foot';
        if (is_array($file)) {
            array_push($this->_layout_val["_js_{$pos}"], ...$file);
        } else {
            $this->_layout_val["_js_{$pos}"][] = $file;
        }
        return $this;
    }


    public function css($file)
    {
        if (is_array($file)) {
            array_push($this->_layout_val['_css'], ...$file);
        } else {
            $this->_layout_val['_css'][] = $file;
        }
        return $this;
    }


    public function meta(string $name, $value)
    {
        $this->_layout_val['_meta'][$name] = $value;
        return $this;
    }


    public function title(string $title, bool $default = true)
    {
        $this->_layout_val['_title'] = $title;
        if (!$default) $this->_layout_val['_title_default'] = false;
        return $this;
    }


    public function keywords(string $value)
    {
        $this->_layout_val['_meta']['keywords'] = $value;
        return $this;
    }


    public function description(string $value)
    {
        $this->_layout_val['_meta']['description'] = $value;
        return $this;
    }

    public function cache(bool $save = true)
    {
        $this->_save_cache = $save;
    }

}