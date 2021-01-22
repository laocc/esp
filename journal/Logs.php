<?php
declare(strict_types=1);

namespace esp\journal;

use esp\core\Configure;
use esp\core\Request;
use esp\core\Response;
use esp\error\EspError;
use esp\library\Output;
use function esp\helper\root;

final class Logs
{
    private $prevTime;
    private $memory;
    private $_run;
    private $_star;
    private $_time;
    private $_value = array();
    private $_print_format = '% 9.3f';
    private $_node = array();
    private $_node_len = 0;
    private $_mysql = array();
    private $_conf;
    private $_request;
    private $_response;
    private $_redis;
    private $_errorText;
    private $_ROOT_len = 0;
    private $_key;//保存记录的Key,要在控制器中->key($key)
    private $_rpc = [];
    private $_transfer_uri = '/_esp_debug_transfer';
    private $_transfer_path = '';

    /**
     * 保存方式:
     * shutdown：进程结束后
     * rpc：发送RPC
     * transfer：只在主服器内，文件中转，然后由后台机器人移走
     */
    public $_save_mode = 'shutdown';

    public function __construct(Request $request, Response $response, Configure $config, array $setting)
    {
        $this->_star = [$_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true), memory_get_usage()];

        $conf = $setting['default'];
        if (isset($setting[_VIRTUAL])) $conf = $setting[_VIRTUAL] + $conf;

        if (($conf['api'] ?? '') === 'rpc') {
            $this->_save_mode = 'rpc';
            $this->_rpc = $config->_rpc;

            //当前是主服务器，还继续判断保存方式
            if (is_file(_RUNTIME . '/master.lock')) {
                $this->_save_mode = $conf['rpc'] ?? 'shutdown';
                $this->_transfer_path = $conf['transfer'] ?? '';
                if (empty($this->_transfer_path)) $this->_transfer_path = _RUNTIME . '/debug/move';
                $this->_transfer_path = root($this->_transfer_path);

                //保存节点服务器发来的日志
                if (_VIRTUAL === 'rpc' && _URI === $this->_transfer_uri) {
                    $save = $this->transferDebug();
                    exit(getenv('SERVER_ADDR') . ";Length={$save};Time:" . microtime(true));
                }
            }
        }

