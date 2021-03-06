<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\db\Redis;
use esp\error\EspError;
use function \esp\helper\root;
use function \esp\helper\str_rand;

final class Request
{
    private $_dispatcher;
    private $_var = Array();
    public $loop = false;//控制器间跳转循环标识
    public $router_path = null;//路由配置目录
    public $router = null;//实际生效的路由器名称
    public $params = Array();
    public $counter = ['concurrent' => false, 'counter' => false];

    public $virtual;
    public $module;
    public $controller;//控制器名，不含后缀
    public $action;
    public $method;
    public $directory;
    public $referer;
    public $uri;
    public $suffix;
    public $contFix;
    public $route_view;
    public $exists = true;//是否为正常的请求，请求了不存在的控制器
    private $_ajax;

    public function __construct(Dispatcher $dispatcher, array $config = null)
    {
        $this->method = strtoupper(getenv('REQUEST_METHOD') ?: '');
        $this->_ajax = !_CLI && (strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') === 'xmlhttprequest');

        if ($this->method === 'GET' and $this->_ajax) $this->method = 'AJAX';
        else if (_CLI) $this->method = 'CLI';

        if (!is_array($config) or empty($config)) $config = [];
        $config += [
            'directory' => '/application',
            'router' => '/common/routes',
            'controller' => '',
            'suffix' => ['auto' => 'Action', 'get' => 'Get', 'ajax' => 'Ajax', 'post' => 'Post', 'cli' => 'Cli'],
            'concurrent' => false, 'counter' => false
        ];

        $this->_dispatcher = $dispatcher;
        $this->virtual = _VIRTUAL;//虚拟机
        $this->module = '';//虚拟机下模块
        $this->directory = root($config['directory']);
        $this->router_path = root($config['router']);
        $this->contFix = $config['controller'];//控制器后缀，固定的
        $this->suffix = $config['suffix'];//数组，方法名后缀，在总控中根据不同请求再取值
        $this->counter['concurrent'] = $config['concurrent'];
        $this->counter['counter'] = $config['counter'];
        $this->referer = _CLI ? null : (getenv("HTTP_REFERER") ?: '');
    }

    public function __debugInfo()
    {
        return ([
            'method' => $this->method,
            'virtual' => $this->virtual,
            'module' => $this->module,
            'directory' => $this->directory,
            'router_path' => $this->router_path,
            'contFix' => $this->contFix,
            'suffix' => $this->suffix,
            'referer' => $this->referer,
            'uri' => _URI,
        ]);
    }

    public function __toString(): string
    {
        return json_encode([
            'method' => $this->method,
            'virtual' => $this->virtual,
            'module' => $this->module,
            'directory' => $this->directory,
            'router_path' => $this->router_path,
            'contFix' => $this->contFix,
            'suffix' => $this->suffix,
            'referer' => $this->referer,
            'uri' => _URI,
        ], 256 | 64);
    }

    public function id(): string
    {
        return getenv('REQUEST_ID') ?: md5(mt_rand() . print_r($_SERVER, true));
    }

    /**
     * 用于缓存组KEY用
     * @return string
     */
    public function getControllerKey(): string
    {
        return $this->virtual . $this->directory . $this->module . $this->controller . $this->action . json_encode($this->params);
    }

    /**
     * 控制器方法后缀
     * @return mixed
     */
    public function getActionExt(): string
    {
        $suffix = $this->suffix;
        if ($this->isGet() and ($p = $suffix['get'] ?? '')) $actionExt = $p;
        elseif ($this->isPost() and ($p = $suffix['post'] ?? '')) $actionExt = $p;
        elseif ($this->isAjax() and ($p = $suffix['ajax'] ?? '')) $actionExt = $p;//必须放在isPost之后
        elseif (_CLI and ($p = $suffix['cli'] ?? '')) $actionExt = $p;
        else $actionExt = strtolower($this->method);
        return ucfirst($actionExt);
    }

    public function recodeConcurrent(Redis $redis)
    {
        //统计最大并发
        if ($this->counter['concurrent']) {
            $redis->hIncrBy($this->counter['concurrent'] . '_concurrent_' . date('Y_m_d'), '' . _TIME, 1);
        }
    }


    /**
     *
     * 统计最大并发
     * 记录各控制器请求计数 若是非法请求，不记录
     *
     * @param Redis $redis
     */
    public function recodeCounter(Redis $redis = null)
    {
        if (!$redis or !$this->exists or !$this->counter['counter']) return;

        //记录各控制器请求计数
        $counter = $this->counter['counter'];

        $key = sprintf('%s/%s/%s/%s/%s/%s', date('H'), $this->method, $this->virtual, $this->module ?: 'auto', $this->controller, $this->action);
        if (is_array($counter)) {
            $counter += ['key' => 'DEBUG', 'params' => 0];
            $hKey = "{$counter['key']}_counter_" . date('Y_m_d');
            if ($counter['params'] and $this->params[0] ?? null) {
                $key .= "/{$this->params[0]}";
            }

        } else {
            $hKey = "{$counter}_counter_" . date('Y_m_d');
        }
        $redis->hIncrBy($hKey, $key, 1);

    }

    /**
     * 获取最大并发数
     * @param int $time
     * @return array
     */
    public function getConcurrent(int $time = _TIME)
    {
        if (!$this->counter['concurrent']) return [];
        $key = "{$this->counter['concurrent']}_concurrent_" . date('Y_m_d', $time);
        $all = $this->_dispatcher->_config->Redis()->hGetAlls($key);
//        arsort($all);
        return $all;
    }

    /**
     * 读取（控制器、方法）计数器值表
     * @param int $time
     * @param bool|null $method
     * @return array|array[]
     * @throws EspError
     */
    public function getCounter(int $time = 0, bool $method = null)
    {
        if ($time === 0) $time = _TIME;
        $conf = $this->counter['counter'];
        if (!$conf) return [];
        if (is_array($conf)) {
            if (!isset($conf['key'])) throw new EspError("counter.key未定义");
            $key = "{$conf['key']}_counter_" . date('Y_m_d', $time);
        } else {
            $key = "{$conf}_counter_" . date('Y_m_d', $time);
        }

        $all = $this->_dispatcher->_config->Redis()->hGetAlls($key);
        if (empty($all)) return ['data' => [], 'action' => []];

        $data = [];
        foreach ($all as $hs => $hc) {
            //实际这里是7段，分为5段就行，后三段连起来
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

    public function __get(string $name)
    {
        return isset($this->_var[$name]) ? $this->_var[$name] : null;
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function get(string $name)
    {
        return isset($this->_var[$name]) ? $this->_var[$name] : null;
    }

    public function __set(string $name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function set(string $name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function getParams()
    {
        unset($this->params['_plugin_debug']);
        return $this->params;
    }

    public function getParam(string $key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }


    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 是否get
     * @param bool $ajax ajax方式的get是否也算在内
     * @return bool
     */
    public function isGet(bool $ajax = false): bool
    {
        if ($ajax) return $this->method === 'GET';
        return $this->method === 'GET' && !$this->_ajax;
    }

    /**
     * 是否post，含ajax
     * @param bool $ajax ajax方式的post是否也算在内
     * @return bool
     */
    public function isPost(bool $ajax = true): bool
    {
        if ($ajax) return $this->method === 'POST';
        return $this->method === 'POST' && !$this->_ajax;
    }

    /**
     * 含 Get和Post，
     * $this->method=ajax时仅指get时
     * @return bool
     */
    public function isAjax(): bool
    {
        return _CLI ? false : $this->_ajax;
    }

    public function isCli(): bool
    {
        return _CLI;
    }

    public function ua(): string
    {
        return getenv('HTTP_USER_AGENT') ?: '';
    }


    /**
     * 客户端唯一标识
     * @param string $key
     * @param bool $number
     * @return string
     * @throws EspError
     */
    public function cid(string $key = '_SSI', bool $number = false): string
    {
        if (is_null($this->_dispatcher->_cookies)) {
            throw new EspError("当前站点未启用Cookies，无法获取CID", 1);
        }

        $key = strtolower($key);
        $unique = $_COOKIE[$key] ?? null;
        if (!$unique) {
            $unique = $number ? mt_rand() : str_rand(20);
            if (headers_sent($file, $line)) {
                $err = ['message' => "Header be Send:{$file}[{$line}]", 'code' => 500, 'file' => $file, 'line' => $line];
                throw new EspError($err);
            }
            $time = time() + 86400 * 365;
            $dom = $this->_dispatcher->_cookies->domain;

            if (version_compare(PHP_VERSION, '7.3', '>=')) {
                $option = [];
                $option['domain'] = $dom;
                $option['expires'] = $time;
                $option['path'] = '/';
                $option['secure'] = true;//仅https
                $option['httponly'] = true;
                $option['samesite'] = 'Lax';
                setcookie($key, $unique, $option);
                _HTTPS && setcookie($key, $unique, ['secure' => true] + $option);
            } else {
                setcookie($key, $unique, $time, '/', $dom, false, true);
                _HTTPS && setcookie($key, $unique, $time, '/', $dom, true, true);
            }
        }
        return $unique;
    }


    /**
     * 分析客户端信息
     *
     * @param string|null $agent
     * @return array|string[]
     * ['agent' => '', 'browser' => '', 'version' => '', 'os' => '']
     */
    public function agent(string $agent = null): array
    {
        $u_agent = $agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!$u_agent) return ['agent' => '', 'browser' => '', 'version' => '', 'os' => ''];

        //操作系统
        if (preg_match('/Android/i', $u_agent)) {
            $os = 'android';
        } elseif (preg_match('/iPhone|iPad|macintosh|mac os x/i', $u_agent)) {
            $os = 'ios';
        } elseif (preg_match('/windows|win32/i', $u_agent)) {
            $os = 'windows';
        } elseif (preg_match('/linux/i', $u_agent)) {
            $os = 'linux';
        } else {
            $os = 'unknown';
        }

        //浏览器
        switch (true) {
            case (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) :
                $browser = 'Internet Explorer';
                $fix = 'MSIE';
                break;
            case (preg_match('/Trident/i', $u_agent)) : // IE11专用
                $browser = 'Internet Explorer';
                $fix = 'rv';
                break;
            case (preg_match('/Edge/i', $u_agent)) ://必须在Chrome之前判断
                $browser = $fix = 'Edge';
                break;
            case (preg_match('/MicroMessenger/i', $u_agent)) ://必须在QQBrowser之前判断
                $browser = $fix = 'MicroMessenger';
                break;
            case (preg_match('/QQBrowser/i', $u_agent)) ://必须在Chrome之前判断
                $browser = $fix = 'QQBrowser';
                break;
            case (preg_match('/UCBrowser/i', $u_agent)) ://必须在Apple Safari之前判断
                $browser = $fix = 'UCBrowser';
                break;
            case (preg_match('/Firefox/i', $u_agent)) :
                $browser = $fix = 'Firefox';
                break;
            case (preg_match('/Chrome/i', $u_agent)) :
                $browser = $fix = 'Chrome';
                break;
            case (preg_match('/Safari/i', $u_agent)) :
                $browser = $fix = 'Safari';
                break;
            case (preg_match('/Opera/i', $u_agent)) :
                $browser = $fix = 'Opera';
                break;
            case (preg_match('/Netscape/i', $u_agent)) :
                $browser = $fix = 'Netscape';
                break;
            default:
                $browser = $fix = 'Unknown';
        }

        $pattern = "/(?<bro>Version|{$fix}|other)[\/|\:|\s](?<ver>[0-9a-zA-Z\.]+)/i";
        preg_match_all($pattern, $u_agent, $matches);
        $i = count($matches['bro']) !== 1 ? (strripos($u_agent, "Version") < strripos($u_agent, $fix) ? 0 : 1) : 0;

        return [
            'agent' => $u_agent,
            'browser' => $browser,
            'version' => $matches['ver'][$i] ?: '?',
            'os' => $os];
    }

    /**
     * md5(IP+UA+DOMAIN)
     * @return string
     */
    public function key(): string
    {
        return md5($this->ip() . getenv('HTTP_USER_AGENT') . _DOMAIN);
    }

    /**
     * 客户端IP
     * @return string
     */
    public function ip(): string
    {
        if (_CLI) return '127.0.0.1';
        if (defined('_CIP')) return _CIP;
        foreach (['X-REAL-IP', 'X-FORWARDED-FOR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($ip = ($_SERVER[$k] ?? ''))) {
                if (strpos($ip, ',')) $ip = explode(',', $ip)[0];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) break;
            }
        }
        return $ip ?? '127.0.0.1';
    }

    /**
     * 是否手机端
     * @param string|null $browser
     * @return bool
     */
    public function is_wap(string $browser = null): bool
    {
        $browser = $browser ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (empty($browser)) return true;

        $uaKey = ['MicroMessenger', 'android', 'AlipayClient', 'mobile', 'iphone', 'ipad', 'ipod', 'opera mini', 'windows ce', 'windows mobile', 'symbianos', 'ucweb', 'netfront'];
        foreach ($uaKey as $i => $k) if (stripos($browser, $k)) return true;

        $mobKey = ['Noki', 'Eric', 'WapI', 'MC21', 'AUR ', 'R380', 'UP.B', 'WinW', 'UPG1', 'upsi', 'QWAP', 'Jigs', 'Java', 'Alca', 'MITS', 'MOT-', 'My S', 'WAPJ', 'fetc', 'ALAV', 'Wapa', 'Oper'];
        if (in_array(substr($browser, 0, 4), $mobKey)) return true;

        $isWap = ['HTTP_X_WAP_PROFILE', 'HTTP_UA_OS', 'HTTP_VIA', 'HTTP_X_NOKIA_CONNECTION_MODE', 'HTTP_X_UP_CALLING_LINE_ID'];
        foreach ($isWap as $i => $k) if (isset($_SERVER[$k])) return true;

        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'vnd.wap')) return true;

        return false;
    }

    public function is_ios(string $browser = null): bool
    {
        $browser = $browser ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (empty($browser)) return true;

        $uaKey = ['iphone', 'ipad', 'ipod', 'ios', 'macintosh'];
        foreach ($uaKey as $i => $k) if (stripos($browser, $k)) return true;

        return false;
    }

    public function is_android(): bool
    {
        return stripos(($_SERVER['HTTP_USER_AGENT'] ?? ''), 'android') > 0;
    }

    /**
     * 是否支付宝APP
     * @return bool
     */
    public function is_alipay(): bool
    {
        return stripos(($_SERVER['HTTP_USER_AGENT'] ?? ''), 'AlipayClient') > 0;
    }

    /**
     * 是否微信开发者工具
     * @return bool
     */
    public function is_weChatTools(): bool
    {
        return stripos(($_SERVER['HTTP_USER_AGENT'] ?? ''), 'wechatdevtools') > 0;
    }

    /**
     * 是否微信端
     * @return bool
     */
    public function is_wechat(): bool
    {
        return stripos(($_SERVER['HTTP_USER_AGENT'] ?? ''), 'MicroMessenger') > 0;
    }


    /**
     * 当前客户端是否真实浏览器，注意：这是本人瞎写的，判断起来不保证百分百准确
     * @return bool
     */
    public function is_robot(): bool
    {
        $v = preg_match_all('/([A-Z][a-zA-Z]{4,15}\/\d+\.+\d+)+/', $_SERVER['HTTP_USER_AGENT'] ?? '', $mac);
        if (!$v or !isset($mac[1]) or count($mac[1]) < 3) return true;
        if ($this->is_spider()) return true;

        //如果这几个基本参数少于4个，基本可以确定为非真实浏览器
        $check = ['HTTP_COOKIE', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING', 'HTTP_UPGRADE_INSECURE_REQUESTS', 'HTTP_CACHE_CONTROL', 'HTTP_CONNECTION'];
        $c = 0;
        foreach ($check as $k) if (isset($_SERVER[$k])) $c++;
        return !($c > (count($check) * 0.5));
    }

    /**
     * 是否搜索蜘蛛人
     * @return bool
     */
    public function is_spider(): bool
    {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
            $keys = ['bot', 'slurp', 'spider', 'crawl', 'curl', 'mediapartners-google', 'fast-webcrawler', 'altavista', 'ia_archiver'];
            foreach ($keys as $key) {
                if (!!strripos($agent, $key)) return true;
            }
        }
        return false;
    }


}