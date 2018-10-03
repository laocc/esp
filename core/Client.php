<?php

namespace esp\core;


final class Client
{

    /**
     * 客户端唯一标识
     * @param string $key
     * @return mixed|null|string
     */
    public static function id(string $key = '_SSI')
    {
        $unique = Session::id();
        if (empty($unique)) $unique = $_COOKIE[$key] ?? null;

        if (!$unique) {
            $unique = self::str_rand(20);
            $time = time() + 86400 * 365;
            if (headers_sent()) return $unique;
            (setcookie($key, $unique, $time, '/', _HOST, true, true));
            (setcookie($key, $unique, $time, '/', _HOST, false, true));
        }
        return $unique;
    }


    /**
     * 客户端标识
     * @return string
     */
    public static function ua()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }


    /**
     * 分析客户端信息
     * @param null $agent
     * @return array ['agent' => '', 'browser' => '', 'version' => '', 'os' => '']
     */
    public static function agent(string $agent = null)
    {
        $u_agent = $agent ?: isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
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


    /**
     * 客户端IP
     * @return string
     */
    public static function ip()
    {
        if (_CLI) return '127.0.0.1';
        foreach (['X-REAL-IP', 'X-FORWARDED-FOR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($ip = ($_SERVER[$k] ?? null))) {
                if (strpos($ip, ',')) $ip = explode(',', $ip)[0];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) break;
            }
        }
        return $ip ?? '127.0.0.1';
    }


    /**
     * 是否搜索蜘蛛人
     * @return bool
     */
    public static function is_spider()
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
     * 是否手机端
     * @param string|null $browser
     * @return bool
     */
    public static function is_wap(string $browser = null): bool
    {
        $browser = $browser ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (empty($browser)) return true;

        $uaKey = ['MicroMessenger', 'android', 'mobile', 'iphone', 'ipad', 'ipod', 'opera mini', 'windows ce', 'windows mobile', 'symbianos', 'ucweb', 'netfront'];
        foreach ($uaKey as $i => $k) if (stripos($browser, $k)) return true;

        $mobKey = ['Noki', 'Eric', 'WapI', 'MC21', 'AUR ', 'R380', 'UP.B', 'WinW', 'UPG1', 'upsi', 'QWAP', 'Jigs', 'Java', 'Alca', 'MITS', 'MOT-', 'My S', 'WAPJ', 'fetc', 'ALAV', 'Wapa', 'Oper'];
        if (in_array(substr($browser, 0, 4), $mobKey)) return true;

        $isWap = ['HTTP_X_WAP_PROFILE', 'HTTP_UA_OS', 'HTTP_VIA', 'HTTP_X_NOKIA_CONNECTION_MODE', 'HTTP_X_UP_CALLING_LINE_ID'];
        foreach ($isWap as $i => $k) if (isset($_SERVER[$k])) return true;

        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'vnd.wap')) return true;

        return false;
    }

    /**
     * 是否支付宝APP
     * @return bool
     */
    public static function is_alipay(): bool
    {
        return stripos(($_SERVER['HTTP_USER_AGENT'] ?? ''), 'AlipayClient') > 0;
    }

    /**
     * 是否微信端
     * @return bool
     */
    public static function is_wechat(): bool
    {
        return stripos(($_SERVER['HTTP_USER_AGENT'] ?? ''), 'MicroMessenger') > 0;
    }


    /**
     * 当前客户端是否真实浏览器，注意：这是本人瞎写的，判断起来不保证百分百准确
     * @return bool
     */
    public static function is_Mozilla(): bool
    {
        $v = preg_match_all('/([A-Z][a-zA-Z]{4,15}\/\d+\.+\d+)+/', $_SERVER['HTTP_USER_AGENT'] ?? '', $mac);
        if (!$v or !isset($mac[1]) or count($mac[1]) < 3) return false;

        //如果这几个基本参数少于4个，基本可以确定为非真实浏览器
        $check = ['HTTP_COOKIE', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING', 'HTTP_UPGRADE_INSECURE_REQUESTS', 'HTTP_CACHE_CONTROL', 'HTTP_CONNECTION'];
        $c = 0;
        foreach ($check as $k) if (isset($_SERVER[$k])) $c++;
        return ($c > (count($check) * 0.5));
    }


    /**
     * 产生随机字符串
     * @param int $min
     * @param null $len
     * @return mixed|string
     */
    private static function str_rand(int $min = 10, int $len = null): string
    {
        $len = $len ? mt_rand($min, $len) : $min;
        $arr = array_rand(['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0, 'H' => 0, 'I' => 0, 'J' => 0, 'K' => 0, 'L' => 0, 'M' => 0, 'N' => 0, 'O' => 0, 'P' => 0, 'Q' => 0, 'R' => 0, 'S' => 0, 'T' => 0, 'U' => 0, 'V' => 0, 'W' => 0, 'X' => 0, 'Y' => 0, 'Z' => 0, 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0, 'e' => 0, 'f' => 0, 'g' => 0, 'h' => 0, 'i' => 0, 'j' => 0, 'k' => 0, 'l' => 0, 'm' => 0, 'n' => 0, 'o' => 0, 'p' => 0, 'q' => 0, 'r' => 0, 's' => 0, 't' => 0, 'u' => 0, 'v' => 0, 'w' => 0, 'x' => 0, 'y' => 0, 'z' => 0, '0' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0, '8' => 0, '9' => 0], $len ?: 10);
        shuffle($arr);//将数组打乱
        return implode($arr);
    }

}