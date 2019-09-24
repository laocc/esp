<?php

namespace esp\core;


class Error
{
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher, array $option)
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
        $default = ['run' => 1, 'throw' => 1, 'filename' => 'YmdHis', 'path' => _RUNTIME . "/error"];
        if (_CLI) {
            $default['run'] = 1;
            $default['throw'] = 1;
        } else if (_DEBUG) {
//            $default['run'] = 2;
//            $default['throw'] = 2;
        }
        //ajax方式下，都只显示简单信息
        if (strtolower(getenv('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
            $default['run'] = 1;
            $default['throw'] = 1;
        }

        $option = $default + $option;
        /**
         * 一般警告错误
         * @param $errNo
         * @param $errStr
         * @param $errFile
         * @param $errLine
         */
        $handler_error = function (int $errNo, string $errStr, string $errFile, int $errLine, array $errcontext = null) use ($option) {
            Session::reset();

            $err = Array();
            $err['level'] = 'Error';
            $err['error'] = $errStr;
            $err['code'] = $errNo;
            $err['file'] = $errFile;
            $err['line'] = $errLine;
            if (is_null($errcontext)) $errcontext = [];
            foreach ($errcontext as $k => $item) {
                if (is_object($item)) $errcontext[$k] = '(OBJECT)';
                unset($errcontext['all']);
            }
            if (!_CLI) $err['text'] = $errcontext;
            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], $option['path'], $option['filename']);
            if (is_int($option['run'])) {
                if ($option['run'] === 0) {
                    exit;
                } else if ($option['run'] === 1) {
                    unset($err['text']);
                    pre($err);
                    exit;
                } else if ($option['run'] === 2) {
                    $this->displayError('Error', $err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                } else {
                    echo $this->displayState($option['run']);
                    exit;
                }
            } else {
                echo($option['run']);
                exit();
            }
        };

        /**
         * 严重错误
         * @param $error
         */
        $handler_exception = function (\Throwable $error) use ($option) {
            Session::reset();

            $err = Array();
            $err['level'] = 'Throw';
            $err['error'] = $error->getMessage();
            $err['code'] = $error->getCode();
            $err['file'] = $error->getFile();
            $err['line'] = $error->getLine();
            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], $option['path'], $option['filename']);
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
                    echo $this->displayState($err['code']);
                    exit;
                } else {
                    echo $this->displayState($option['throw']);
                    exit;
                }
            } else {
                echo($option['throw']);
                exit();
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
        $seh = set_exception_handler($handler_exception);
//        var_export($seh);
    }

    public static function exception(\Exception $exception)
    {
        $err = Array();
        $err['level'] = 'Exception';
        $err['error'] = $exception->getMessage();
        $err['code'] = $exception->getCode();
        $err['file'] = $exception->getFile();
        $err['line'] = $exception->getLine();
//        pre($err);

        echo "<pre style='background:#fff;display:block;'>";
        if (_DEBUG) {
            print_r($exception);
        } else {
            print_r($err);
        }
        echo "</pre>";
    }

    /**
     * 仅记录错误，但不阻止程序继续运行
     * @param $error
     */
    private function error(array $error, array $prev, string $path, string $filename)
    {
        $debug = Debug::class();
        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Debug' => !is_null($debug) ? $debug->filename() : '',
            'Error' => $error,
            'Server' => $_SERVER,
            'Post' => file_get_contents("php://input"),
        ];
        if (strlen($info['Post']) > 1000) $info['Post'] = substr($info['Post'], 0, 1000);

        $filename = $path . "/" . date($filename) . mt_rand() . '.md';
        if (defined('_RPC') and RPC::post('/debug', ['filename' => $filename, 'data' => json_encode($info, 256 | 128 | 64)])) return;

        if (!is_dir($path)) mkdir($path, 0740, true);
        if (is_readable($path)) file_put_contents($filename, json_encode($info, 64 | 128 | 256), LOCK_EX);
        if (1) return;

        $info = Array();
        $info['time'] = date('Y-m-d H:i:s');
        $info['url'] = _URI;
        $info['referer'] = getenv("HTTP_REFERER");
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
        if (_CLI) return "[{$code}]:{$state}\n";

        $server = isset($_SERVER['SERVER_SOFTWARE']) ? ucfirst($_SERVER['SERVER_SOFTWARE']) : null;
        $html = "<html>\n<head><title>{$code} {$state}</title></head>\n<body bgcolor=\"white\">\n<center><h1>{$code} {$state}</h1></center>\n<hr><center>{$server}</center>\n</body>\n</html>\n\n";
        if (!stripos(PHP_SAPI, 'cgi')) {
            header("Status: {$code} {$state}", true);
        } else {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            header("{$protocol} {$code} {$state}", true, $code);
        }
        header('Content-type: text/html', true);
        return $html;
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
            echo $this->displayState(intval($err['error']));
            exit;
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
                    $args = '"……"';
//                    $args = '"' . implode('","', $tr['args']) . '"';
                }
                $str .= "{$tr['function']}({$args})";
            }
            $str .= '</td></tr>';
            $traceHtml .= $str;
        }

        $errValue = [];
        $errValue['time'] = date('Y-m-d H:i:s');
        $errValue['title'] = $this->filter_root($err['error']);
        $errValue['code'] = "{$type}={$err['code']}";
        $errValue['file'] = "{$this->filter_root($err['file'])} ({$err['line']})";
        $errValue['trace'] = $traceHtml;

        ob_start();
        extract($errValue);
        include('view/error.php');
        $content = ob_get_contents();
        ob_end_clean();
        echo $content;
        exit();
    }

    private function filter_root($str)
    {
        return str_replace(_ROOT, '', $str);
    }

}