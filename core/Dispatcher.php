<?php
declare(strict_types=1);

namespace esp\core;

final class Dispatcher
{
    public $_plugs = Array();
    private $_plugs_count = 0;
    public $_request;
    public $_response;
    public $_debug;
    public $_cache;
    private $run = true;

    public function __construct(array $option, string $module = 'www')
    {
//        try {
        if (!defined('_ROOT')) exit("网站入口处须定义 _ROOT 项，指向系统根目录");
        define('_DAY_TIME', strtotime(date('Ymd')));//今天零时整的时间戳
        define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
        define('_DEBUG', is_file(_RUNTIME . '/debug.lock'));
        if (!defined('_MODULE')) define('_MODULE', $module);
        if (_CLI) {
            define('_URI', ('/' . trim(implode('/', array_slice($GLOBALS['argv'], 1)), '/')));
        } else {
            define('_URI', parse_url(getenv('REQUEST_URI'), PHP_URL_PATH));
            if (_URI === '/favicon.ico') exit;
        }
        if (isset($option['callback'])) $option['callback']($option);
        $option += ['error' => [], 'config' => []];
        //以下三项必须在`chdir()`之前，且三项顺序不可变
        if (!_CLI) $err = new Error($this, $option['error']);
        Config::_init($option['config']);
        chdir(_ROOT);

        $this->_request = new Request(Config::get('frame.request'));
        if (_CLI) return;
        $this->_response = new Response($this->_request);

        if ($debug = Config::get('debug')) {
            $this->_debug = new Debug($this->_request, $this->_response, $debug);
            $GLOBALS['_Debug'] = &$this->_debug;
        }
        if ($session = Config::get('session')) Session::_init($session);

        if ($cache = Config::get('cache')) {
            $setting = $cache['setting'];
            if (isset($cache[_MODULE])) $setting = $cache[_MODULE] + $setting;
            if (isset($setting['run']) and $setting['run']) {
                $this->_cache = new Cache($this, $setting);
            }
        }

        if (isset($option['attack'])) $option['attack']($option);
        unset($GLOBALS['option']);

//        } catch (\Exception $exception) {
//            pre($exception);
//        }
    }


    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->_request;
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->_response;
    }


    /**
     * @param string $class '\library\Bootstrap'
     * @return Dispatcher
     * @throws \Exception
     */
    public function bootstrap(string $class): Dispatcher
    {
        if (!class_exists($class)) {
            throw new \Exception("Bootstrap类不存在，请检查{$class}.php文件", 404);
        }
        $boot = new $class();
        foreach (get_class_methods($boot) as $method) {
            if (substr($method, 0, 5) === '_init') {
                $run = call_user_func_array([$boot, $method], [$this]);
                if ($run === false) $this->run = false;
            }
        }
        return $this;
    }

    /**
     * 接受注册插件
     * @param Plugin $class
     * @return bool
     * @throws \Exception
     */
    public function setPlugin(Plugin $class): bool
    {
        $name = get_class($class);
        $name = ucfirst(substr($name, strrpos($name, '\\') + 1));
        if (isset($this->_plugs[$name])) {
            throw new \Exception("插件名{$name}已被注册过", 404);
        }
        $this->_plugs[$name] = $class;
        $this->_plugs_count++;
        return true;
    }

    /**
     * 执行HOOK
     * @param $time
     */
    private function plugsHook(string $time): void
    {
        if (empty($this->_plugs)) return;
        if (!in_array($time, ['routeBefore', 'routeAfter', 'dispatchBefore', 'dispatchAfter', 'displayBefore', 'displayAfter', 'mainEnd'])) return;
        foreach ($this->_plugs as $plug) {
            if (method_exists($plug, $time)) {
                call_user_func_array([$plug, $time], [$this->_request, $this->_response]);
            }
        }
    }

    /**
     * 系统运行调度中心
     * @throws \Exception
     */
    public function run(callable $callable = null): void
    {
        $testDebug = [];
        if ($callable and call_user_func($callable)) return;

        if ($this->run === false) return;

        $this->_plugs_count and $this->plugsHook('routeBefore');
        (new Route($this->_request));
        $this->_plugs_count and $this->plugsHook('routeAfter');

        if (!_CLI and !is_null($this->_cache)) if ($this->_cache->Display()) goto end;

        $this->_plugs_count and $this->plugsHook('dispatchBefore');
        $value = $this->dispatch();//运行控制器->方法
        $this->_plugs_count and $this->plugsHook('dispatchAfter');
        $this->_plugs_count and $this->plugsHook('displayBefore');
        if (_CLI) {
            print_r($value);
        } else {
            $this->_response->display($value);
        }
        $this->_plugs_count and $this->plugsHook('displayAfter');

        if (!_CLI) fastcgi_finish_request(); //运行结束，客户端断开
        if (!_CLI and !is_null($this->_cache)) $this->_cache->Save();

        end:
        $this->_plugs_count and $this->plugsHook('mainEnd');

        if (!is_null($this->_debug)) {
            register_shutdown_function(function () {
                $save = $this->_debug->save_logs();

                if (getenv('HTTP_MOBILE')) {
                    $testDebug['ua'] = getenv('HTTP_USER_AGENT');
                    $testDebug['file'] = $this->_debug->filename();
                    $testDebug['save'] = $save;
                    file_put_contents(_RUNTIME . '/debug/test/' . date('YmdHis_') . mt_rand() . '.txt', json_encode($testDebug, 64 | 128 | 256), LOCK_EX);
                }

            });
        }

    }


    /**
     * 路由结果分发至控制器动作
     * @return mixed
     * @throws \Exception
     */
    private function dispatch()
    {
        $suffix = &$this->_request->suffix;
        $actionExt = $suffix['auto'];
        $isPost = $this->_request->isPost();
        $isAjax = $this->_request->isAjax();
        if ($this->_request->isGet() and ($p = $suffix['get'])) $actionExt = $p;
        elseif ($isAjax and ($p = $suffix['ajax'])) $actionExt = $p;
        elseif ($isPost and ($p = $suffix['post'])) $actionExt = $p;
        elseif (_CLI and ($p = $suffix['auto'])) $actionExt = $p;
        else {
//            print_r($suffix);
//            var_dump([$this->_request->isGet(), $this->_request->isCli(), $isAjax, $isPost]);
            return 'Unknown method=' . $this->_request->method;
        }

        LOOP:
        $module = strtolower($this->_request->module);
        $controller = ucfirst($this->_request->controller);
        $action = strtolower($this->_request->action) . ucfirst($actionExt);
        $auto = strtolower($this->_request->action) . 'Action';

        if ($controller === 'Base') throw new \Exception('控制器名不可以为base，这是系统保留公共控制器名', 404);

        /**
         * 加载控制器，也可以在composer中预加载
         * "admin\\": "application/admin/controllers/",
         */
        $base = $this->_request->directory . "/{$module}/controllers/Base.php";
        $file = $this->_request->directory . "/{$module}/controllers/{$controller}.php";
        if (is_readable($base)) load($base);//加载控制器公共类，有可能不存在
        if (!load($file)) {
            return $this->err404("[/{$module}/controllers/{$controller}.php] not exists.");
        }

        $controller = '\\' . $module . '\\' . $controller . 'Controller';
        if (!class_exists($controller)) {
            return $this->err404("[{$controller}] not exists.");
        }
        $cont = new $controller($this);
        if (!$cont instanceof Controller) {
            throw new \Exception("{$controller} 须继承自 \\esp\\core\\Controller", 404);
        }

        if (!method_exists($cont, $action) or !is_callable([$cont, $action])) {
            if (method_exists($cont, $auto) and is_callable([$cont, $auto])) {
                $action = $auto;
            } else {
                return $this->err404("[{$controller}::{$action}()] not exists.");
            }
        }

        $GLOBALS['_Controller'] = &$cont;//放入公共变量，后面Model需要读取

        //运行初始化方法
        if (method_exists($cont, '_init') and is_callable([$cont, '_init'])) {
            if (!is_null($this->_debug)) $this->_debug->relay("[blue;{$controller}->_nit() ============================]", []);
            $val = call_user_func_array([$cont, '_init'], [$action]);
            if (!is_null($val)) return $val;
        }

        if (method_exists($cont, '_main') and is_callable([$cont, '_main'])) {
            if (!is_null($this->_debug)) $this->_debug->relay("[blue;{$controller}->_main() =============================]", []);
            $val = call_user_func_array([$cont, '_main'], [$action]);
            if (!is_null($val)) return $val;
        }

        /**
         * 正式请求到控制器
         */
        if (!is_null($this->_debug)) $this->_debug->relay("[green;{$controller}->{$action} Star ==============================]", []);
        $val = call_user_func_array([$cont, $action], $this->_request->params);
        if (!is_null($this->_debug)) $this->_debug->relay("[red;{$controller}->{$action} End ==============================]", []);
        if ($this->_request->loop === true) {
            $this->_request->loop = false;
            goto LOOP;
        }

        //运行结束方法
        if (method_exists($cont, '_close') and is_callable([$cont, '_close'])) {
            $clo = call_user_func_array([$cont, '_close'], [$action, $val]);
            if (!is_null($clo)) $val = $clo;// and is_null($val)
            if (!is_null($this->_debug)) $this->_debug->relay("[red;{$controller}->_close() ==================================]", []);
        }

        if ($isPost or $isAjax) {
            $rest = $cont->result ?? [];
            $val = $this->_close($rest, $val);
        }

        unset($cont, $GLOBALS['_Controller']);
        return $val;
    }

    final private function _close(array $result, $return)
    {
        //所有数组
        if (is_array($return)) return $return + $result + ['success' => 1, 'message' => 'OK'];

        //指定了$result
        if (!empty($result)) return $result + ['success' => 1, 'message' => $return ?: 'OK'];

        if (is_string($return)) {
            if (substr($return, 0, 4) === 'err:') return ['success' => 0, 'message' => substr($return, 4)];
            if (substr($return, 0, 6) === 'error:') return ['success' => 0, 'message' => substr($return, 6)];

            if (_MODULE !== 'api') $this->_debug->error($return);
        }

        //其他情况原样返回
        return $return;
    }

    final private function err404(string $msg)
    {
        $this->_debug->folder('error');
        if (_DEBUG) return $msg;
        $empty = Config::get('frame.request.empty');
        if (!empty($empty)) return $empty;
        return $msg;
    }

}