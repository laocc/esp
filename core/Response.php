<?php
declare(strict_types=1);

namespace esp\core;

use esp\face\Adapter;
use esp\helper\library\ext\Xml;
use function esp\helper\displayState;

final class Response
{
    private $_display_value;
    private Request $_request;
    private Resources $_resource;
    private string $_display_type = '';
    private array $_header = [];

    public string $_display_Result = '';//最终的打印结果
    public string $_Content_Type = 'text/html';
    public bool $cache;

    private array $_view_val = array();
    private array $_layout_val = [
        '_js_foot' => [],
        '_js_head' => [],
        '_js_body' => [],
        '_js_defer' => [],
        '_css' => [],
        '_meta' => ['keywords' => null, 'description' => null],
        '_title' => null,
        '_title_default' => true,
    ];

    private array $_view_set = [
        'view_use' => true,
        'view_file' => null,
        'file_ext' => '.php',
        'view_path' => null,
        'layout_use' => true,
        'layout_file' => null,
    ];

    private bool $_autoRun = true;

    private View $viewObj;
    private View $layoutObj;
    private string $renderHtml;
    private array $_adapter;

    public function __construct(Request $request, array $conf)
    {
        $this->_request = &$request;
        $this->_resource = new Resources($conf);
        if ($conf['adapter'] ?? null) {
            if (is_array($conf['adapter']) and isset($conf['adapter']['class'])) {
                $adConf = $conf['adapter'];
                $adConf['use'] = true;
                $adConf['layout'] = boolval($adConf['layout'] ?? false);
                $adConf['cache'] = realpath($adConf['cache'] ?? (_RUNTIME . '/cache'));
                if (!$adConf['cache']) $adConf['cache'] = _RUNTIME . '/cache';
                if ($adConf['class'][0] !== '\\') $adConf['class'] = '\\' . $adConf['class'];
                $this->_adapter = $adConf;
            }
        }

        if (isset($conf['views'])) $this->_view_set['view_path'] = $conf['views'];
        if (isset($conf['extend'])) $this->_view_set['file_ext'] = '.' . trim($conf['extend'], '.');
    }

    /**
     * @return Resources
     */
    public function getResource(): Resources
    {
        return $this->_resource;
    }

    /**
     * 接受控制器设置页面特殊显示内容
     * @param string $name
     * @param $value
     * @return bool
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

            case 'image':
                $this->_display_type = 'png';
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
                    $this->_display_type = '';
                    $this->_display_value = null;
                } else {
                    $this->_display_type = 'html';
                    $this->_display_value = $value;
                }
                break;

            default:
                esp_error('Response', "不接受{$name}类型的值");

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
     */
    public function display($value): void
    {
        if ($this->_autoRun === false) return;
//        $hasSend = headers_sent();
        if (!empty($this->_header)) {
            foreach ($this->_header as $kv) header("{$kv[0]}: {$kv[1]}");
        }

        if (is_null($value)) goto display;

        if (is_array($value)) {//直接显示为json/jsonP
            $this->_Content_Type = 'application/json';
            header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
            $json = json_encode($value, 256 | 64);
            if ($json === false) {
                try {
                    $this->_display_Result = json_encode([
                        'success' => 0,
                        'error' => 500,
                        'message' => '控制器返回的array无法序列化为json，请unserialize结果',
                        'result' => serialize($value)
                    ], 320);
                } catch (\Error $error) {
                    $this->_display_Result = json_encode([
                        'success' => 0,
                        'error' => 500,
                        'message' => '控制器返回的array无法序列化为json，请unserialize(base64_decode())结果',
                        'err_msg' => $error->getMessage(),
                        'result' => base64_encode(serialize($value))
                    ], 320);
                }
            } else {
                $this->_display_Result = $json;
            }

            if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                $this->_display_Result = "{$match[1]}({$this->_display_Result});";
            }
            echo $this->_display_Result;
            return;

        } else if (is_string($value)) {//直接按文本显示
            if (!empty($value) and $value[0] === '<') {
                $this->_Content_Type = 'text/html';
            } else {
                $this->_Content_Type = 'text/plain';
            }
            header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
            echo $this->_display_Result = &$value;
            return;

        } else if (is_int($value)) {//如果是某种错误代码，则显示为错误
            $this->_Content_Type = 'text/html';
            echo $this->_display_Result = displayState($value);
            return;

        } else if (is_bool($value)) {//简单表示是否立即渲染
//            if ($value) goto display;
            return;
        }

