<?php
declare(strict_types=1);

namespace esp\core;

use function esp\helper\root;

final class Request
{
    public bool $loop = false;//控制器间跳转循环标识
    public string $router_path;//路由配置目录
    private array $_var = array();
    public array $params = array();

    public string $virtual = '';
    public string $router = '';//实际生效的路由器名称
    public string $module = '';
    public string $controller = '';//控制器名，不含后缀
    public string $action = '';
    public string $method = '';//实际请求方式，get/post等
    public string $directory;
    public string $referer;//等于HTTP_REFERER
    public string $uri;//等于_URI
    public array $suffix;//定义了各种请求方式对应的控制方法名后缀，如：get=Get,post=Post等
    public string $contFix;//控制器后缀，固定的，默认为 Controller
    public ?array $route_view = null;//在route中可以直接定义视图的相关设置，layout,path和file
    public bool $exists = true;//是否为正常的请求，请求了不存在的控制器
    public $empty;

    private array $alias = [];//控制器映射
    private array $allow = [];//仅允许的控制器
    private array $disallow = [];//禁止的控制器
    private bool $_ajax;
    private string $_ua;

    public function __construct(array $config)
    {
        $this->method = strtoupper(getenv('REQUEST_METHOD') ?: '');
        $this->_ajax = !_CLI && (strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') === 'xmlhttprequest');
        $this->_ua = getenv('HTTP_USER_AGENT') ?: '';

        if ($this->method === 'GET' and $this->_ajax) $this->method = 'AJAX';
        else if (_CLI) $this->method = 'CLI';

        $config += [
            'directory' => '/application',
            'router' => '/common/routes',
            'controller' => '',
            'suffix' => [
                'auto' => 'Action',
                'get' => 'Get',
                'ajax' => 'Ajax',
                'post' => 'Post',
                'cli' => 'Cli',
                'controller' => 'Controller',
                'model' => 'Model',
            ],
        ];

        $this->virtual = _VIRTUAL;//虚拟机
        if (isset($config['virtual'])) $this->virtual = $config['virtual'];//虚拟机
        $this->module = '';//虚拟机下模块
        $this->directory = root($config['directory']);
        $this->router_path = root($config['router']);
        $this->contFix = ucfirst($config['suffix']['controller'] ?? 'Controller');//控制器后缀，固定的
        unset($config['suffix']['controller'], $config['suffix']['model']);
        $this->suffix = $config['suffix'];//数组，方法名后缀，在总控中根据不同请求再取值
        $this->referer = _CLI ? '' : (getenv("HTTP_REFERER") ?: '');

        if (isset($config['alias']) and is_array($config['alias'])) $this->alias = $config['alias'];
        if (isset($config['allow']) and is_array($config['allow'])) $this->allow = $config['allow'];
        if (isset($config['disallow']) and is_array($config['disallow'])) $this->disallow = $config['disallow'];
        if (isset($config['empty'])) {
            $this->empty = $config['empty'];
        }
    }

    /**
     * 路由结果
     *
     * @return array
     */
    public function RouterValue(): array
    {
        return [
            'label' => $this->router,
            'virtual' => $this->virtual,
            'method' => $this->method,
            'module' => $this->module,
            'controller' => $this->controller,
            'action' => $this->action,
            'exists' => $this->exists,
            'params' => $this->params,
        ];
    }

    public function __debugInfo()
    {
        return $this->RouterValue() +
            [
                'directory' => $this->directory,
                'router_path' => $this->router_path,
                'contFix' => $this->contFix,
                'suffix' => $this->suffix,
                'referer' => $this->referer,
                'uri' => _URI,
            ];
    }

    public function __toString(): string
    {
        return json_encode($this->__debugInfo(), 256 | 64);
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
        return sha1($this->virtual . $this->directory . $this->module . $this->controller . $this->action . json_encode($this->params));
    }

    /**
     * md5(IP+UA+DOMAIN)
     * @return string
     */
    public function key(): string
    {
        return md5($this->ip() . $this->_ua . _DOMAIN);
    }

    /**
     * 检查控制器是否允许或禁止
     *
     * @return string|null
     */
    public function checkController(): ?string
    {
        if (!empty($this->allow) and !in_array($this->controller, $this->allow)) return $this->controller . ' not exist in allowed';
        if (!empty($this->disallow) and in_array($this->controller, $this->disallow)) return 'disallow';
        //控制器别名转换
        if (!empty($this->alias) and isset($request->alias[$this->controller])) {
            $this->controller = $request->alias[$this->controller];
        }

        return null;
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

    public function __get(string $name)
    {
        return $this->_var[$name] ?? null;
    }

    /**
     * @param string $name
     * @return string|null|array
     */
    public function get(string $name)
    {
        return $this->_var[$name] ?? null;
    }

    public function __set(string $name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function set(string $name, $value)
    {
        $this->_var[$name] = $value;
    }

    public function getParams(): array
    {
        unset($this->params['_plugin_debug']);
        return $this->params;
    }

    public function getParam(string $key)
    {
        return $this->params[$key] ?? null;
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
        return $this->_ua;
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
        $u_agent = $agent ?: $this->_ua;
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
        $browser = $browser ?: $this->_ua;
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
        $browser = $browser ?: $this->_ua;
        if (empty($browser)) return true;

        $uaKey = ['iphone', 'ipad', 'ipod', 'ios', 'macintosh'];
        foreach ($uaKey as $i => $k) if (stripos($browser, $k)) return true;

        return false;
    }

    public function is_ie(): bool
    {
        return (bool)preg_match('/MSIE|Trident/i', $this->_ua);
    }

    public function is_android(): bool
    {
        return stripos($this->_ua, 'android') > 0;
    }

    /**
     * 是否支付宝APP
     * @return bool
     */
    public function is_alipay(): bool
    {
        return stripos($this->_ua, 'AlipayClient') > 0;
    }

    /**
     * 是否微信开发者工具
     * @return bool
     */
    public function is_weChatTools(): bool
    {
        return stripos($this->_ua, 'wechatdevtools') > 0;
    }

    /**
     * 是否微信端
     * @return bool
     */
    public function is_wechat(): bool
    {
        return stripos($this->_ua, 'MicroMessenger') > 0;
    }


    /**
     * 当前客户端是否真实浏览器，注意：这是本人瞎写的，判断起来不保证百分百准确
     * @return bool
     */
    public function is_robot(): bool
    {
        $v = preg_match_all('/([A-Z][a-zA-Z]{4,15}\/\d+\.+\d+)+/', $this->_ua, $mac);
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
        if (empty($this->_ua)) return true;

        $keys = ['bot', 'slurp', 'spider', 'crawl', 'curl', 'mediapartners-google', 'fast-webcrawler', 'altavista', 'ia_archiver'];
        foreach ($keys as $key) {
            if (strripos($this->_ua, $key)) return true;
        }

        return false;
    }

    public function returnEmpty(string $path, string $msg)
    {
        $this->exists = false;
        if (isset($this->empty)) {
            if (is_array($this->empty)) {
                return $this->empty[$this->_request->virtual] ?? $msg;
            }
            return $this->empty;
        }
        return $msg;
    }

}