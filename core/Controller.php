<?php
namespace wbf\core;


abstract class Controller
{
    private $_request;
    private $_response;
    private $_plugs;

    private $_use_view;
    private $_use_layout;

    private $_models = [];

    final public function __construct(array &$plugs, Request &$request, Response &$response)
    {
        $this->_request = $request;
        $this->_response = $response;
        $this->_plugs = $plugs;
        $response->control($this);
    }

    /**
     * 加载模型
     * 关于参数：在实际模型类中，建议用func_get_args()获取参数列表，也可以直接指定参数
     * @param $model
     * @param null $params
     * @return mixed
     *
     */
    final protected function &model(...$paras)
    {
        if (empty($paras)) return null;
        if (isset($this->_models[$paras[0]])) return $this->_models[$paras[0]];

        $from = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $dir = dirname(dirname($from['file'])) . '/models/';
        $model = ucfirst(strtolower($paras[0]));
        $class = $model . Config::get('wbf.modelExt');
        $file = "{$dir}{$model}.php";

        if (!is_readable($file)) error("Model File {$file} is not exists");
        include $file;
        if (!class_exists($class)) error("Model class {$class} is not found");

        $this->_models[$model] = new $class(...array_slice($paras, 1));
        if (!$this->_models[$model] instanceof Model) {
            exit("{$class} 须继承自 wbf\\core\\Model");
        }
        return $this->_models[$model];
    }


    /**
     * 设置视图文件，或获取对象
     * @return View|bool
     */
    final public function view($file = null)
    {
        if ($file === false) {
            return $this->_use_view = $file;
        }
        static $obj;
        if (!is_null($obj)) return $obj;
        $dir = $this->_request->directory . $this->_request->module . '/views/';
        $this->_use_view = true;
        return $obj = new View($dir, $file);
    }

    /**
     * 标签解析器
     * @param null $bool
     * @return bool|View
     */
    final protected function adapter($bool = null)
    {
        return $this->view()->adapter($bool);
    }

    /**
     * 关闭，或获取layout对象，可同时指定框架文件
     * @param null $file
     * @return bool|View
     */
    final protected function layout($layout_file = null)
    {
        if ($layout_file === false) {
            return $this->_use_layout = false;
        }
        static $obj;
        if (!is_null($obj)) return $obj;
        $layout = Config::get('layout.filename');
        $dir = $this->_request->directory . $this->_request->module . '/views/';
        $layout_file = $layout_file ?: $this->_request->controller . '/' . $layout;
        if (stripos($layout_file, $dir) !== 0) $layout_file = $dir . ltrim($layout_file, '/');
        if (!is_readable($layout_file)) $layout_file = $dir . $layout;
        if (!is_readable($layout_file)) error('框架视图文件不存在');
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
    final protected function check_host(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host($this->_request->referer), array_merge([_HOST], $host))) error(Config::get('error.host'));
    }

    /**
     * @return Request
     */
    final protected function &getRequest()
    {
        return $this->_request;
    }

    final protected function &getResponse()
    {
        return $this->_response;
    }

    /**
     * @param $name
     */
    final protected function &getPlugin($name)
    {
        return isset($this->_plugs[$name]) ? $this->_plugs[$name] : null;
    }

    /**
     * @return array
     */
    final public function check_object()
    {
        if (is_null($this->_use_view)) $this->_use_view = Config::get('view.autoRun');
        if (is_null($this->_use_layout)) $this->_use_layout = Config::get('layout.autoRun');

        return [
            $this->_use_view ? $this->view() : null,
            $this->_use_layout ? $this->layout() : null,
        ];
    }

    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    final protected function assign($name, $value = null)
    {
        $this->_response->assign($name, $value);
    }

    final public function __set($name, $value)
    {
        $this->_response->assign($name, $value);
    }

    final public function __get($name)
    {
        return $this->_response->get($name);
    }

    final protected function set($name, $value = null)
    {
        $this->_response->assign($name, $value);
    }

    final protected function get($name)
    {
        return $this->_response->get($name);
    }

    final protected function html($value = null)
    {
        $this->_response->set_value('html', $value);
    }

    final protected function json(array $value)
    {
        $this->_response->set_value('json', $value);
    }

    final protected function text($value)
    {
        $this->_response->set_value('text', $value);
    }

    final protected function xml($root, array $value = null)
    {
        if (is_array($root)) list($root, $value) = ['xml', $root];
        $this->_response->set_value('xml', [$root, $value]);
    }


    final protected function js($file, $pos = 'foot')
    {
        $this->_response->js($file, $pos);
        return $this;
    }


    final protected function css($file)
    {
        $this->_response->css($file);
        return $this;
    }


    final protected function meta($name, $value)
    {
        $this->_response->meta($name, $value);
        return $this;
    }


    final protected function title($title, $default = true)
    {
        $this->_response->title($title, $default);
        return $this;
    }


    final protected function keywords($value)
    {
        $this->_response->keywords($value);
        return $this;
    }


    final protected function description($value)
    {
        $this->_response->description($value);
        return $this;
    }


}