        display:
        if ($this->_request->isAjax()) return;
        echo $this->_display_Result = $this->render();
    }

    public function redirect(string $val): void
    {
        $this->_display_Result = $val;
        $this->_autoRun = false;
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
    public function getAdapter()
    {
        return $this->getView()->getAdapter();
    }

    /**
     * 视图注册标签解析器
     * @param Adapter $adapter
     * @return View|null
     */
    public function registerAdapter($adapter): ?View
    {
        if (!$this->_view_set['view_use']) return null;

        if (!$this->_request->virtual) {
            esp_error('Response', "registerAdapter要在routeAfter之后执行");
        }
        return $this->getView()->registerAdapter($adapter);
    }


    public function getViewPath(): string
    {
        if (is_null($this->_view_set['view_path'])) {
            $vmp = $this->_request->virtual;
            if ($this->_request->module) $vmp = "{$vmp}/{$this->_request->module}";
            return "{$this->_request->directory}/{$vmp}/views";
        } else {
            return $this->_view_set['view_path'];
        }
    }


    /**
     * 重新指定视图目录
     *
     * 若以@开头，为系统的绝对目录，注意是否有权限读取
     * 若以/开头，为相对于_ROOT的目录
     *
     * 其他均是指相对于/application/www的目录，
     * 如默认为    /application/www/views
     * 改为       /application/www/PATH
     *
     * 固定配置目录，可以在response.ini中定义：views = {_ROOT}/template/default
     *
     * @param string|null $path
     * @return $this
     */
    public function setViewPath(string $path): Response
    {
        $vmp = $this->_request->virtual;
        if ($this->_request->module) $vmp = "{$vmp}/{$this->_request->module}";
        $d = substr($path, 0, 1);
        if ($d === '/') {
            $this->_view_set['view_path'] = _ROOT . $path;
        } else if ($d === '@') {
            $this->_view_set['view_path'] = substr($path, 1);
        } else {
            $this->_view_set['view_path'] = "{$this->_request->directory}/{$vmp}/{$path}";
        }
        return $this;
    }

    /**
     * 设置是否启用视图
     * 设置视图文件名
     * 获取视图对象
     * @return View
     */
    public function getView(): View
    {
        if (isset($this->viewObj)) return $this->viewObj;
        $this->_view_set['view_use'] = true;
        return $this->viewObj = new View($this->getViewPath(), $this->_view_set['view_file'], $this->_view_set['file_ext']);
    }

    public function setView($value): Response
    {
        if (is_bool($value)) {
            $this->_view_set['view_use'] = $value;
        } elseif (is_string($value)) {
            if (strpos($value, '.') === false) {
                $value = "{$value}{$this->_view_set['file_ext']}";
            }
            if (strpos($value, '/') === false) {
                $value = "{$this->_request->controller}/{$value}";
            }
            $this->_view_set['view_use'] = true;
            $this->_view_set['view_file'] = $value;
            $this->getView()->file($value);
        }
        return $this;
    }

    public function setMarkDown(array $mdConf)
    {
        if (isset($mdConf['css'])) $this->_layout_val['_css'][] = $mdConf['css'];
        if (isset($mdConf['file'])) {
            $this->_view_set['view_use'] = true;
            $this->_view_set['view_file'] = $mdConf['file'];
            $this->getView()->file($mdConf['file']);
        }
        if (isset($mdConf['md'])) {
            $this->getView()->mdConf($mdConf['md']);
        }
        $this->_display_type = 'md';
        $this->_display_value = null;
    }


    /**
     * @return View
     */
    public function getLayout(): View
    {
        if (isset($this->layoutObj)) return $this->layoutObj;
        $this->_view_set['layout_use'] = true;
        $layout = $this->_view_set['layout_file'];
        if ($route = $this->_request->route_view) {
            if (isset($route['layout']) and $route['layout']) $layout = $route['layout'];
        }
        return $this->layoutObj = new View($this->getViewPath(), $layout, $this->_view_set['file_ext']);
    }

    public function setLayout($value): Response
    {
        if (is_bool($value)) {
            $this->_view_set['layout_use'] = $value;
        } elseif (is_string($value)) {
            if (!strpos($value, '.')) $value = "{$value}{$this->_view_set['file_ext']}";
            $this->_view_set['layout_use'] = true;
            $this->_view_set['layout_file'] = $value;
            $this->getLayout()->file($value);
        }
        return $this;
    }

    /**
     * 返回当前请求须响应的格式，json,xml,html,text等
     */
    public function getType(): string
    {
        return $this->_display_type;
    }

    /**
     * 渲染视图并返回
     * @return string
     */
    public function render(): string
    {
        if (isset($this->renderHtml)) return $this->renderHtml;
        $this->_Content_Type = 'text/html';
        switch (strtolower($this->_display_type)) {
            case 'json':
                $html = json_encode($this->_display_value, 256 | 64);
                if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                    $html = "{$match[1]}({$html});";
                }
                $this->_Content_Type = 'application/json';
                break;

            case 'php':
                $html = serialize($this->_display_value);
                $this->_Content_Type = 'application/octet-stream';
                break;

            case 'html':
                $html = print_r($this->_display_value, true);
                $this->_Content_Type = 'text/html';
                break;

            case 'text':
                $html = print_r($this->_display_value, true);
                $this->_Content_Type = 'text/plain';
                break;

            case 'png':
                $this->_display_value = preg_replace('/data:image\/\w+?;base64,/i', '', $this->_display_value);
                $html = base64_decode($this->_display_value);
                $this->_Content_Type = 'image/png';
                break;

            case 'xml':
                if (is_array($this->_display_value[1])) {
                    $html = (new Xml($this->_display_value[1], $this->_display_value[0]))->useCData(false)->render();
                } else {
                    $html = $this->_display_value[1];
                }
                $this->_Content_Type = 'text/xml';
                break;

            case 'md':
            default:
                $html = $this->display_response();
        }
        if (is_null($html)) return '';

        if (empty($this->_display_type)) $this->_display_type = 'html';

        if (!headers_sent()) {
            header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
        }

        $res_domain = $this->_resource->host();
        $res_rand = $this->_resource->rand();
        $res_prev = $this->_resource->path();

        //合并js/css
        if ($this->_resource->concat()) {
            preg_match_all("/<link.*?href=['\"](\/\w.+?)['\"].*?>/is", $html, $css, PREG_PATTERN_ORDER);
            $html = str_replace($css[0], '', $html);
            foreach ($css[1] as $i => $mch) {
                if (($w = strpos($mch, '?')) > 0) {
                    $css[1][$i] = substr($mch, 0, $w);
                }
                if (strpos($css[1][$i], $res_prev) === 0) $css[1][$i] = substr($css[1][$i], strlen($res_prev));
            }
            $import = $this->_resource->get('importCss');
            if ($import) array_push($css[1], ...$import);

            preg_match_all("/<script.*?src=['\"](\/\w.+?)['\"]><\/script>/i", $html, $jss, PREG_PATTERN_ORDER);
            $html = str_replace($jss[0], '', $html);
            foreach ($jss[1] as $i => $mch) {
                if (substr($mch, 0, 4) === 'http' or substr($mch, 0, 2) === '//') {
                    unset($jss[1][$i]);
                    continue;
                }

                if (($w = stripos($mch, '?')) > 0) {
                    $jss[1][$i] = substr($mch, 0, $w);
                }
                if (strpos($jss[1][$i], $res_prev) === 0) $jss[1][$i] = substr($jss[1][$i], strlen($res_prev));
            }

//            $html = str_replace(["    \n", "\t", "\t\n", "\t\r", "\n\n\n", "\r\r\r"], '', $html);

            $cssTag = implode(',', $css[1]);
            if (empty($css[0])) {
                $cssTag = '';
            } else {
                $cssTag = "<link media=\"all\" rel=\"stylesheet\" href=\"{$res_domain}/??{$cssTag}?{$res_rand}\">";
            }
            $jssTag = implode(',', $jss[1]);
            if (empty($jss[0])) {
                $jssTag = '';
            } else {
                $jssTag = "<script type=\"text/javascript\" charset=\"utf-8\" merge=\"true\" src=\"{$res_domain}??{$jssTag}?{$res_rand}\"></script>";
            }
            $html = str_replace("</head>", "{$cssTag}\n\t{$jssTag}\n</head>", $html);
        }

        //由resource过滤一些特殊的字符串
        return $this->renderHtml = $this->_resource->replace($html);
    }

    public function setAdapter(bool $use): Response
    {
        $this->_adapter['use'] = $use;
        return $this;
    }

    /**
     * 最后显示内容
     * @return null|string
     */
    private function display_response(): ?string
    {
        if ($this->_view_set['view_use'] === false) return null;

        $view = $this->getView();
        $this->cleared_layout_val();

        if (isset($this->_adapter) and $this->_adapter['use']) {
            $adp = $this->_adapter;
            $adc = new $adp['class']($adp['cache']);
            if ($adp['class'] === '\Smarty') $adc->setCompileDir($adp['cache']);
            $view->registerAdapter($adc);
        }

        if ($this->_view_set['layout_use']) {
            $layout = $this->getLayout();
            if (isset($adc) and $this->_adapter['layout']) $layout->registerAdapter($adc);//layout也启用解析器
            $layout->assign($this->_layout_val);//送入layout变量
            $view->layout($layout);//为视图注册layout
        } else {
            $view->assign($this->_layout_val);//无layout，将这些变量送入子视图
        }
        $this->_layout_val = [];

        $viewFileExt = $this->_view_set['file_ext'];
        if (strtolower($this->_display_type) === 'md') $viewFileExt = '.md';
        if ($route = $this->_request->route_view) {
            if (isset($route['path']) and $route['path']) $view->dir($route['path']);
            if (isset($route['file']) and $route['file']) $view->file($route['file']);
        }
        //组合一个默认值送到视图中，但是，有可能在这之前已经通过$this->getView()->file($value);指定过实际视图文件
        $file = "{$this->_request->controller}/{$this->_request->action}{$viewFileExt}";
        return $view->display_type($this->_display_type)->render(strtolower($file), $this->_view_val);
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
                    $conJS = array();
                    $http = array();
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
                $conCSS = array();
                $http = array();
                foreach ($this->_layout_val['_css'] as $css) {
                    if (substr($css, 0, 4) === 'http') {
                        $http[] = "<link rel=\"stylesheet\" href=\"{$css}\" />";
                    } else {
                        $conCSS[] = $css;
                    }
                }
                $conCSS = empty($conCSS) ? null : ($dom . '??' . implode(',', $this->_layout_val['_css']));
                $this->_layout_val['_css'] = '';
                if ($conCSS) $this->_layout_val['_css'] .= "<link rel=\"stylesheet\" href=\"{$conCSS}\" />\n";
                if (!empty($http)) $this->_layout_val['_css'] .= implode("\n", $http) . "\n";

            } else {
                $this->_layout_val['_css'] = null;
            }
        } /**
         * 不合并
         */
        else {
            foreach (['foot', 'head', 'body', 'defer'] as $pos) {
                $defer = ($pos === 'defer') ? ' defer="defer"' : null;
                foreach ($this->_layout_val["_js_{$pos}"] as $i => &$js) {
                    $js = "<script type=\"text/javascript\" src=\"{$domain($js)}\" charset=\"utf-8\" {$defer} ></script>";
                }
                $this->_layout_val["_js_{$pos}"] = implode("\n", $this->_layout_val["_js_{$pos}"]) . "\n";
            }
            foreach ($this->_layout_val['_css'] as $i => &$css) {
                $css = "<link rel=\"stylesheet\" href=\"{$domain($css)}\" />";
            }
            $this->_layout_val['_css'] = implode("\n", $this->_layout_val['_css']) . "\n";
        }

        $this->_layout_val['_meta']['keywords'] = $this->_layout_val['_meta']['keywords'] ?: $this->_resource->keywords();
        $this->_layout_val['_meta']['description'] = $this->_layout_val['_meta']['description'] ?: $this->_resource->description();

        foreach ($this->_layout_val['_meta'] as $i => $meta) {
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
     * @param $name
     * @param null $value
     */
    public function assign($name, $value = null): void
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
        return $this->_view_val[$name] ?? null;
    }

    public function set(string $name, $value): void
    {
        $this->assign($name, $value);
    }


    public function js($file, $pos = 'foot'): void
    {
        $pos = in_array($pos, ['foot', 'head', 'body', 'defer']) ? $pos : 'foot';
        if (is_array($file)) {
            array_push($this->_layout_val["_js_{$pos}"], ...$file);
        } else {
            $this->_layout_val["_js_{$pos}"][] = $file;
        }
    }


    public function css($file): void
    {
        if (is_array($file)) {
            array_push($this->_layout_val['_css'], ...$file);
        } else {
            $this->_layout_val['_css'][] = $file;
        }
    }


    public function meta(string $name, $value): void
    {
        $this->_layout_val['_meta'][$name] = $value;
    }

    /**
     * @param string $title
     * @param bool|null $overwrite
     *
     *      * $overwrite:
     * 默认null：最终的<title>为 $title + response.title
     * =true：覆盖response.title中的值
     * =false：仅显示 $title
     */
    public function title(string $title, bool $overwrite = null): void
    {
        if ($overwrite === true) {
            $this->_resource->title($title);
            return;
        } else if ($overwrite === false) {
            $this->_layout_val['_title_default'] = false;
        }
        $this->_layout_val['_title'] = $title;
    }


    public function keywords(string $value): void
    {
        $this->_layout_val['_meta']['keywords'] = $value;
    }


    public function description(string $value): void
    {
        $this->_layout_val['_meta']['description'] = $value;
    }

    /**
     * 设置缓存
     *
     * @param bool $run
     */
    public function cache(bool $run = true): void
    {
        $this->cache = $run;
    }

    /**
     * echo
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this, true);
    }

    /**
     * var_dump
     * @return array
     */
    public function __debugInfo()
    {
        return [__CLASS__];
    }

}