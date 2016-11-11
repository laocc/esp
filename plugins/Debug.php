<?php
namespace esp\plugins;

use esp\core\Plugin;
use esp\core\Request;
use esp\core\Response;

/**
 *
 * 主要用于在程序中自己创建监控点，顺带显示xdebug
 *
 * 本类只记录程序执行时间(ms)顺序及内存消耗(kb)
 *
 * 其中调用了全部的6个HOOK，可以大致观察进入控制器之前的执行顺序
 *
 * 在dispatchLoopStartup()中，将自己记录到request中_plugin_debug，供控制器中读取，
 *
 * 另外_debug_action是Kernel::routerShutdown()里记录路由设置中的有没有要求启动debug记录，
 *
 * save_logs()在整个系统最后一步执行，即便exit()也会被执行到。
 *
 *
 *
 *
 *
 * 最后的日志内容大概类似下面：
 * 第一行GET，是请求类型，有可能是：get,post,head,put等
 * Router：路由结果=/模块/控制器/动作[生效的路由设置名称]
 * Params：路由后的参数
 * 虚线中是所有创建的节点，
 * 比如：5.postDispatch这一行，表示的是从上一个节点4.preDispatch创建后到当前节点5.postDispatch创建之前的消耗量
 * 第1列=时间；
 * 第2列=内存消耗本节点-上节点
 * 第3列=该节点时内存消耗，也就是memory_get_usage()的值，
 *
 *
 *<?php
 *
 * # GET    http://www.kaibuy.top/class/nosql
 * # IP_S    192.168.1.11
 * # IP_C    192.168.1.167
 * # AGENT    Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0
 *
 * # Router    /Index/Article/list    [class]
 * # Params    {"class":"nosql"}
 *
 * #     0.924        8.898    框架自身消耗
 * # ----------------------------------------------------------------------------------------------------
 * #     0.006        0.000    0.start
 * #     0.695       59.609    1.routerStartup
 * #     0.045        2.805    2.routerShutdown
 * #     0.453        3.555    3.dispatchLoopStartup
 * #     0.494       31.391    4.preDispatch
 * #     3.303      127.211    5.postDispatch
 * #     0.010        1.000    6.dispatchLoopShutdown
 * #     0.067       -3.883    END:save_logs
 * # ----------------------------------------------------------------------------------------------------
 * #     6.192      236.453    业务程序部分消耗合计
 */
class Debug extends Plugin
{
    private $prevTime;
    private $memory;
    private $save;
    private $_value;
    private $_print_format = '% 9.3f';
    private $_log_path = 'cache/debug';

    private $_node = [];
    private $_node_len = 0;

