<?php

namespace esp\core;

final class Debug
{
    private $prevTime;
    private $memory;
    private $_run;
    private $_star;
    private $_time;
    private $_value = Array();
    private $_print_format = '% 9.3f';
    private $_node = Array();
    private $_node_len = 0;
    private $_mysql = Array();
    private $_conf;
    private $_request;
    private $_errorText;

    public function __construct(Request $request, Response $response, array &$conf, array &$error)
    {
        $this->_star = defined('_STAR') ? _STAR : [microtime(true), memory_get_usage()];
        $this->_conf = $conf;
        $this->_run = boolval($conf['run'] ?? false);
        $this->_time = time();
        $this->prevTime = microtime(true) - $this->_star[0];
        $this->memory = memory_get_usage();
        $time = sprintf($this->_print_format, $this->prevTime * 1000);
        $memo = sprintf($this->_print_format, ($this->memory - $this->_star[1]) / 1024);
        $now = sprintf($this->_print_format, ($this->memory) / 1024);
        $this->_node[0] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => ''];
        $this->prevTime = microtime(true);
        $this->relay('START:__construct', []);
        $this->_request = $request;

        //自定义出错处理方法
        $this->register_handler($error);

        //将最后保存数据部分注册为关门动作
        register_shutdown_function(function () use ($request, $response, $error) {
            $this->save_logs($request, $response, $error['debug']);
        });

    }


    /**
     * 仅记录错误，但不阻止程序继续运行
     * @param $error
     */
    public function error(array $error, array $prev = null)
    {
        $info = Array();
        $info['time'] = date('Y-m-d H:i:s');
        $info['url'] = _URI;
        $info['referer'] = getenv("HTTP_REFERER");
        $info['debug'] = $this->filename();
        $filename = root($this->_conf['path']) . "/error/" . date("{$this->_conf['rules']['error']}") . mt_rand() . '.txt';
        $route = $this->get_routes_info();
        $this->_errorText = '错误信息：' . print_r($error, true) .
            "\n\n文件位置：" . print_r($prev ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0], true) .
            "\n\n路由信息：" . print_r($route, true) .
            "\n\n用户信息：" . print_r($info, true);
        mk_dir($filename);
        file_put_contents($filename, $this->_errorText . "\n\n环境信息：" . print_r($_SERVER, true), LOCK_EX);
    }

    /**
     * 保存记录到的数据
     */
    private function save_logs(Request $request, Response $response, bool $onlyError)
    {
        if ($onlyError and !$this->_hasError) return;

        if (empty($this->_node) or $this->_run === false) return;
        $filename = $this->filename();
        if (is_null($filename)) return;
        $this->relay('END:save_logs', []);
        $method = $request->getMethod();
        $data = Array();
        $data[] = "## 请求数据\n";
        $data[] = " - METHOD:\t{$method}\n";
        $data[] = " - GET_URL:\t" . (defined('_URL') ? _URL : '') . "\n";
        $data[] = " - SERV_IP:\t" . ($_SERVER['SERVER_ADDR'] ?? '') . "\n";
        $data[] = " - USER_IP:\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        $data[] = " - REAL_IP:\t" . Client::ip() . "\n";
        $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s', $this->_time) . "\n";
        $data[] = " - AGENT:\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
//        $data[] = " - UN_ID:\t\t" . (Client::id()) . "\n";
        $data[] = " - Router:\t/{$request->module}/{$request->controller}/{$request->action}\t({$request->router})\n";

        //一些路由结果，路由结果参数
        $Params = implode(',', $request->getParams());
        $data[] = " - Params:\t({$Params})\n";
        if (!empty($this->_value)) {
            foreach ($this->_value as $k => &$v) {
                $data[] = "- {$k}\t{$v}\n";
            }
        }

        $data[] = "\n## 程序执行顺序：\n```\n";
        $data[] = "uTime\t指上一节点到此节点的运行时间\nuMem\t指上一节点到此节点的消耗内存\ntMem\t指当前节点时占用内存总量\n\n";
        $data[] = "\t\tuTime\tuMem\t\ttMem\t\n";
        $data[] = "  {$this->_node[0]['t']}\t{$this->_node[0]['m']}\t{$this->_node[0]['n']}\t{$this->_node[0]['g']}\t进程启动到Debug被创建的消耗总量，请在程序最靠前处定义：define('_STAR', [microtime(true), memory_get_usage()]);\n";
        unset($this->_node[0]);
        $data[] = "" . (str_repeat('-', 100)) . "\n";
        //具体监控点
        $len = min($this->_node_len + 3, 50);
        foreach ($this->_node as $i => &$row) {
            $data[] = "  {$row['t']}\t{$row['m']}\t{$row['n']}\t" . sprintf("%-{$len}s", $row['g']) . "\t{$row['f']}\n";
        }

        $data[] = "" . (str_repeat('-', 100)) . "\n";
        $time = sprintf($this->_print_format, (microtime(true) - $this->_star[0]) * 1000);
        $memo = sprintf($this->_print_format, (memory_get_usage() - $this->_star[1]) / 1024);
        $total = sprintf($this->_print_format, (memory_get_usage()) / 1024);
        $data[] = "  {$time}\t{$memo}\t{$total}\t进程启动到Debug结束时的消耗总量\n```\n";

        if (!empty($this->_errorText)) {
            $data[] = "\n\n##程序出错：\n```\n{$this->_errorText}\n```\n";
        }
        $e = error_get_last();
        if (!empty($e)) {
            $data[] = "\n\n##程序出错：\n```\n" . print_r($e, true) . "\n```\n";
        }

        if ($this->_conf['print']['mysql'] ?? 0) {
            if (is_array($this->_mysql)) {
                $slow = Array();
                foreach ($this->_mysql as $i => $sql) {
                    if (intval($sql['wait']) > 20) {
                        $slow[] = $i;
                    }
                }
                $data[] = "\n## Mysql 顺序：\n";
                $data[] = " - 当前共执行MYSQL：\t" . count($this->_mysql) . " 次\n";
                if (!empty($slow)) $data[] = " - 超过20Ms的语句有：\t" . implode(',', $slow) . "\n";
                $data[] = "```\n" . print_r($this->_mysql, true) . "\n```";
            }
        }

        if (($this->_conf['print']['post'] ?? 0) and $method === 'POST') {
            $data[] = "\n## Post原始数据：\n```\n" . file_get_contents("php://input") . "\n```\n";
        }

        if ($this->_conf['print']['html'] ?? 0) {
            $data[] = "\n## 页面实际响应： \nExit:\n```\n" . ob_get_contents() . "\n```\n";
            $data[] = "\nContent-Type:{$response->_Content_Type}\n```\n" . $response->_display_Result . "\n```\n";
        }

        if ($this->_conf['print']['server'] ?? 0) {
            $data['_SERVER'] = "\n## _SERVER\n```\n" . print_r($_SERVER, true) . "\n```\n";
        }

        $data[] = "\n";
        file_put_contents($filename, $data, LOCK_EX);
    }

    /**
     * 设置是否记录post内容
     * @param bool $val
     * @return $this
     */
    public function post(bool $val)
    {
        $this->_conf['print']['post'] = $val;
        return $this;
    }

    /**
     * 设置是否记录$_SERVER
     * @param bool $val
     * @return $this
     */
    public function server(bool $val)
    {
        $this->_conf['print']['server'] = $val;
        return $this;
    }

    /**
     * 禁止
     */
    public function disable()
    {
        $this->_run = false;
    }

    /**
     * 启动，若程序入口已经启动，这里则不需要执行
     * @param null $pre
     * @return $this
     */
    public function star($pre = null)
    {
        $this->_run = true;
        $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->relay('STAR BY HANDer', $pre);//创建起点
        return $this;
    }

    /**
     * 停止记录，只是停止记录，不是禁止
     * @param null $pre
     * @return $this|null
     */
    public function stop($pre = null)
    {
        if (!$this->_run) return null;
        if (!empty($this->_node)) {
            $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $this->relay('STOP BY HANDer', $pre);//创建一个结束点
        }
        $this->_run = null;
        return $this;
    }

    public function __set($name, $value)
    {
        $this->_value[$name] = $value;
    }

    public function __get($name)
    {
        return $this->_value[$name] ?? null;
    }

    public function mysql_log($val)
    {
        if ($this->_run === false or !($this->_conf['print']['mysql'] ?? 0)) return;
        $this->_mysql[] = $val;
    }

    /**
     * 创建一个debug点
     * @param $msg
     * @param null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     *
     * $pre=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
     */
    /**
     * @param $msg
     * @param array|null $prev
     * @return $this
     */
    public function relay($msg, array $prev = null)
    {
        if (!$this->_run) return $this;
        $prev = is_null($prev) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] : $prev;
        if (isset($prev['file'])) {
            $file = substr($prev['file'], strlen(_ROOT)) . " [{$prev['line']}]";
        } else {
            $file = null;
        }
        if (is_array($msg)) $msg = "\n" . print_r($msg, true);

        $this->_node_len = max(iconv_strlen($msg), $this->_node_len);
        $nowMemo = memory_get_usage();
        $time = sprintf($this->_print_format, (microtime(true) - $this->prevTime) * 1000);
        $memo = sprintf($this->_print_format, ($nowMemo - $this->memory) / 1024);
        $now = sprintf($this->_print_format, ($nowMemo) / 1024);
        $this->prevTime = microtime(true);
        $this->memory = $nowMemo;
        $this->_node[] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => $msg, 'f' => $file];
        return $this;
    }

    private $_folder = '';
    private $_path;
    private $_file;
    private $_hasError = false;

    /**
     * 设置debug文件保存的目录
     * @param $path
     * @param bool|null $right
     * @return $this
     */
    public function path(string $path)
    {
        $this->_path = trim($path, '/');
        return $this;
    }

    /**
     * 指定最后一节文件目录名
     * @param $path
     * @return $this
     */
    public function folder(string $path)
    {
        $this->_folder = '/' . trim($path, '/');
        return $this;
    }

    /**
     * 设置文件名
     * @param $file
     * @return $this
     */
    public function file(string $file)
    {
        $this->_file = $file;
        return $this;
    }

    /**
     * 设置，或读取完整的保存文件地址和名称
     * 如果运行一次后，第二次运行时不会覆盖之前的值，也就是只以第一次取得的值为准
     * @param string|null $file
     * @return null|string
     */
    public function filename(string $file = null)
    {
        if (empty($this->_request->controller)) return null;
        static $fileName;
        if (!is_null($fileName)) return $fileName;
        if (!is_null($file)) {
            $this->_file = $file;
            $fileName = null;
        }

        list($s, $c) = explode('.', microtime(true) . '.0');
        $file = $this->_file ?: (date($this->_conf['rules']['filename'], $s) . "_{$c}_" . mt_rand(100, 999));
        if ($this->_hasError) $file .= '_Error';

        $path = $this->_path ?: "{$this->_request->module}/{$this->_request->controller}/{$this->_request->action}" . ucfirst($this->_request->method);
        $fileName = root($this->_conf['path']) . '/debug/' . date($this->_conf['rules']['folder'], $s) . "/{$path}{$this->_folder}/{$file}.md";

        mk_dir($fileName);
        return $fileName;
    }

    /**
     * 简单处理出错信息
     */
    public static function simple_register_handler()
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
     * @param $display
     * 显示程度:0=不显示,1=简单,2=完整
     */
    private function register_handler(array $display)
    {
        /**
         * 一般警告错误
         * @param $errNo
         * @param $errStr
         * @param $errFile
         * @param $errLine
         */
        $handler_error = function (int $errNo, string $errStr, string $errFile, int $errLine, array $errcontext) use ($display) {
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

            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]);
            if (is_int($display['run'])) {
                if ($display['run'] === 0) {
                    exit;
                } else if ($display['run'] === 1) {
                    unset($err['text']);
                    print_r($err);
                    exit;
                } else if ($display['run'] === 2) {
                    $this->displayError('Error', $err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
                } else {
                    echo $this->displayState($display['run']);
                    exit;
                }
            } else {
                exit($display);
            }
        };

        /**
         * 严重错误
         * @param $error
         */
        $handler_exception = function (\Throwable $error) use ($display) {
            $err = Array();
            $err['level'] = 'Throw';
            $err['error'] = $error->getMessage();
            $err['code'] = $error->getCode();
            $err['file'] = $error->getFile();
            $err['line'] = $error->getLine();
            $this->error($err, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]);
            if (is_int($display['throw'])) {
                if ($display['throw'] === 0) {
                    exit;
                } else if ($display['throw'] === 1) {
                    print_r($err);
                    exit;
                } else if ($display['throw'] === 2) {
                    $this->displayError('Throw', $err, $error->getTrace());
                } else if ($display['throw'] === 3) {
                    if (!$err['code']) $err['code'] = $display['run'];
                    echo $this->displayState($err['code']);
                    exit;
                } else {
                    echo $this->displayState($display['throw']);
                    exit;
                }
            } else {
                exit($display);
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
     * 显示成一个错误状态
     * @param $code
     * @return string
     */
    public static function displayState(int $code)
    {
        $state = Config::states($code);
        if (_CLI) return "[{$code}]:{$state}";
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
                    $args = '"' . implode('","', $tr['args']) . '"';
                }
                $str .= "{$tr['function']}({$args})";
            }
            $str .= '</td></tr>';
            $traceHtml .= $str;
        }

        if ($this->_show_route) {
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

        $fontSize = $this->_font_size ?: '75%';
        if (is_numeric($fontSize)) $fontSize .= ($fontSize > 50 ? '%' : 'px');
        $color = ['#555', '#def', '#ffe', '#f0c040'];
        $html = <<<HTML
<!DOCTYPE html><html lang="zh-cn"><head><meta charset="UTF-8"><title>%s</title><style>
body {margin: 0;padding: 0;font-size: %s;color:{$color[0]};font-family:"Source Code Pro", "Arial", "Microsoft YaHei", "msyh", "sans-serif";}
table {width: 80%%;margin: 1em auto;border: 1px solid #456;box-shadow: 5px 5px 2px #ccc;}
tr,td {overflow: hidden;}td {text-indent: 0.5em;line-height: 2em;}
table.head {background: {$color[1]};}table.head td.l {width: 6em;font-weight: bold;}td.msg{color:red;}
table.trade tr:nth-child(odd){background: {$color[2]};} 
table.trade tr.nav{background: {$color[3]};} td.time{text-align: right;padding-right:1em;}
table.trade td {border-bottom: 1px solid #abc;}table.trade td.l {width: 40%%;}</style>
</head><body><table class="head" cellpadding="0" cellspacing="0">
<tr><td class="l">错误代码：</td><td>%s</td></tr>
<tr><td class="l">错误信息：</td><td class="msg">%s</td></tr>
<tr><td class="l">错误位置：</td><td>%s</td></tr>%s
</table><table class="trade" cellpadding="0" cellspacing="0">
<tr class="nav"><td><b>Trace</b> : (执行顺序从上往下)</td><td class="time">%s</td></tr>%s</table></body></html>
HTML;
        $html = printf($html,
            $this->filter_root($err['error']),
            $fontSize,
            $type . '=' . $err['code'],
            $this->filter_root($err['error']),
            "{$this->filter_root($err['file'])} ({$err['line']})",
            $routeHtml,
            date('Y-m-d H:i:s'),
            $traceHtml
        );
        exit($html);
    }

    private function filter_root($str)
    {
        return str_replace(_ROOT, '', $str);
    }


    /**
     * 路由结果信息
     * @return array|null
     */
    private function get_routes_info()
    {
        $route = Array();
        $route['Router'] = $this->_request->router;
        $route['Path'] = $this->_request->directory;
        $route['Module'] = $this->_request->module;
        $route['Control'] = $this->_request->controller;
        $route['Action'] = $this->_request->action;
        $route['Params'] = $this->_request->getParams();
        return $route;
    }

}