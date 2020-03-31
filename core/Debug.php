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
    private $_response;
    private $_errorText;
    private $_save_RPC = false;
    private $_ROOT_len = 0;
    private static $_object;

    public function __construct(Request $request, Response $response, array &$config)
    {
        $this->_star = [$_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true), memory_get_usage()];

        $conf = $config['default'];
        if (isset($config[_MODULE])) {
            $conf = $config[_MODULE] + $conf;
//            $conf = array_replace_recursive($conf, $config[_MODULE]);
        }

        if (defined('_RPC') and ($conf['api'] ?? '') === 'rpc' and !in_array(getenv('SERVER_ADDR'), $conf['server'] ?? [_RPC['ip']]))
            $this->_save_RPC = true;

        $this->_conf = $conf;
        $this->_ROOT_len = strlen(_ROOT);
        $this->_run = boolval($conf['run'] ?? false);
        $this->_time = time();
        $this->prevTime = microtime(true) - $this->_star[0];
        $this->memory = memory_get_usage();
        $this->_node[0] = [
            't' => sprintf($this->_print_format, $this->prevTime * 1000),
            'm' => sprintf($this->_print_format, ($this->memory - $this->_star[1]) / 1024),
            'n' => sprintf($this->_print_format, ($this->memory) / 1024),
            'g' => ''];
        $this->prevTime = microtime(true);
        $this->relay('START', []);
        $this->_request = $request;
        $this->_response = $response;
    }

    /**
     * @return Debug
     */
    public static function class()
    {
        return $GLOBALS['_Debug'] ?? null;
    }

    public function error($error, $tract = null)
    {
        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Referer' => getenv("HTTP_REFERER"),
            'Debug' => $this->filename(),
            'Trace' => $tract ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0],
            'Error' => $error,
        ];
        $conf = ['filename' => 'YmdHis', 'path' => _RUNTIME . "/error"];
        $filename = $conf['path'] . "/" . date($conf['filename']) . mt_rand() . '.md';
        !is_dir($conf['path']) and mkdir($conf['path'], 0740, true);
        file_put_contents($filename, json_encode($info, 64 | 128 | 256), LOCK_EX);
    }


    /**
     * 保存记录到的数据
     */
    public function save_logs()
    {
        if (empty($this->_node) or $this->_run === false) return 0;
        $filename = $this->filename();
        if (is_null($filename)) return 0;
        $this->relay('END:save_logs', []);
        $rq = $this->_request;
        $method = $rq->getMethod();
        $data = Array();
        $data[] = "## 请求数据\n```\n";
        $data[] = " - METHOD:\t{$method}\n";
        $data[] = " - GET_URL:\t" . (defined('_URL') ? _URL : '') . "\n";
        $data[] = " - SERV_IP:\t" . ($_SERVER['SERVER_ADDR'] ?? '') . "\n";
        $data[] = " - USER_IP:\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        $data[] = " - REAL_IP:\t" . Client::ip() . "\n";
        $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s', $this->_time) . "\n";
        $data[] = " - AGENT:\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
        $data[] = " - ROOT:\t" . _ROOT . "\n";
        $data[] = " - Router:\t/{$rq->module}/{$rq->controller}/{$rq->action}\t({$rq->router})\n";

        //一些路由结果，路由结果参数
        $Params = implode(',', $rq->getParams());
        $data[] = " - Params:\t({$Params})\n```\n";

        if (!empty($this->_value)) {
            $data[] = "\n## 程序附加\n```\n";
            foreach ($this->_value as $k => &$v) $data[] = " - {$k}:\t{$v}\n";
            $data[] = "```\n";
        }

        $data[] = "\n## 执行顺序\n```\n\t\t耗时\t\t耗内存\t\t占内存\t\n";
        $data[] = "  {$this->_node[0]['t']}\t{$this->_node[0]['m']}\t{$this->_node[0]['n']}\t{$this->_node[0]['g']}进程启动到Debug被创建的消耗总量\n";
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
            $data[] = "\n\n##程序出错1：\n```\n{$this->_errorText}\n```\n";
        }
        $e = error_get_last();
        if (!empty($e)) {
            $data[] = "\n\n##程序出错0：\n```\n" . print_r($e, true) . "\n```\n";
        }

        if (1 and $this->_conf['print']['mysql'] ?? 0) {
            if (is_array($this->_mysql)) {
                $slow = Array();
                foreach ($this->_mysql as $i => $sql) {
                    if (intval($sql['wait']) > 20) $slow[] = $i;
                }
                $data[] = "\n## Mysql 顺序：\n";
                $data[] = " - 当前共执行MYSQL：\t" . count($this->_mysql) . " 次\n";
                if (!empty($slow)) $data[] = " - 超过20Ms的语句有：\t" . implode(',', $slow) . "\n";
                $data[] = "```\n" . print_r($this->_mysql, true) . "\n```";
            }
        }

        if (($this->_conf['print']['post'] ?? 0) and ($method === 'POST' or $method === 'AJAX')) {
            $data[] = "\n## Post原始数据：\n```\n" . file_get_contents("php://input") . "\n```\n";
        }

        if ($this->_conf['print']['html'] ?? 0) {
            $data[] = "\n## 页面实际响应： \nEcho:\n```\n" . ob_get_contents() . "\n```\n";
            $display = $this->_response->_display_Result;
            if (empty($display)) $display = var_export($display, true);
            $data[] = "\nContent-Type:{$this->_response->_Content_Type}\n```\n" . $display . "\n```\n";
        }

        if ($this->_conf['print']['server'] ?? 0) {
            $data['_SERVER'] = "\n## _SERVER\n```\n" . print_r($_SERVER, true) . "\n```\n";
        }

        $data[] = "\n";

        if (defined('_SELF_DEBUG')) {
            $p = dirname($filename);
            if (!is_dir($p)) @mkdir($p, 0740, true);
            $s = file_put_contents($filename, $data, LOCK_EX);
            return $s;
        }

        if ($this->_save_RPC) {
            return RPC::post('/debug', ['filename' => $filename, 'data' => implode($data)]);
        }

        if (is_dir(_RUNTIME . '/debug/move/')) {
            $s = file_put_contents(_RUNTIME . '/debug/move/' . urlencode(base64_encode($filename)), implode($data), LOCK_EX);
            if ($s) return $s;
        }

        $p = dirname($filename);
        if (!is_dir($p)) @mkdir($p, 0740, true);

        return file_put_contents($filename, $data, LOCK_EX);
    }

    public static function move(bool $show = false)
    {
        if ($show) echo "moveDebug:\t" . _RUNTIME . "/debug/move\n";

        $array = Array();
        $dir = new \DirectoryIterator(_RUNTIME . '/debug/move');

        foreach ($dir as $i => $f) {
            if ($i > 1000) break;
            if ($f->isFile()) $array[] = $f->getFilename();
        }
        if (!empty($array)) {
            if ($show) print_r($array);

            foreach ($array as $file) {
                try {
                    $move = base64_decode(urldecode($file));
                    $p = dirname($move);
                    if (!is_readable($p)) @mkdir($p, 0740, true);
                    if (!is_dir($p)) @mkdir($p, 0740, true);
                    rename(_RUNTIME . "/debug/move/{$file}", $move);
                } catch (Exception $e) {
                    print_r(['moveDebug' => $e]);
                }
            }
        }
    }

    public static function copy(bool $show = false)
    {
        if ($show) echo "moveDebug:\t" . _RUNTIME . "/debug/cache\n";

        $array = Array();
        $dir = new \DirectoryIterator(_RUNTIME . '/debug/cache');

        foreach ($dir as $i => $f) {
            if ($i > 1000) break;
            if ($f->isFile()) $array[] = $f->getFilename();
        }
        if (!empty($array)) {
            if ($show) print_r($array);

            foreach ($array as $file) {
                try {
                    $data = file_get_contents(_RUNTIME . "/debug/cache/{$file}");
                    $data = json_decode($data, true);
                    $p = dirname($data['filename']);
                    if (!is_dir($p)) @mkdir($p, 0740, true);
                    file_put_contents($data['filename'], $data['data'], LOCK_EX);
                    @unlink(_RUNTIME . "/debug/cache/{$file}");
                } catch (Exception $e) {
                    print_r(['moveDebug' => $e]);
                }
            }
        }
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
     * 禁用debug
     * @param int $mt 禁用几率，100，即为1%的机会会启用
     * @return $this
     */
    public function disable(int $mt = 0)
    {
        if ($mt > 1 && mt_rand(0, $mt) === 1) return $this;
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

    public function __set(string $name, $value)
    {
        $this->_value[$name] = $value;
    }

    public function __get(string $name)
    {
        return $this->_value[$name] ?? null;
    }

    public function mysql_log($val, $pre = null)
    {
        if ($this->_run === false or !($this->_conf['print']['mysql'] ?? 0)) return;
        static $count = 0;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $this->relay("Mysql[" . (++$count) . '] = ' . print_r($val, true) . str_repeat('-', 10) . '>', $pre);
    }

    /**
     * 创建一个debug点
     * @param $msg
     * @param null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     *
     * $pre=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
     * @param $msg
     * @param array|null $prev
     * @return $this|bool
     */
    public function relay($msg, array $prev = null)
    {
        if (_CLI) return false;
        if (!$this->_run) return $this;
        $prev = is_null($prev) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] : $prev;
        if (isset($prev['file'])) {
            $file = substr($prev['file'], $this->_ROOT_len) . " [{$prev['line']}]";
        } else {
            $file = null;
        }
        if (is_array($msg)) $msg = "\n" . print_r($msg, true);
        elseif (is_object($msg)) $msg = "\n" . print_r($msg, true);
        elseif (is_null($msg)) $msg = "\n" . var_export($msg, true);

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

    private $_folder;
    private $_root;
    private $_path = '';
    private $_file;
    private $_filename;
    private $_hasError = false;

    /**
     * 设置或读取debug文件保存的根目录
     * @param $path
     * @param bool|null $right
     * @return $this|string
     */
    public function root(string $path = null)
    {
        if (is_null($path)) {
            if (is_null($this->_root))
                return $this->_root = str_replace(['{DOMAIN}', '{DATE}'], [_DOMAIN, date('Y_m_d')], $this->_conf['path']);
            return $this->_root;
        }
        $this->_root = '/' . trim($path, '/');
        return $this;
    }

    /**
     * 修改前置目录
     * @param string|null $path
     * @return $this|string
     */
    public function folder(string $path = null)
    {
        if (is_null($path)) {
            if (is_null($this->_folder))
                return $this->_folder = "/{$this->_request->controller}/{$this->_request->action}" . ucfirst($this->_request->method);
            return $this->_folder;
        }
        $path = trim($path, '/');
        $this->_folder = "/{$path}/{$this->_request->controller}/{$this->_request->action}" . ucfirst($this->_request->method);
        return $this;
    }

    /**
     * 修改后置目录
     * @param string|null $path
     * @return $this|string
     */
    public function path(string $path = null)
    {
        if (is_null($path)) return $this->_path;
        $this->_path = '/' . trim($path, '/');
        return $this;
    }

    /**
     * 设置文件名
     * @param $file
     * @return $this|string
     */
    public function file(string $file = null)
    {
        if (is_null($file)) {
            if (is_null($this->_file)) {
                list($s, $c) = explode('.', microtime(true) . '.0');
                return $this->_file = date($this->_conf['rules']['filename'], $s) . "_{$c}_" . mt_rand(100, 999);
            }
            return $this->_file;
        }
        $this->_file = trim(trim($file, '.md'), '/');
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
        if ($file) return $this->file($file);

        if (is_null($this->_filename)) {
            $root = $this->root();
            $folder = $this->folder();
            $file = $this->file();
            if ($this->_hasError) $file .= '_Error';
            $p = "{$root}{$folder}{$this->_path}";
            $this->_filename = "{$p}/{$file}.md";
        }
        return $this->_filename;
    }

}