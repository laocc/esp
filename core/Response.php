<?php
namespace esp\core;


class Response
{
    private $_display_type;
    private $_display_value = [];
    private $_control;
    private $_request;
    private $_shutdown = true;


    private $_view_val = [];
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


    public function __construct(Request &$request)
    {
        $this->_request = $request;
    }

    public function set_value($name, $value)
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

            case 'text':
                $this->_display_type = 'text';
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
                error("不接受{$name}类型的值");
        }
    }

    /**
     * @return Controller|bool
     */
    public function control($obj = null)
    {
        if (is_null($obj)) return $this->_control;
        $this->_control = $obj;
        return true;
    }

    /**
     * 应该只有 Kernel::run();调用
     */
    public function display()
    {
        if (_DEBUG) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $trace = pathinfo($trace['file']);
            if (!($trace['dirname'] === __DIR__ and $trace['filename'] === 'Kernel'))
                error('Response::display()方法不可直接调用');
        }

        switch (strtolower($this->_display_type)) {
            case 'json':
                header('Content-type:application/json', true);
                $value = json_encode($this->_display_value, 256);
                if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                    $value = "{$match[1]}({$value});";
                }
                echo $value;
                break;

            case 'html':
                header('Content-type:text/html', true);
                print_r($this->_display_value);
                break;

            case 'text':
                header('Content-type:text/plain', true);
                print_r($this->_display_value);
                break;

            case 'xml':
                header('Content-type:text/xml', true);
                echo (new \esp\library\Xml($this->_display_value[1], $this->_display_value[0]))->render();
                break;

            default:
                echo $this->display_response();
        }
    }


    /**
     * 最后显示内容
     * 只能被Kernel中触发
     */
    private function display_response()
    {
        list($view, $layout) = $this->control()->check_object();
        if (!$view) return null;
        $view instanceof View and true;
        $this->cleared_layout_val();

        if ($layout) {
            $layout instanceof View and true;
            $layout->assign($this->_layout_val);//送入layout变量
            $view->layout($layout);//为视图注册layout
        } else {
            $view->assign($this->_layout_val);//无layout，将这些变量送入子视图
        }
        $file = $this->_request->controller . '/' . $this->_request->action . '.' . ltrim(Config::get('view.ext'), '.');
        $this->_layout_val = null;
        return $view->render($file, $this->_view_val);
    }

    /**
     * 整理layout中的变量
     */
    private function cleared_layout_val()
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


    public function registerAdapter(&$adapter)
    {
        $this->control()->view()->registerAdapter($adapter);
    }

    public function getAdapter()
    {
        return $this->control()->view()->adapter();
    }

    public function getType()
    {
        return $this->_display_type;
    }

    /**
     * @param null $run
     * @return bool
     */
    public function shutdown($run = null)
    {
        if (is_bool($run)) return $this->_shutdown = $run;
        return $this->_shutdown;
    }

    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->_view_val[$k] = $v;
            }
        } else {
            $this->_view_val[$name] = $value;
        }
    }

    public function get($name)
    {
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
    }

    public function set($name, $value)
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


    public function meta($name, $value)
    {
        $this->_layout_val['_meta'][$name] = $value;
        return $this;
    }


    public function title($title, $default = true)
    {
        $this->_layout_val['_title'] = $title;
        if (!$default) $this->_layout_val['_title_default'] = false;
        return $this;
    }


    public function keywords($value)
    {
        $this->_layout_val['_meta']['keywords'] = $value;
        return $this;
    }


    public function description($value)
    {
        $this->_layout_val['_meta']['description'] = $value;
        return $this;
    }


}