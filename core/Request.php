<?php
declare(strict_types=1);

namespace esp\core;

use esp\core\ext\EspError;

final class Request
{
    private $_var = Array();
    public $loop = false;//控制器间跳转循环标识
    public $router_path = null;//路由配置目录
    public $router = null;//实际生效的路由器名称
    public $params = Array();

    public $cookies;
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

    public function __construct(array $conf = null)
    {
        $this->method = strtoupper(getenv('REQUEST_METHOD') ?: '');
        if ($this->isAjax() and !$this->isPost()) $this->method = 'AJAX';
        if (!is_array($conf)) $conf = [];
        $conf += [
            'directory' => '/application',
            'router' => '/config/routes',
            'controller' => '',
            'suffix' => ['auto' => 'Action', 'get' => 'Get', 'ajax' => 'Ajax', 'post' => 'Post'],
        ];

        $this->virtual = _VIRTUAL;//虚拟机
        $this->module = '';//虚拟机下模块
        $this->directory = \esp\helper\root($conf['directory']);
        $this->router_path = \esp\helper\root($conf['router']);
        $this->contFix = $conf['controller'];//控制器后缀，固定的
        $this->suffix = $conf['suffix'];//数组，方法名后缀，在总控中根据不同请求再取值
        $this->referer = _CLI ? null : (getenv("HTTP_REFERER") ?: '');
        $this->uri = _CLI ? //CLI模式下 取参数作为路由
            ('/' . trim(implode('/', array_slice($GLOBALS["argv"], 1)), '/')) :
            parse_url(getenv('REQUEST_URI'), PHP_URL_PATH);

        if (_CLI) return;
        $this->cookies = new Cookies();
    }

    public function id()
    {
        return getenv('REQUEST_ID') ?: md5(mt_rand() . print_r($_SERVER, true));
    }

    /**
     * 用于缓存组KEY用
     * @return string
     */
    public function getControllerKey()
    {
        return $this->virtual . $this->directory . $this->module . $this->controller . $this->action . json_encode($this->params);
    }

    /**
     * 控制器方法后缀
     * @return mixed
     * @throws \Exception
     */
    public function getActionExt()
    {
        $suffix = $this->suffix;
        if ($this->isGet() and ($p = $suffix['get'] ?? '')) $actionExt = $p;
        elseif ($this->isPost() and ($p = $suffix['post'] ?? '')) $actionExt = $p;
        elseif ($this->isAjax() and ($p = $suffix['ajax'] ?? '')) $actionExt = $p;//必须放在isPost之后
        elseif (_CLI and ($p = $suffix['cli'] ?? '')) $actionExt = $p;
        else {
            throw new EspError("非法访问请求：{$this->method}", 500);
        }
        return ucfirst($actionExt);
    }


    public function __get(string $name)
    {
        return isset($this->_var[$name]) ? $this->_var[$name] : null;
    }

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


    public function getMethod()
    {
        return $this->method;
    }

    public function isGet()
    {
        return $this->method === 'GET' && strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') !== 'xmlhttprequest';
    }

    public function isPost()
    {
        return $this->method === 'POST';
    }

    public function isCli()
    {
        return _CLI;
    }

    /**
     * 含 Get和Post
     * @return bool
     */
    public function isAjax()
    {
        return _CLI ? false : strtolower(getenv('HTTP_X_REQUESTED_WITH') ?: '') === 'xmlhttprequest';
    }

    public function ua()
    {
        return getenv('HTTP_USER_AGENT') ?: '';
    }


