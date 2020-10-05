<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\ext\EspError;

final class Error
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
            header("Status: 500 Internal Server Error", true);
            echo("[{$err[0]}]{$err[1]}");
        });
        set_exception_handler(function (EspError $error) {
            header("Status: 500 Internal Server Error", true);
            echo("[{$error->getCode()}]{$error->getMessage()}");
        });
    }

    /**
     * @param $option
     * 显示程度:0=不显示,1=简单,2=完整
     */
    private function register_handler(array $option)
    {
        $option += ['display' => 'json', 'filename' => 'YmdHis', 'path' => _RUNTIME . "/error"];

        /**
         * 一般警告错误
         * @param int $errNo
         * @param string $errStr
         * @param string $errFile
         * @param int $errLine
         * @param array|null $context
         */
        $handler_error = function (int $errNo, string $errStr, string $errFile, int $errLine, array $context = null)
        use ($option) {
            //($message = "", $code = 0, $severity = 1, $filename = __FILE__, $lineno = __LINE__, $previous)
            throw new EspError($errStr, $errNo, 1, $errFile, $errLine);
        };

        /**
         * 严重错误
         * @param $error
         */
        $handler_exception = function (\Error $error) use ($option) {
//            Session::reset();
            $err = Array();
            $err['code'] = $error->getCode();
            $err['message'] = $error->getMessage();
            $err['file'] = $error->getFile() . '(' . $error->getLine() . ')';
            $err['trace'] = $error->getTrace();

            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], $option['path'], $option['filename']);

            switch (true) {
                case _CLI:
                    echo json_encode($err, 256 | 128 | 64);
                    break;

                case is_int($option['display']):
                    if ($option['display'] === 0) {
                        $this->displayState($error->getCode());
                    } else {
                        $this->displayState($option['display']);
                    }
                    break;

                case ($option['display'] === 'json'):
                    //ajax方式下，都只显示简单信息
                    if (strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') === 'xmlhttprequest' or
                        strtolower(getenv('REQUEST_METHOD') ?: '') === 'post') {
                        echo json_encode($err, 256 | 128 | 64);
                    } else {
                        echo '<pre>' . json_encode($err, 256 | 128 | 64) . '</pre>';
                    }
                    break;

                case ($option['display'] === 'html'):
                    $this->displayError($error);
                    break;

                default:
                    echo $option['display'];
            }

            fastcgi_finish_request();
            exit;
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
         * 3，throw new EspError('抛出的异常');
         */
        set_exception_handler($handler_exception);
    }

    public static function exception(EspError $exception)
    {
        echo "<pre style='background:#fff;display:block;'>";
        if (_DEBUG) {
            print_r($exception->debug());
        } else {
            print_r($exception->display());
        }
        echo "</pre>";
    }

    /**
     * 仅记录错误，但不阻止程序继续运行
     * @param array $error
     * @param array $prev
     * @param string $path
     * @param string $filename
     */
    private function error(array $error, array $prev, string $path, string $filename)
    {
        $debug = Debug::class();
        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('HTTP_HOST'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Debug' => !is_null($debug) ? $debug->filename() : '',
            'Error' => $error,
            'Server' => $_SERVER,
            'Post' => file_get_contents("php://input"),
            'prev' => $prev
        ];
        if (strlen($info['Post']) > 1000) $info['Post'] = substr($info['Post'], 0, 1000);
        $filename = $path . "/" . date($filename) . mt_rand() . '.md';

        if (!is_null($debug)) {
            register_shutdown_function(function (Debug $debug, $filename, $info) {
                $err = $info['Error'];
                if ($err['trace'] ?? []) {
                    foreach ($err['trace'] as $i => $ii) {
                        if ($i > 1) unset($err['trace'][$i]);
                    }
                }
                $debug->relay($err);
                $sl = $debug->save_logs('Error Saved');
                $info['save_logs'] = $sl;
                if ($debug->save_file($filename, json_encode($info, 256 | 128 | 64))) return;
            }, $debug, $filename, $info);
            if (1) return;
//            if ($debug->save_file($filename, json_encode($info, 256 | 128 | 64))) return;
        }

        if (!is_dir($path)) mkdir($path, 0740, true);
        if (is_readable($path)) file_put_contents($filename, json_encode($info, 64 | 128 | 256), LOCK_EX);
    }

    /**
     * 显示成一个错误状态
     * @param $code
     * @param $writeHeader
     * @return string
     */
    public static function displayState(int $code, bool $writeHeader = true): string
    {
        $conf = parse_ini_file(_ESP_ROOT . '/common/static/state.ini', true);
        $state = $conf[$code] ?? 'OK';
        if (_CLI) return "[{$code}]:{$state}\n";
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? ucfirst($_SERVER['SERVER_SOFTWARE']) : null;
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-cn">
    <head>
        <meta charset="UTF-8">
        <title>{$code} {$state}</title>
        <meta name="viewport" content="width=device-width,user-scalable=no,initial-scale=1,maximum-scale=1,minimum-scale=1">
    </head>
    <body bgcolor="white">
        <center><h1>{$code} {$state}</h1></center>
        <hr>
        <center>{$server}</center>
    </body>
</html>
HTML;
        if ($writeHeader) {
            http_response_code($code);
            if (!stripos(PHP_SAPI, 'cgi')) {
                header("Status: {$code} {$state}", true);
            } else {
                $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
                header("{$protocol} {$code} {$state}", true, $code);
            }
            header('Content-type: text/html', true);
        }
        return $html;
    }


    /**
     * 显示并停止所有操作
     * @param $error
     */
    private function displayError(\Error $error)
    {
        $err = Array();
        $err['code'] = $error->getCode();
        $err['message'] = $error->getMessage();
        $err['file'] = $error->getFile() . '(' . $error->getLine() . ')';
        $err['trace'] = $error->getTrace();
        if (_CLI) {
            echo "\n\e[40;31;m================ERROR=====================\e[0m\n";
            print_r($err);
            exit;
        }

        $trace = $error->getTrace();
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
                        if (is_array($arr)) $arr = json_encode($arr, 256 | 64);
                    }
//                    $args = '"……"';
                    $args = '"' . implode('", "', $tr['args']) . '"';
                }
                $str .= "{$tr['function']}(<span style='color:#d00;'>{$args}</span>)";
            }
            $str .= "</td></tr>\n";
            $traceHtml .= $str;
        }

        $errValue = [];
        $errValue['time'] = date('Y-m-d H:i:s');
        $errValue['title'] = $error->getMessage();
        $errValue['code'] = $error->getCode();
        $errValue['file'] = $err['file'];
        $errValue['trace'] = $traceHtml;

        ob_start();
        extract($errValue);
        include('view/error.php');
        $content = ob_get_contents();
        ob_end_clean();
        echo $content;
        exit;
    }

    private function filter_root($str)
    {
        return str_replace(_ROOT, '', $str);
    }

}