<?php

namespace esp\core;


use esp\library\ext\Xml;

final class Response
{
    private $_display_value = Array();
    private $_request;
    private $_display_type;
    public $_display_Result;//最终的打印结果
    public $_Content_Type;

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
        'layout_use' => true,
        'layout_file' => null,
    ];

    private $_autoRun = true;

    public function __construct(Request $request)
    {
        $this->_request = $request;
    }


    /**
     * 接受控制器设置页面特殊显示内容
     * @param string $name
     * @param $value
     * @return bool
     * @throws \Exception
     */
    public function set_value(string $name, $value)
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


    /**
     * 渲染视图并返回
     * @param void $value 控制器返回的值
     */
    public function display(&$value)
    {
        if ($this->_autoRun === false) return;
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
            $this->_Content_Type = 'text/plain';
            header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
            echo $this->_display_Result = &$value;
            return;

        } else if (is_int($value)) {//如果是某种错误代码，则显示为错误
            $this->_Content_Type = 'text/html';
            echo $this->_display_Result = Error::displayState($value);
            return;

        } else if (is_bool($value)) {//简单表示是否立即渲染
            if ($value) goto display;
            return;
        }

        display:

        echo $this->_display_Result = $this->render();
    }


    public function autoRun(bool $run)
    {
        $this->_autoRun = $run;
        return $this;
    }

    /**
     * 返回标签解析器
     * @return View
     */
    public function getAdapter()
    {
        return $this->getView()->getAdapter();
    }

    /**
     * 视图注册标签解析器
     * @param $adapter
     */
    public function registerAdapter($adapter)
    {
        $this->getView()->registerAdapter($adapter);
    }


    public function viewFolder()
    {
        $dir = $this->_request->directory . '/' . $this->_request->module . '/views/';
        if (Client::is_wap()) {
            if (is_dir($dir_wap = $this->_request->directory . '/' . $this->_request->module . '/views_wap/')) {
                $dir = $dir_wap;
            }
        }
        return $dir;
    }

    /**
     * 设置是否启用视图
     * 设置视图文件名
     * 获取视图对象
     * @param $file
     * @return bool|View
     */
    public function getView()
    {
        static $obj;
        if (!is_null($obj)) return $obj;
        $this->_view_set['view_use'] = true;
        return $obj = new View($this->viewFolder(), $this->_view_set['view_file']);
    }

    public function setView($value)
    {
        if (is_bool($value)) {
            $this->_view_set['view_use'] = $value;
        } elseif (is_string($value)) {
            $this->_view_set['view_use'] = true;
            $this->_view_set['view_file'] = $value;
            $this->getView()->file($value);
        }
    }


    public function getLayout()
    {
        static $obj;
        if (!is_null($obj)) return $obj;
        $this->_view_set['layout_use'] = true;
        return $obj = new View($this->viewFolder(), $this->_view_set['layout_file']);
    }

    public function setLayout($value)
    {
        if (is_bool($value)) {
            $this->_view_set['layout_use'] = $value;
        } elseif (is_string($value)) {
            $this->_view_set['layout_use'] = true;
            $this->_view_set['layout_file'] = $value;
            $this->getLayout()->file($value);
        }
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
        if (is_null($this->_display_type)) $this->_display_type = 'html';

        $this->_Content_Type = Config::mime($this->_display_type);

        if (!headers_sent()) {
            header("Content-type: {$this->_Content_Type}; charset=UTF-8", true, 200);
        }

        return $html;
    }


    /**
     * 最后显示内容
     * @return null|string
     * @throws \Exception
     */
    private function display_response()
    {
        if ($this->_view_set['view_use'] === false) return null;

//        $this->getResponse()->getView()->dir($this->_response->viewFolder());//重新注册视图目录

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
        $file = "{$this->_request->controller}/{$this->_request->action}.{$viewFileExt}";

        return $view->render(strtolower($file), $this->_view_val);
    }

    /**
     * 整理layout中的变量
     */
    private function cleared_layout_val()
    {
        $resource = Config::get('resource.default');
        $module = Config::get('resource.' . _MODULE);
        if (is_array($module)) $resource = $module + $resource;

        $dom = rtrim($resource['domain'] ?? '', '/');

        $domain = function ($item) use ($dom, $resource) {
            if (substr($item, 0, 4) === 'http') return $item;
            if (substr($item, 0, 1) === '.') return $item;
            if (substr($item, 0, 2) === '//') return substr($item, 1);
            if ($item === 'jquery') $item = $resource['jquery'];
            return $dom . '/' . ltrim($item, '/');
        };

        if ($resource['concat'] ?? false) {
            foreach (['foot', 'head', 'body', 'defer'] as $pos) {
                if (!empty($this->_layout_val["_js_{$pos}"])) {
                    $defer = ($pos === 'defer') ? ' defer="defer"' : null;
                    $concat = Array();
                    $http = Array();
                    foreach ($this->_layout_val["_js_{$pos}"] as &$js) {
                        if ($js === 'jquery') $js = $resource['jquery'] ?? '';
                        if (substr($js, 0, 4) === 'http') {
                            $http[] = "<script type=\"text/javascript\" src=\"{$js}\" charset=\"utf-8\" {$defer} ></script>";
                        } else {
                            $concat[] = $js;
                        }
                    }
                    $concat = empty($concat) ? null : ($dom . '??' . implode(',', $concat));
                    $this->_layout_val["_js_{$pos}"] = '';
                    if ($concat) $this->_layout_val["_js_{$pos}"] .= "<script type=\"text/javascript\" src=\"{$concat}\" charset=\"utf-8\" {$defer} ></script>\n";
                    if (!empty($http)) $this->_layout_val["_js_{$pos}"] .= implode("\n", $http) . "\n";
                } else {
                    $this->_layout_val["_js_{$pos}"] = null;
                }
            }
            if (!empty($this->_layout_val['_css'])) {
                $concat = Array();
                $http = Array();
                foreach ($this->_layout_val['_css'] as &$css) {
                    if (substr($css, 0, 4) === 'http') {
                        $http[] = "<link rel=\"stylesheet\" href=\"{$css}\" charset=\"utf-8\" />";
                    } else {
                        $concat[] = $css;
                    }
                }
                $concat = empty($concat) ? null : ($dom . '??' . implode(',', $this->_layout_val['_css']));
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

        $this->_layout_val['_meta']['keywords'] = $this->_layout_val['_meta']['keywords'] ?: $resource['keywords'] ?? '';
        $this->_layout_val['_meta']['description'] = $this->_layout_val['_meta']['description'] ?: $resource['description'] ?? '';

        foreach ($this->_layout_val['_meta'] as $i => &$meta) {
            $this->_layout_val['_meta'][$i] = "<meta name=\"{$i}\" content=\"{$meta}\" />";
        }
        $this->_layout_val['_meta'] = implode("\n", $this->_layout_val['_meta']) . "\n";

        if (is_null($this->_layout_val['_title'])) {
            $this->_layout_val['_title'] = $resource['title'] ?? '';
        } elseif ($this->_layout_val['_title_default']) {
            $this->_layout_val['_title'] .= ' - ' . $resource['title'] ?? '';
        }
        unset($this->_layout_val['_title_default']);
    }

    /**
     * 向视图送变量
     * @param $name
     * @param $value
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


}