    /**
     * 客户端唯一标识
     * @param string $key
     * @param bool $number
     * @return int|mixed|null|string
     */
    public function cid(string $key = '_SSI', bool $number = false)
    {
        $key = strtolower($key);
        $unique = $_COOKIE[$key] ?? null;
        if (!$unique) {
            $unique = $number ? mt_rand() : \esp\helper\str_rand(20);
            if (headers_sent($file, $line)) {
                throw new EspError("Header be Send:{$file}[{$line}]", 505);
            }
            $time = time() + 86400 * 365;
            $dom = $this->cookies->domain;

            if (version_compare(PHP_VERSION, '7.3', '>')) {
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
     * @param string $agent
     * @return array ['agent' => '', 'browser' => '', 'version' => '', 'os' => '']
     */
    public function agent(string $agent = null)
    {
        $u_agent = $agent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!$u_agent) return ['agent' => '', 'browser' => '', 'version' => '', 'os' => ''];

        //操作系统
        if (preg_match('/Android/i', $u_agent)) {
            $os = 'Android';
        } elseif (preg_match('/linux/i', $u_agent)) {
            $os = 'linux';
        } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $os = 'mac';
        } elseif (preg_match('/windows|win32/i', $u_agent)) {
            $os = 'windows';
        } else {
            $os = 'Unknown';
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

    public function key()
    {
        return md5($this->ip() . getenv('HTTP_USER_AGENT') . _DOMAIN);
    }

    /**
     * 客户端IP
     * @return string
     */
    public function ip()
    {
        if (_CLI) return '127.0.0.1';
        if (defined('_CIP')) return _CIP;
        foreach (['X-REAL-IP', 'X-FORWARDED-FOR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($ip = ($_SERVER[$k] ?? null))) {
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

        $uaKey = ['iphone', 'ipad', 'ipod', 'ios'];
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




    /**
     * 从ua中提取手机品牌
     * @param null $ua
     * @return string
     */
    public function brand($ua = null)
    {
        if (is_null($ua)) $ua = (getenv('HTTP_USER_AGENT') ?: '');

        if (stripos($ua, 'Android') === false and stripos($ua, 'Windows') > 0) return 'windows';

        $OPPO_MOBILE_UA = ['oppo', "PAAM00", "PAAT00", "PACM00", "PACT00", "PADM00", "PADT00", "PAFM00", "PAFT00", "PAHM00",
            "PAHM00", "PAFT10", "PBAT00", "PBAM00", "PBAM00", "PBBM30", "PBBT30", "PBEM00", "PBET00", "PBBM00",
            "PBBT00", "PBCM10", "PBCT10", "PBCM30", "PBDM00", "PBDT00", "PBFM00", "PBFT00", "PCDM00", "PCDT00",
            "PCAM00", "PCAT00", "PCDM10", "PCDM10", "PCGM00", "PCGT00", "PCCM00", "PCCT00", "PCCT30", "PCCT40",
            "PCAM10", "PCAT10", "PCEM00", "PCET00", "PCKM00", "PCKT00", "PCHM00", "PCHT00", "PCHM10", "PCHT10",
            "PCHM30", "PCHT30", "PCLM10", "PCNM00", "PCKM00", "PCKM00", "RMX1901", "RMX1851", "RMX1971", "RMX1901",
            "RMX1851", "RMX1901", "RMX1991", "RMX1971", "RMX1931"];
        $xiaoMi = ['xiaomi', 'MIUI', 'redmi', 'MIX 2', 'MIX 3', 'MI CC', 'AWM-A0', 'SKR-A0', 'Mi-4c', 'Mi Note', 'MI PLAY', 'MI MAX', 'MI PAD', 'Mi9 Pro'];
        $huaEei = ['huawei', 'emui', 'honor'];
        $smartisan = ['smartisan', 'OD103'];//锤子手机
        $meizu = ['meizu', 'MX4 Pro'];//魅族
        $vivo = ['vivo'];
        $apple = ['Mac OS', 'iPad', 'iPhone'];//AppleWebKit

        $op = implode('|', $OPPO_MOBILE_UA);
        $xm = implode('|', $xiaoMi);
        $hw = implode('|', $huaEei);
        $cz = implode('|', $smartisan);
        $mz = implode('|', $meizu);
        $vv = implode('|', $vivo);
        $ap = implode('|', $apple);

        $auto = 'ONEPLUS|gionee|lenovo|meitu|MicroMessenger';

        if (preg_match("/(Dalvik|okhttp)/i", $ua, $mua)) {
            return 'robot';

        } else if (preg_match("/({$ap})/i", $ua, $mua)) {
            return 'apple';

        } else if (preg_match("/({$op})/i", $ua, $mua)) {
            return 'oppo';

        } else if (preg_match("/({$hw})/i", $ua, $mua)) {
            return 'huawei';

        } else if (preg_match("/({$mz})/i", $ua, $mua)) {
            return 'meizu';

        } else if (preg_match("/({$vv})/i", $ua, $mua)) {
            return 'vivo';

        } else if (preg_match("/({$cz})/i", $ua, $mua)) {
            return 'smartisan';

        } else if (preg_match("/({$xm}|mi \d)/i", $ua, $mua)) {
            return 'xiaomi';

        } elseif (preg_match("/({$auto})/i", $ua, $mua)) {
            return strtolower($mua[1]);

        } else if (preg_match('/; (v\d{4}[a-z]{1,2});? Build\/\w+/i', $ua, $mua)) {
            return 'vivo';

        } else if (preg_match('/; ([\w|\020]+?);? Build\/\w+/i', $ua, $mua)) {
            return strtolower(trim($mua[1]));
        }
        return 'unknown';
    }

}