    public function __construct()
    {
        /**
         * 这儿记录的是从站点入口index.php执行到现在的消耗，大致为bootstrap的总消耗
         * 也就是说没有对bootstrap每一步做统计，如果有必要的话，最好在bootstrap内部做统计，再想办法把结果送入这里，意义不大，不建议；
         */
        $star = defined('_STAR') ? _STAR : [microtime(true), memory_get_usage()];
        $this->prevTime = microtime(true) - $star[0];
        $this->memory = memory_get_usage();
        $time = sprintf($this->_print_format, $this->prevTime * 1000);
        $memo = sprintf($this->_print_format, ($this->memory - $star[1]) / 1024);
        $now = sprintf($this->_print_format, ($this->memory) / 1024);
        $this->_node[0] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => 'bootstrap合计'];
        $this->prevTime = microtime(true);
        $this->relay('0.start');
    }

    /**
     * 1.在路由之前触发
     * 显示xDebug结果
     */
    public function routeBefore(Request $request, Response $response)
    {
        $xDebug = isset($_GET['xdebug']) ? $_GET['xdebug'] : (isset($_GET['XDEBUG']) ? $_GET['XDEBUG'] : null);
        if ($xDebug) {
            new ext\Xdebug($xDebug, __FILE__);
            exit;
        }

        $this->relay('1.routeBefore');

        //将最后保存数据部分注册为关门动作
        register_shutdown_function(function () use ($request) {
            $this->save_logs($request);
            $this->show_debug();
        });

    }

    /**
     * 2.路由结束之后触发
     */
    public function routeAfter(Request $request, Response $response)
    {
        $this->relay('2.routeAfter');
    }


    /**
     * 3.分发循环开始之前被触发
     */
    public function dispatchBefore(Request $request, Response $response)
    {
        $this->relay('3.dispatchBefore');

        //把自己放入一下临时变量，供后面控制器读取，只能放在这个方法里，不可以提前。
        $request->setParam('_plugin_debug', $this);

        //_debug_action是在kernel中赋入的
        $action = $request->getParam('_debug_action');
        if ($action === true)
            $this->star();
        elseif ($action === false)
            $this->stop();

    }

    /**
     * 4.分发之前触发
     */
    public function dispatchAfter(Request $request, Response $response)
    {
        $this->relay('4.dispatchAfter');
    }


    /**
     * 6.分发循环结束之后触发
     */
    public function kernelEnd(Request $request, Response $response)
    {
        $this->relay('6.kernelEnd');

    }


    //显示xdebug
    private function show_debug()
    {
        if (!function_exists('xdebug_get_tracefile_name')) return;
        if (!$debug = xdebug_get_tracefile_name()) return;

        if (_CLI) {
            echo "xDebug File:\t{$debug}\n\n";
        } else {
            echo "<a href='?xdebug={$debug}' style='position: fixed;left:0;top:0;background: red;color:#fff;padding:0.2em 1em;' target='_blank'>xDebug</a>";
        }
    }

    /**
     * 保存记录到的数据
     */
    private function save_logs(Request $request)
    {
        if (_CLI) echo "\n\n";

        if (empty($this->_node) or $this->save === null) return;
        $this->relay('END:save_logs');

        $filename = $this->filename();
        $data = [];
        $data[] = "<?php \n\n";
        $data[] = "# " . $request->getMethod() . "\t" . (_CLI ? '[CLI]' : $request->url) . "\n";
        $data[] = "# IP\t" . (_IP) . "\n";
        $data[] = "# AGENT\t" . server('HTTP_USER_AGENT', '') . "\n\n";
        $data[] = "# Router\t/{$request->module}/{$request->controller}/{$request->action}\t[{$request->route}]\n";

        //一些路由结果，路由结果参数
        $Params = $request->getParams();
        $Params = json_encode($Params, 256);
        $data[] = "# Params\t{$Params}\n";
        if (!empty($this->_value)) {
            foreach ($this->_value as $k => &$v) {
                $data[] = "# {$k}\t{$v}\n";
            }
        }

        $data[] = "\n# {$this->_node[0]['t']}\t{$this->_node[0]['m']}\t{$this->_node[0]['n']}\t{$this->_node[0]['g']}\n";
        unset($this->_node[0]);
        $data[] = "# " . (str_repeat('-', 100)) . "\n";
        //具体监控点
        $len = min($this->_node_len + 3, 100);
        foreach ($this->_node as $i => &$row) {
            $data[] = "# {$row['t']}\t{$row['m']}\t{$row['n']}\t" . sprintf("%-{$len}s", $row['g']) . "\t{$row['f']}\n";
        }

        $data[] = "# " . (str_repeat('-', 100)) . "\n";
        $star = defined('_STAR') ? _STAR : [0, 0];
        $time = sprintf($this->_print_format, (microtime(true) - $star[0]) * 1000);
        $memo = sprintf($this->_print_format, (memory_get_usage() - $star[1]) / 1024);
        $total = sprintf($this->_print_format, (memory_get_usage()) / 1024);
        $data[] = "# {$time}\t{$memo}\t{$total}\t业务程序部分消耗合计\n";

        file_put_contents($filename, $data, LOCK_EX);
    }

    public function star()
    {
        $this->save = true;
    }

    public function disable()
    {
        $this->save = null;
    }

    public function stop()
    {
        if (!$this->save) return;
        if (!empty($this->_node)) $this->relay('shutdown');//创建一个结束点
        $this->save = false;
    }

    public function __set($name, $value)
    {
        $this->_value[$name] = $value;
    }

    public function __get($name)
    {
        if (strtolower($name) === 'star') {
            return $this->save = true;

        } elseif (strtolower($name) === 'stop') {
            $this->stop();
            return false;

        } else {
            return isset($this->_value[$name]) ? $this->_value[$name] : null;
        }
    }

    /**
     * 创建一个debug点
     * @param $msg
     * @param null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     *
     * $pre=debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
     *
     */
    public function relay($msg, $prev = null)
    {
        if ($this->save === false) return;
        $prev = $prev ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        if (isset($prev['file'])) {
            $file = substr($prev['file'], strlen(_ROOT) - 1) . " [{$prev['line']}]";
        } else {
            $file = null;
        }
        $this->_node_len = max(iconv_strlen($msg), $this->_node_len);
        $nowMemo = memory_get_usage();
        $time = sprintf($this->_print_format, (microtime(true) - $this->prevTime) * 1000);
        $memo = sprintf($this->_print_format, ($nowMemo - $this->memory) / 1024);
        $now = sprintf($this->_print_format, ($nowMemo) / 1024);
        $this->prevTime = microtime(true);
        $this->memory = $nowMemo;
        $this->_node[] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => $msg, 'f' => $file];
    }


    private function filename()
    {
        $log_dir = root($this->_log_path, true) . date('Y-m-d') . '/' . _MODULE . '/' .
            (date('H.i.s') . '_' . microtime(true)) . '.php';

        mk_dir($log_dir);
        return $log_dir;
    }


}