        $this->_conf = $conf + ['path' => _RUNTIME, 'run' => false, 'host' => [], 'counter' => false];
        $this->_redis = $config->_Redis;
        $this->_ROOT_len = strlen(_ROOT);
        $this->_run = boolval($conf['run']);
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
        if (is_null($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        } else if (is_int($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $tract + 1)[$tract] ?? [];
        }
        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Referer' => getenv("HTTP_REFERER"),
            'Debug' => $this->filename(),
            'Trace' => $tract,
            'Error' => $error,
            'Server' => $_SERVER,
        ];
        $conf = ['filename' => 'YmdHis', 'path' => $this->_conf['error'] ?? (_RUNTIME . '/error')];
        $filename = $conf['path'] . "/" . date($conf['filename']) . mt_rand() . '.md';
        return $this->save_file($filename, json_encode($info, 64 | 128 | 256));
    }

    public function warn($error, $tract = null)
    {
        if (is_null($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        } else if (is_int($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $tract + 1)[$tract] ?? [];
        }

        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Referer' => getenv("HTTP_REFERER"),
            'Debug' => $this->filename(),
            'Trace' => $tract,
            'Error' => $error,
            'Server' => $_SERVER,
        ];
        $conf = ['filename' => 'YmdHis', 'path' => $this->_conf['warn'] ?? (_RUNTIME . '/warn')];
        $filename = $conf['path'] . "/" . date($conf['filename']) . mt_rand() . '.md';
        return $this->save_file($filename, json_encode($info, 64 | 128 | 256));
    }

    public function key(string $key)
    {
        $this->_key = $key;
        return $this;
    }

    /**
     * @param string $filename
     * @param string $data
     * @return string
     */
    public function save_file(string $filename, string $data)
    {
        //        $this->_run = false;//防止重复保存

        if ($filename[0] !== '/') {
            //这是从Error中发来的保存错误日志
            $path = $this->_conf['error'] ?? (_RUNTIME . '/error');
            $filename = "{$path}/{$filename}";
        }

        $send = null;
        //以前通过redis做中转已取消，若日志量大的时候，redis会被塞满

        if ($this->_save_mode === 'transfer') {
            return 'Transfer:' . file_put_contents($this->_transfer_path . '/' . urlencode(base64_encode($filename)), $data, LOCK_EX);

        } else if ($this->_save_mode === 'rpc' and $this->_rpc) {

            /**
             * 发到RPC，写入move专用目录，然后由后台移到实际目录
             */
            $send = Output::new()->rpc($this->_transfer_uri, $this->_rpc)
                ->data(json_encode(['filename' => $filename, 'data' => $data], 256 | 64))->post('html');
            if (is_array($send)) $send = json_encode($send, 256 | 64);
            return "Rpc:{$send}";
        }

        $p = dirname($filename);
        if (!is_dir($p)) {
            try {
                @mkdir($p, 0740, true);
            } catch (EspError $e) {
                print_r($e);
            }
        }

        return 'Self Save:' . file_put_contents($filename, $data, LOCK_EX);
    }

    /**
     * 读取counter值
     * @param int $time
     * @param bool $method
     * @return array
     */
    public function counter(int $time = 0, bool $method = null)
    {
        if ($time === 0) $time = time();
        $key = "{$this->_conf['counter']}_counter_" . date('Y_m_d', $time);
        $all = $this->_redis->hGetAlls($key);
        if (empty($all)) return ['data' => [], 'action' => []];

        $data = [];
        foreach ($all as $hs => $hc) {
            $key = explode('/', $hs, 5);
            $hour = (intval($key[0]) + 1);
            $ca = "/{$key[4]}";
            switch ($method) {
                case true:
                    $ca = "{$key[1]}:{$ca}";
                    break;
                case false;
                    break;
                default:
                    $ca .= ucfirst($key[1]);
                    break;
            }
            $vm = "{$key[2]}.{$key[2]}";
            if (!isset($data[$vm])) $data[$vm] = ['action' => [], 'data' => []];
            if (!isset($data[$vm]['data'][$hour])) $data[$vm]['data'][$hour] = [];
            $data[$vm]['data'][$hour][$ca] = $hc;
            if (!in_array($ca, $data[$vm]['action'])) $data[$vm]['action'][] = $ca;
            sort($data[$vm]['action']);
        }
        return $data;
    }

    /**
     * 保存记录到的数据
     * @param string $pre
     * @return string
     */
    public function save_logs(string $pre = '')
    {
        /**
         * 控制器访问计数器
         * 键名及表名格式是固定的
         */
        if ($this->_conf['counter'] and $this->_request->exists) {
            $key = date('H/') . $this->_request->method .
                '/' . $this->_request->virtual .
                '/' . ($this->_request->module ?: 'auto') .
                '/' . $this->_request->controller .
                '/' . $this->_request->action;
            $this->_redis->hIncrBy("{$this->_conf['counter']}_counter_" . date('Y_m_d'), $key, 1);
        }

        if (empty($this->_node)) return 'empty node';
        else if ($this->_run === false) return 'run false';
        $filename = $this->filename();
        if (empty($filename)) return 'null filename';

        if (isset($GLOBALS['_relay'])) $this->relay(['GLOBALS_relay' => $GLOBALS['_relay']], []);
        $this->relay('END:save_logs', []);
        $rq = $this->_request;
        $method = $rq->getMethod();
        $data = array();
        $data[] = "## 请求数据\n```\n";
        $data[] = " - CallBy:\t{$pre}\n";
        $data[] = " - METHOD:\t{$method}\n";
        $data[] = " - GET_URL:\t" . (defined('_URL') ? _URL : '') . "\n";
        $data[] = " - SERV_IP:\t" . ($_SERVER['HTTP_X_SERV_IP'] ?? ($_SERVER['SERVER_ADDR'] ?? '')) . "\n";
        $data[] = " - USER_IP:\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        $data[] = " - REAL_IP:\t" . _CIP . "\n";
        $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s', $this->_time) . "\n";
        $data[] = " - PHP_VER:\t" . phpversion() . "\n";
        $data[] = " - AGENT:\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
        $data[] = " - ROOT:\t" . _ROOT . "\n";
        $data[] = " - Router:\t/{$rq->virtual}/{$rq->module}/{$rq->controller}/{$rq->action}\t({$rq->router})\n";

        //一些路由结果，路由结果参数
        $Params = implode(',', $rq->getParams());
        $data[] = " - Params:\t({$Params})\n```\n";
        if (!$this->_request->exists) goto save;

        if (!empty($this->_value)) {
            $data[] = "\n## 程序附加\n```\n";
            foreach ($this->_value as $k => &$v) $data[] = " - {$k}:\t{$v}\n";
            $data[] = "```\n";
        }

        $data[] = "\n## 执行顺序\n```\n\t\t耗时\t\t耗内存\t\t占内存\t\n";
        if (isset($this->_node[0])) {
            $data[] = "  {$this->_node[0]['t']}\t{$this->_node[0]['m']}\t{$this->_node[0]['n']}\t{$this->_node[0]['g']}进程启动到Debug被创建的消耗总量\n";
            unset($this->_node[0]);
        }
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
                $slow = array();
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
            $data[] = "\n## 页面实际响应： \n";
            $headers = headers_list();
            headers_sent($hFile, $hLin);
            $headers[] = "HeaderSent: {$hFile}($hLin)";
            $data[] = "\n## _Headers\n```\n" . json_encode($headers, 256 | 128 | 64) . "\n```\n";
            $data[] = "\n## Echo:\n```\n" . ob_get_contents() . "\n```\n";
            $display = $this->_response->_display_Result;
            if (empty($display)) $display = var_export($display, true);
            $data[] = "\nContent-Type:{$this->_response->_Content_Type}\n```\n" . $display . "\n```\n";
        }

        if ($this->_conf['print']['server'] ?? 0) {
            $data['_SERVER'] = "\n## _SERVER\n```\n" . print_r($_SERVER, true) . "\n```\n";
        }

        $data[] = microtime(true) . "\n";

        if (defined('_SELF_DEBUG')) {
            $p = dirname($filename);
            if (!is_dir($p)) @mkdir($p, 0740, true);
            $s = file_put_contents($filename, $data, LOCK_EX);
            return "_SELF_DEBUG={$s}";
        }
        save:
        return $this->save_file($filename, implode($data));
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
        return $this;
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
        if ($this->_run === false or !($this->_conf['print']['mysql'] ?? 0)) return $this;
        static $count = 0;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $this->relay("Mysql[" . (++$count) . '] = ' . print_r($val, true) . str_repeat('-', 10) . '>', $pre);
        return $this;
    }

    public static function recode($data)
    {
        $GLOBALS['_relay'][] = $data;
    }


    /**
     * 创建一个debug点
     *
     * @param $msg
     * @param array|null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     * @return $this|bool
     */
    public function relay($msg, array $prev = null): Debug
    {
        if (!$this->_run) return $this;
        if (is_null($prev)) $prev = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        if (isset($prev['file'])) {
            $file = substr($prev['file'], $this->_ROOT_len) . " [{$prev['line']}]";
        } else {
            $file = null;
        }
        if (is_array($msg)) $msg = "\n" . print_r($msg, true);
        elseif (is_object($msg)) $msg = "\n" . print_r($msg, true);
        elseif (is_null($msg)) $msg = "\n" . var_export($msg, true);
        elseif (is_bool($msg)) $msg = "\n" . var_export($msg, true);
        elseif (!is_string($msg)) $msg = strval($msg);

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
     * @return $this|string
     */
    public function root(string $path = null)
    {
        if (is_null($path)) {
            if (is_null($this->_root))
                return $this->_root = str_replace(
                    ['{RUNTIME}', '{ROOT}', '{VIRTUAL}', '{DATE}'],
                    [_RUNTIME, _ROOT, _VIRTUAL, date('Y_m_d')],
                    $this->_conf['path']);
            return $this->_root;
        }
        $this->_root = '/' . trim($path, '/');
        if (!in_array(_HOST, $this->_conf['host'])) $this->_root .= "/hackers";
        return $this;
    }

    /**
     * 修改前置目录，前置目录从域名或module之后开始
     * @param string|null $path
     * @return $this|string
     */
    public function folder(string $path = null)
    {
        $m = $this->_request->module;
        if (!empty($m)) $m = strtoupper($m) . "/";

        if (is_null($path)) {
            if (is_null($this->_folder)) {
                return $this->_folder = '/' . _DOMAIN . "/{$m}{$this->_request->controller}/{$this->_request->action}" . ucfirst($this->_request->method);
            }
            return $this->_folder;
        }
        $path = trim($path, '/');
        $this->_folder = '/' . _DOMAIN . "/{$m}{$path}/{$this->_request->controller}/{$this->_request->action}" . ucfirst($this->_request->method);
        return $this;
    }

    /**
     * 修改后置目录
     * @param string|null $path
     * @param bool $append
     * @return $this|string
     */
    public function path(string $path = null, bool $append = false)
    {
        if (is_null($path)) return $this->_path;
        if ($append) {
            $this->_path .= '/' . trim($path, '/');
        } else {
            $this->_path = '/' . trim($path, '/');
        }
        return $this;
    }


    /**
     * 指定完整的目录，也就是不采用控制器名称
     * @param string|null $path
     * @return $this|string
     */
    public function fullPath(string $path = null)
    {
        if (is_null($path)) return $this->folder() . $this->path();
        $m = $this->_request->module;
        if ($m) $m = "/{$m}";
        $path = trim($path, '/');
        $this->_folder = '/' . _DOMAIN . "{$m}/{$path}";
        $this->_path = '';
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
                return date($this->_conf['rules']['filename'], intval($s)) . "_{$c}_" . mt_rand(100, 999);
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
    public function filename(string $file = null): string
    {
        if (empty($this->_request->controller)) return '';
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