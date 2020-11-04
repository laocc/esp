<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\ext\EspError;
use function esp\helper\replace_array;

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
        /**
         * 这里的path是在Debug没有生成之前发生错误，所保存的位置
         * 如果是在Debug生成之后产生的错误，保存在debug.ini中指定的位置
         */
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
            $error = new EspError($errStr, $errNo, 1, $errFile, $errLine);

            $err = Array();
            $err['success'] = 0;
            $err['time'] = date('Y-m-d H:i:s');
            $err['error'] = $errNo;
            $err['message'] = $errStr;
            $err['file'] = $errFile . '(' . $errLine . ')';
            $err['trace'] = $error->getTrace();
            $err['context'] = print_r($context, true);

            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], $option['path'], $option['filename']);
            $ajax = (strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') === 'xmlhttprequest');
            $post = (strtolower(getenv('REQUEST_METHOD') ?: '') === 'post');

            switch (true) {
                case _CLI:
                    echo "\n\e[40;31;m================ERROR=====================\e[0m\n";
                    print_r($error);
                    break;

                case is_int($option['display']):
                    if ($option['display'] === 0) {
                        echo $this->displayState($error->getCode());
                    } else {
                        echo $this->displayState($option['display']);
                    }
                    break;

                case ($ajax or $post):
                    $this->displayState(500, true);
                    unset($err['trace'], $err['context']);
                    echo json_encode($err, 256 | 128 | 64);
                    break;

                case ($option['display'] === 'json'):
                    echo '<pre>' . json_encode($err, 256 | 128 | 64) . '</pre>';
                    break;

                case ($option['display'] === 'html'):
                    $this->displayError($err);
                    break;

                default:
                    echo $option['display'];
            }

            fastcgi_finish_request();
            exit;
            /**
             * 这里必须要结束，以阻止程序继续执行，
             * 同时也是切断Dispatcher中shutdown中保存Debug，
             * 由本类->error执行保存
             * 否则shutdown内的异常将无法被记录
             */
        };

        /**
         * 严重错误
         * @param $error
         */
        $handler_exception = function (\Throwable $error) use ($option) {
//            Session::reset();
            $err = Array();
            $err['success'] = 0;
            $err['time'] = date('Y-m-d H:i:s');
            $err['error'] = $error->getCode();
            $err['message'] = $error->getMessage();
            $err['file'] = $error->getFile() . '(' . $error->getLine() . ')';
            $err['trace'] = $error->getTrace();

            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], $option['path'], $option['filename']);

            $ajax = (strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') === 'xmlhttprequest');
            $post = (strtolower(getenv('REQUEST_METHOD') ?: '') === 'post');
            switch (true) {
                case _CLI:
                    echo "\n\e[40;31;m================ERROR=====================\e[0m\n";
                    print_r($error);
                    break;

                case is_int($option['display']):
                    if ($option['display'] === 0) {
                        echo $this->displayState($error->getCode());
                    } else {
                        echo $this->displayState($option['display']);
                    }
                    break;

                case ($ajax or $post):
                    $this->displayState(500, true);
                    unset($err['trace'], $err['context']);
                    echo json_encode($err, 256 | 128 | 64);
                    break;

                case ($option['display'] === 'json'):
                    echo '<pre>' . json_encode($err, 256 | 128 | 64) . '</pre>';
                    break;

                case ($option['display'] === 'html'):
                    $this->displayError($err);
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
        if ($error['trace'] ?? []) {
            foreach ($error['trace'] as $i => &$trace) {
                if ($i > 1) unset($error['trace'][$i]);
                if (!isset($trace['args']) or !is_array($trace['args'])) continue;
                foreach ($trace['args'] as $lin => &$pam) {
                    if (is_resource($pam)) $pam = print_r($pam, true);
                }
            }
        }

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
        if (strlen($info['Post']) > 10000) $info['Post'] = substr($info['Post'], 0, 10000);
        $filename = date($filename) . mt_rand() . '.md';

        if (!is_null($debug)) {
            //这里不能再继续加shutdown，因为有可能运行到这里已经处于shutdown内
            $debug->relay($info['Error']);
            $sl = $debug->save_logs('by Error Saved');
            $info['debugLogSaveRest:'] = $sl;
            if ($debug->save_file(ltrim($filename, '/'), json_encode($info, 256 | 128 | 64))) return;
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
    private function displayError(array $error)
    {
        $traceHtml = '';
        foreach (array_reverse($error['trace']) as $tr) {
            $str = '<tr><td class="l">';
            if (isset($tr['file'])) $str .= $this->filter_root($tr['file']);
            if (isset($tr['line'])) $str .= "({$tr['line']})";
            $str .= '</td><td>';

            if (isset($tr['class'])) $str .= $tr['class'];
            if (isset($tr['type'])) $str .= $tr['type'];
            if (isset($tr['function'])) {
                if (empty($tr['args'])) {
                    $args = null;
                } else {
                    foreach ($tr['args'] as $i => &$arr) {
                        if (is_resource($arr)) $arr = print_r($arr, true);
                        else if (is_array($arr)) $arr = json_encode($arr, 256 | 64);
                    }
                    $args = '"……"';
//                    $args = '"' . implode('", "', $tr['args']) . '"';
                }
                $str .= "{$tr['function']}(<span style='color:#d00;'>{$args}</span>)";
            }
            $str .= "</td></tr>\n";
            $traceHtml .= $str;
        }

        $error['trace'] = $traceHtml;

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=0"/>
    <meta name="format-detection" content="telephone=no"/>
    <title>{message}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-size: 1em;
            color: #555555;
            font-family: "Source Code Pro", "Arial", "Microsoft YaHei", "msyh", "sans-serif";
        }

        table {
            width: 80%;
            margin: 1em auto;
            border: 1px solid #456;
            box-shadow: 5px 5px 2px #ccc;
            border-radius: 4px;
        }

        tr, td {
            overflow: hidden;
        }

        td {
            text-indent: 0.5em;
            line-height: 2em;
        }

        table.head {
            background: #def;
        }

        table.head td.l {
            width: 6em;
            font-weight: bold;
        }

        td.msg {
            color: red;
        }

        table.trade tr:nth-child(odd) {
            background: #ffe;
        }

        table.trade tr.nav {
            background: #f0c040;
        }

        td.time {
            text-align: right;
            padding-right: 1em;
        }

        table.trade td {
            border-bottom: 1px solid #abc;
        }

        table.trade td.l {
            width: 40%;
        }

    </style>
</head>
<body>
<table class="head" cellpadding="0" cellspacing="0">
<tr><td class="l">错误代码：</td><td>{error}</td></tr>
<tr><td class="l">错误信息：</td><td class="msg">{message}</td></tr>
<tr><td class="l">错误位置：</td><td>{file}</td></tr>
<tr><td class="l">触发时间：</td><td>{time}</td></tr>
</table>
<table class="trade" cellpadding="0" cellspacing="0">
    <tr class="nav">
        <td><b>Trace</b> : (执行顺序从上往下)</td>
        <td class="time">{time}</td>
    </tr>
    {trace}
    <tr class="nav">
    <td colspan="2">上下文</td>
    </tr>
    <tr>
    <td colspan="2"><pre style="line-height:1em;">{context}</pre></td>
    </tr>
</table>
</body>
</html>
HTML;
        echo replace_array($html, $error);
    }

    private function filter_root($str)
    {
        return str_replace(_ROOT, '', $str);
    }

}