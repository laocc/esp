<?php

namespace esp\core;


class Error
{
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher, array &$option)
    {
        $this->dispatcher = $dispatcher;
        $this->register_handler($option);
    }

    /**
     * 简单处理出错信息
     */
    public function simple_register_handler()
    {
        set_error_handler(function (...$err) {
            header("Status: 400 Bad Request", true);
            echo("[{$err[0]}]{$err[1]}");
        });
        set_exception_handler(function (\Throwable $error) {
            header("Status: 400 Bad Request", true);
            echo("[{$error->getCode()}]{$error->getMessage()}");
        });
    }

    /**
     * @param $option
     * 显示程度:0=不显示,1=简单,2=完整
     */
    private function register_handler(array $option)
    {
        if (_DEBUG) $option = ['run' => 2, 'throw' => 2] + $option;

        /**
         * 一般警告错误
         * @param $errNo
         * @param $errStr
         * @param $errFile
         * @param $errLine
         */
        $handler_error = function (int $errNo, string $errStr, string $errFile, int $errLine, array $errcontext) use ($option) {
            $err = Array();
            $err['level'] = 'Error';
            $err['error'] = $errStr;
            $err['code'] = $errNo;
            $err['file'] = $errFile;
            $err['line'] = $errLine;
            foreach ($errcontext as $k => $item) {
                if (is_object($item)) $errcontext[$k] = '(OBJECT)';
            }
            $err['text'] = $errcontext;

            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], $option['filename']);
            if (is_int($option['run'])) {
                if ($option['run'] === 0) {
                    exit;
                } else if ($option['run'] === 1) {
                    unset($err['text']);
                    print_r($err);
                    exit;
                } else if ($option['run'] === 2) {
                    $this->displayError('Error', $err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                } else {
                    $this->displayState($option['run']);
                }
            } else {
                exit($option['run']);
            }
        };

        /**
         * 严重错误
         * @param $error
         */
        $handler_exception = function (\Throwable $error) use ($option) {
            $err = Array();
            $err['level'] = 'Throw';
            $err['error'] = $error->getMessage();
            $err['code'] = $error->getCode();
            $err['file'] = $error->getFile();
            $err['line'] = $error->getLine();
            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], $option['filename']);
            if (is_int($option['throw'])) {
                if ($option['throw'] === 0) {
                    exit;
                } else if ($option['throw'] === 1) {
                    print_r($err);
                    exit;
                } else if ($option['throw'] === 2) {
                    $this->displayError('Throw', $err, $error->getTrace());
                } else if ($option['throw'] === 3) {
                    if (!$err['code']) $err['code'] = $option['run'];
                    $this->displayState($err['code']);
                } else {
                    $this->displayState($option['throw']);
                }
            } else {
                exit($option['throw']);
            }
        };

        /**
         * 注册出错时的处理方法，等同于set_error_handler($handler_error)
         * 处理类型：
         * 1，框架自身出错；
         * 2，PHP原生错误比如：除以0，语法错误等；
         * 3，程序中error()抛出的错误；
         * 4，找不到控制器，找不到控制动作等；
         * 5，Mysql连接不上等；
         */
        set_error_handler($handler_error);

        /**
         * 注册【异常】处理方法，
         * 处理类型：
         * 1，调用了不存在的函数；
         * 2，函数参数不对；
         * 3，throw new \Exception('抛出的异常');
         */
        set_exception_handler($handler_exception);
    }


    /**
     * 仅记录错误，但不阻止程序继续运行
     * @param $error
     */
    private function error(array $error, array $prev = null, string $filename)
    {
        $info = Array();
        $info['time'] = date('Y-m-d H:i:s');
        $info['url'] = _HTTP_DOMAIN . _URI;
        $info['referer'] = getenv("HTTP_REFERER");
        $filename = _ROOT . "/cache/error/" . date($filename) . mt_rand() . '.txt';
        mk_dir($filename);
        if (!empty($this->dispatcher->_request)) {
            $request = $this->dispatcher->_request;
        } else {
            $request = null;
        }

        file_put_contents($filename, print_r([
            'info' => $info,
            'error' => $error,
            'prev' => $prev,
            'request' => $request,
            'server' => $_SERVER,
        ], true), LOCK_EX);
    }

    /**
     * 显示成一个错误状态
     * @param $code
     * @return string
     */
    public static function displayState(int $code)
    {
        $state = Config::states($code);
        if (_CLI) exit("[{$code}]:{$state}\n");
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? ucfirst($_SERVER['SERVER_SOFTWARE']) : null;
        $html = "<html>\n<head><title>{$code} {$state}</title></head>\n<body bgcolor=\"white\">\n<center><h1>{$code} {$state}</h1></center>\n<hr><center>{$server}</center>\n</body>\n</html>\n\n";
        if (!stripos(PHP_SAPI, 'cgi')) {
            header("Status: {$code} {$state}", true);
        } else {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            header("{$protocol} {$code} {$state}", true, $code);
        }
        header('Content-type: text/html', true);
        exit($html);
    }


    /**
     * 显示并停止所有操作
     * @param $type
     * @param $err
     * @param $trace
     */
    private function displayError(string $type, array $err, array $trace)
    {
        if (_CLI) {
            echo "\n\e[40;31;m================ERROR=====================\e[0m\n";
            print_r($err);
            if (!empty($trace)) print_r($trace);
            exit;
        }

        if (is_numeric($err['error'])) {
            $this->displayState(intval($err['error']));
        }

        $traceHtml = '';
        foreach (array_reverse($trace) as $tr) {
            $str = '<tr><td class="l">';
            if (isset($tr['file'])) $str .= $this->filter_root($tr['file']);
            if (isset($tr['line'])) $str .= " ({$tr['line']})";
            $str .= '</td><td>';

            if (isset($tr['class'])) $str .= $tr['class'];
            if (isset($tr['type'])) $str .= $tr['type'];
            if (isset($tr['function'])) {
                if (empty($tr['args'])) {
                    $args = null;
                } else {
                    foreach ($tr['args'] as $i => &$arr) {
                        if (is_array($arr)) $arr = json_encode($arr, 256);
                    }
                    $args = '"' . implode('","', $tr['args']) . '"';
                }
                $str .= "{$tr['function']}({$args})";
            }
            $str .= '</td></tr>';
            $traceHtml .= $str;
        }

        if (0) {
            $route = $this->get_routes_info();
            if (!empty($route)) {
                $Params = empty($route['Params']) ? '' : (implode(',', $route['Params']));
                $mca_name = "{$route['Router']} : {$route['Module']} / {$route['Control']} / {$route['Action']}";
                $mca_file = $route['Path'] . $route['ModulePath'] . $route['Control'] . '->' . $route['Action'] . '(' . $Params . ')';
                $routeHtml = '<tr><td class="l">路由结果：</td><td>' . $mca_name . '</td></tr><tr><td class="l">路由请求：</td><td>';
                $routeHtml .= $this->filter_root($mca_file) . '</td></tr>';
                $err['route_name'] = $mca_name;
                $err['route_mca'] = $mca_file;
            } else {
                $routeHtml = '';
            }
        } else {
            $routeHtml = '';
        }

        $errValue = [];
        $errValue['time'] = date('Y-m-d H:i:s');
        $errValue['title'] = $this->filter_root($err['error']);
        $errValue['code'] = "{$type}={$err['code']}";
        $errValue['file'] = "{$this->filter_root($err['file'])} ({$err['line']})";
        $errValue['info'] = $routeHtml;
        $errValue['trace'] = $traceHtml;

        ob_start();
        extract($errValue);
        include('view/error.php');
        $content = ob_get_contents();
        ob_end_clean();
        exit($content);
    }

    private function filter_root($str)
    {
        return str_replace(_ROOT, '', $str);
    }

}