<?php
/*
 * 公用函数
 * 本文件所有函数基本可以用在任何其他程序中
 * 但里面有几个用到了本系统定义的常量，如：_CLI,_IP_C
 *
 *
 */
/*

pre(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]);

 */

function pre(...$str)
{
    $prev = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    if (_CLI) {
        if (isset($prev['file'])) echo "{$prev['file']}[{$prev['line']}]\n";
        foreach ($str as $i => &$v) {
            print_r($v);
        }
    } else {
        if (isset($prev['file'])) {
            $file = "<i style='color:blue;'>{$prev['file']}</i><i style='color:red;'>[{$prev['line']}]</i>\n";
        } else {
            $file = null;
        }
        echo "<pre style='background:#fff;display:block;'>", $file;
        foreach ($str as $i => &$v) {
            print_r($v);
        }
        echo "</pre>";
    }
}

/**
 * 随机字符，唯一值用：uniqid(true)
 * @param int $min 最小长度
 * @param null $len 最大长度，若不填，则以$min为固定长度
 * @return mixed|string
 */
function str_rand($min = 10, $len = null)
{
    $len = $len ? mt_rand($min, $len) : $min;
    $arr = array_rand(['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0, 'H' => 0, 'I' => 0, 'J' => 0, 'K' => 0, 'L' => 0, 'M' => 0, 'N' => 0, 'O' => 0, 'P' => 0, 'Q' => 0, 'R' => 0, 'S' => 0, 'T' => 0, 'U' => 0, 'V' => 0, 'W' => 0, 'X' => 0, 'Y' => 0, 'Z' => 0, 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0, 'e' => 0, 'f' => 0, 'g' => 0, 'h' => 0, 'i' => 0, 'j' => 0, 'k' => 0, 'l' => 0, 'm' => 0, 'n' => 0, 'o' => 0, 'p' => 0, 'q' => 0, 'r' => 0, 's' => 0, 't' => 0, 'u' => 0, 'v' => 0, 'w' => 0, 'x' => 0, 'y' => 0, 'z' => 0, '0' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0, '8' => 0, '9' => 0], $len ?: 10);
    if ($len === 1) return $arr;
    shuffle($arr);
    return implode($arr);
}


function uid()
{
    $ip = ip2long(_IP_C) % 10000;
    $time = (microtime(true) * 10000) % 100000000;
    $rand = mt_rand(1000, 9999);
    $arr = str_split($ip . $time . $rand, 2);
    $str = '';
    foreach ([6, 3, 5, 1, 4, 2, 0, 7] as $i) $str .= $arr[$i];
    return $str;
}

/**
 * 生成唯一GUID，基于当前时间微秒数的唯一ID
 * @param null $fh 连接符号
 * @param int $format 格式化规则
 * @return string
 *
 * $format<10，按此数将字串分隔成等长的串，如：AC99B6F3-8F367B59-945E5971-8250D219
 * $format为2个数以上，
 * =：44888，将分成：9DD0-6CAE-C06FFA31-7D88F2A1-F2FA370D，前两节4位，后三节8位长
 * =：4470，将分成：9B50-E478-E328A69-733FF53602224E9D9，第三位7位长，最后为剩余全部
 * =：447，将分成：9B50-E478-E328A69，第三位7位长，剩下的全丢弃
 * 也就是说这些数总和不超过32，若超过32按32计算。
 * 须注意：最长为9位长，若用881284，视为8 8 1 2 8 4，中间的12视为1和2，而不视为12
 * 若需要大于10位长的，则传入数组[8,8,12,8,4]
 *
 */
function gid($fh = null, $format = 0)
{
    $md = strtoupper(md5(uniqid(mt_rand(), true)));
    if (intval($fh) > 0 and $format === 0) list($fh, $format) = [chr(45), $fh];
    elseif (intval($fh) > 0 and $format !== 0) list($fh, $format) = [$format, $fh];

    $fh = ($fh !== null) ? $fh : chr(45);// "-"
    if (!$fh or !$format) return $md;
    if (!is_array($format)) {
        if (intval($format) < 10) return wordwrap($md, $format, $fh, true);
        $format = str_split((string)$format);
    }
    $str = [];
    $j = 0;
    for ($i = 0; $i < count($format); $i++) {
        if ($format[$i] > 0) {
            $str[] = substr($md, $j, $format[$i]);
        } else {
            $str[] = substr($md, $j);
        }
        $j += $format[$i];
        if ($j > 31) break;
    }
    return implode($fh, $str);
}

/**
 * 是否手机访问
 * 0不是
 * 1是手机
 * 2是微信
 * @return bool
 */
function is_wap()
{
    $browser = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (empty($browser)) return 0;
    if (strripos($browser, "MicroMessenger")) return 2;//微信
    if (stripos($browser, "mobile") || stripos($browser, "android")) return 1;
    if (isset($_SERVER['HTTP_VIA']) or isset($_SERVER['HTTP_X_NOKIA_CONNECTION_MODE']) or isset($_SERVER['HTTP_X_UP_CALLING_LINE_ID'])) return 1;
    if (stripos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML") > 0) return 1;
    $browser = substr($browser, 0, 4);
    $mobs = ['Noki', 'Eric', 'WapI', 'MC21', 'AUR ', 'R380', 'UP.B', 'WinW', 'UPG1', 'upsi', 'QWAP', 'Jigs', 'Java', 'Alca', 'MITS', 'MOT-', 'My S', 'WAPJ', 'fetc', 'ALAV', 'Wapa', 'Oper'];
    return in_array($browser, $mobs) ? 1 : 0;
}

function is_wechat()
{
    return is_wap() === 2;
}


/**
 *
 * 产生一个错误信息，具体处理，由\plugins\Mistake处理
 * @param $str
 * @param int $level 错误级别，012，
 *
 * 0：系统停止执行，严重级别
 * 1：提示错误，继续运行
 * 2：警告级别，在生产环境中不提示，仅发给管理员
 *
 * error("{$filePath} 不是有效文件。");
 */
function error($str, $level = 0, array $errFile = null)
{
    if (is_int($str)) goto state;
    if (is_array($level)) list($level, $errFile) = [0, $level];

    $err = $errFile ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    \esp\core\Mistake::try_error($str, $level, $err);
    return;

    state:  //模拟成某个错误状态

//    $err = $errFile ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
//    pre($err);

    $state = \esp\core\Config::states($str);
    $server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : null;
    $html = "<html>\n<head><title>{$str} {$state}</title></head>\n<body bgcolor=\"white\">\n<center><h1>{$str} {$state}</h1></center>\n<hr><center>{$server}</center>\n</body>\n</html>";
    header_state($str, $state);
    header('Content-type:text/html', true);
    exit($html);
}


/**
 * 设置HTTP响应头
 * @param int $code
 * @param null $text
 * @throws Exception
 */
function header_state($code = 200, $text = null)
{
    if (empty($code) OR !is_numeric($code)) {
        error('状态码必须为数字');
    }
    if (empty($text)) {
        $text = \esp\core\Config::states($code);
    }
    if (!stripos(PHP_SAPI, 'cgi')) {
        header('Status: ' . $code . ' ' . $text, true);
    } else {
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header("{$protocol} {$code} {$text}", true, $code);
    }
}

/**
 * 分析客户端信息
 * @param null $agent
 * @return array ['agent' => '', 'browser' => '', 'version' => '', 'os' => '']
 *
 * ab压力测试： ApacheBench/2.3
 */
function agent($agent = null)
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
 * 判断是否为手机号码
 * @param $mobNumber
 * @param bool|false $Zero 是否允许手机号为0
 * @return bool
 */
function is_mob($mobNumber, $Zero = false)
{
    return ($mobNumber === 0 and $Zero) or preg_match('/^1[34578]\d{9}$/', $mobNumber);
}

function is_mail($eMail)
{
    return preg_match('/^\w+([-\.]\w+)*@\w+([-\.]\w+)*\.\w+([-\.]\w+)*$/', $eMail);
}

function is_date($date)
{
    return preg_match('/^(?:(?:1[789]\d{2}|2[012]\d{2})[-\/](?:(?:0?2[-\/](?:0?1\d|2[0-8]))|(?:0?[13578]|10|12)[-\/](?:[012]?\d|3[01]))|(?:(?:0?[469]|11)[-\/](?:[012]?\d|30)))|(?:(?:1[789]|2[012])(?:[02468][048]|[13579][26])[-\/](?:0?2[-\/]29))$/', $date);
}


/**
 * 是否搜索蜘蛛人
 * @return bool
 */
function is_spider()
{
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $keys = ['bot', 'slurp', 'spider', 'crawl', 'curl', 'mediapartners-google', 'fast-webcrawler', 'altavista', 'ia_archiver'];
        foreach ($keys as &$key) {
            if (!!strripos($agent, $key)) return true;
        }
    }
    return false;
}


/**
 * 身份证号码检测，区分闰年，较验最后识别码
 * @param $number
 * @return bool
 */
function is_card($number)
{
    $png = '/^\d{6}(?:(?:(?:19\d{2}|20[01]\d)(?:(?:02(?:01\d|2[0-8]))|(?:0[13578]|10|12)(?:[012]?\d|3[01]))|(?:(?:0[469]|11)(?:[012]\d|30)))|(?:(?:19(?:[02468][048]|[13579][26])|20[0][048]|20[1][26])(?:0229)))\d{3}(\d|x|x)$/';
    if (!preg_match($png, $number, $mac)) return false;
    $total = 0;
    $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
    for ($i = 0; $i < 17; $i++) $total += (int)substr($number, $i, 1) * $factor[$i];
    return strtoupper($mac[1]) == ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'][$total % 11];
}

/**
 * 将字符串分割成1个字的数组，主要用于中英文混合时，将中英文安全的分割开
 * @param $str
 * @return array
 */
function str_cut($str)
{
    $len = mb_strlen($str);
    $arr = [];
    for ($i = 0; $i < $len; $i++) {
        $arr[] = mb_substr($str, $i, 1, "utf8");
    }
    return $arr;
}

function str_len($str)
{
    $len = mb_strlen($str);
    $arr = [];
    for ($i = 0; $i < $len; $i++) {
        $arr[] = mb_substr($str, $i, 1, "utf8");
    }
    return $arr;
}

/**
 * 查询域名的根域名，兼容国别的二级域名
 * @param null $domain
 * @return null|string
 */
function host($domain)
{
    $dm1 = 'cn|cm|my|ph|tw|uk|hk';
    $dm2 = 'com|net|org|gov|idv|co|name';
    if (strpos('/', $domain)) {
        $domain = explode('/', $domain . '//')[2];
    }
    if (preg_match("/^(?:[\w\.\-]+\.)?([a-z]+)\.({$dm2})\.({$dm1})$/i", $domain, $match)) {
        return "{$match[1]}.{$match[2]}.{$match[3]}";
    } elseif (preg_match("/^(?:[\w\.\-]+\.)?([a-z]+)\.([a-z]+)$/i", $domain, $match)) {
        return "{$match[1]}.{$match[2]}";
    } else {
        return null;
    }
}


function server($key, $auto = null)
{
    $key = strtoupper($key);
    return isset($_SERVER[$key]) ? $_SERVER[$key] : $auto;
}


function load($file)
{
    if (!$file) return false;
    return @include_once root($file);
}

/**
 * 修正为_ROOT开头
 * @param string $path 若最后一个参数是true，则在返回结果后加/
 * @return string|array
 */
function root(...$path)
{
    $len = count($path);
    $folder = false;
    if (is_bool($path[$len - 1])) {
        $folder = $path[$len - 1];
        unset($path[$len - 1]);
    }
    foreach ($path as $i => &$p) {
        if (stripos($p, _ROOT) !== 0) $p = _ROOT . ltrim($p, '/');
        if ($folder) $p = rtrim($p, '/') . '/';
    }
    return count($path) === 1 ? $path[0] : $path;
}


/**
 * 计算一个偶数的组成，比如：10=8+2，14=8+4+2，22=16+4+2。
 * @param $value
 * @return array
 */
function numbers($value)
{
    if ($value % 2 != 0) return [];
    $val = [];
    $i = 0;
    while (true) {
        if (2 << $i > $value) break;
        $i++;
    }
    for ($j = $i; $j >= 0; $j--) {
        if (2 << $j > $value) continue;
        $val[] = 2 << $j;
        $value -= 2 << $j;
        if ($value <= 0) break;
    }
    return $val;
}


/**
 * 获取并验证用户的IP地址
 * $forced=true   强制返回真实IP
 * @return    string
 */
function ip()
{
    if (_CLI) return '127.0.0.1';
    $keys = ['x-real-ip', 'x-forwarded-for', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header]) && is_ip($_SERVER[$header])) return $_SERVER[$header];
    }
    return '127.0.0.1';
}

function is_ip($ip, $which = 'ipv4')
{
    switch (strtolower($which)) {
        case 'ipv4':
            $which = FILTER_FLAG_IPV4;
            break;
        case 'ipv6':
            $which = FILTER_FLAG_IPV6;
            break;
        default:
            $which = NULL;
            break;
    }
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, $which);
}


/**
 * 跳转页面
 * @param string $url
 * @param bool|false $js 用JS方式跳，top
 */
function go($url, $js = false)
{
    if ($js) {
        echo "<script>top.location.href='{$url}';</script>";
    } else {
//        header('HTTP/1.1 301 Moved Permanently');
        header('Location:' . $url, true, 301);
    }
    exit;
}

/**
 * @param $path
 * @param int $mode
 * @return bool
 * 文件权限：
 * 类型   所有者  所有者组    其它用户
 * r    read    4
 * w    write   2
 * x    exec    1
 * 通过PHP建立的文件夹权限一般为0740就可以了
 */
function mk_dir($path, $mode = 0740)
{
    if (!$path) return false;
    if (strrchr($path, '/')) $path = dirname($path);
    if (file_exists($path)) return true;
    if (!$mode) $mode = 0740;
    try {
        @mkdir($path, $mode, true);
        return true;
    } catch (\Exception $e) {
        return true;
    }
}

/**
 * 储存文件
 * @param $file
 * @param $content
 * @return int
 */
function save_file($file, $content)
{
    if (is_array($content)) $content = json_encode($content, 256);
    mk_dir($file);
    return file_put_contents($file, $content, LOCK_EX);
}

/**
 * GB2312转UTF8
 * @param $str
 * @return string
 */
function utf8($str)
{
    return iconv('GB2312', 'UTF-8//IGNORE', $str);
}

function unicode_utf8($code)
{
    $str = [];
    $cs = str_split($code, 4);
    foreach ($cs as &$c) {
        $code = intval(hexdec($c));
        $c1 = decbin(0xe0 | ($code >> 12));
        $c2 = decbin(0x80 | (($code >> 6) & 0x3f));
        $c3 = decbin(0x80 | ($code & 0x3f));
        $str[] = chr(bindec($c1)) . chr(bindec($c2)) . chr(bindec($c3));
    }
    return implode($str);
}


/**
 * HTML截取
 * str_ireplace中最后几个空白符号，不是空格，是一些特殊空格
 * @param $html
 * @param int $star
 * @param int $stop
 * @return string
 * strip_tags
 */
function text($html, $star = null, $stop = null)
{
    if ($stop === null) list($star, $stop) = [0, $star];
    $v = preg_replace(['/\&lt\;(.*?)\&gt\;/is', '/<(.*?)>/is', '/[\s\x20\xa\xd\'\"\`]/is'], '', trim($html));
    $v = str_ireplace(["\a", "\b", "\f", "\s", "\t", "\n", "\v", "\0", "\h", " ", "　", "	"], '', $v);
    return htmlentities(mb_substr($v, $star, $stop, 'utf-8'));
}


//数字介于之间
function between(int $n, int $a, int $b, bool $han = true)
{
    return $han ? ($n >= $a and $n <= $b) : ($n > $a and $n < $b);
}


/**
 * 将12k,13G转换为字节长度
 * @param $size
 * @return mixed
 */
function re_size($size)
{
    return preg_replace_callback('/(\d+)([kmGt])b?/i', function ($matches) {
        switch (strtolower($matches[2])) {
            case 'k':
                return $matches[1] * 1024;
            case 'm':
                return $matches[1] * pow(1024, 2);
            case 'g':
                return $matches[1] * pow(1024, 3);
            case 't':
                return $matches[1] * pow(1024, 4);
            default:
                return $matches[1] * 1;
        }
    }, $size) * 1;
}


function image_type($ext)
{
    $file = [];
    $file['gif'] = IMAGETYPE_GIF;
    $file['jpg'] = IMAGETYPE_JPEG;
    $file['jpeg'] = IMAGETYPE_JPEG;
    $file['png'] = IMAGETYPE_PNG;
    $file['swf'] = IMAGETYPE_SWF;
    $file['psd'] = IMAGETYPE_PSD;
    $file['bmp'] = IMAGETYPE_BMP;
    $file['wbmp'] = IMAGETYPE_WBMP;
    $file['bmp'] = IMAGETYPE_XBM;
    return isset($file[$ext]) ? $file[$ext] : null;
}

/**
 * 格式化字符串
 * @param $str
 * @return mixed
 */
function format($str)
{
    return preg_replace_callback('/\%([a-z])/i', function ($matches) {
        switch ($matches[1]) {
            case 'D':       //从2015-8-3算起的天数
                return ceil((time() - 1438531200) / 86400);
            case 'u':       //唯一性值
                return uniqid(true);
            case 'r':       //随机数
                return mt_rand(1000, 9999);
            default:        //用data
                return date($matches[1]);
        }
    }, $str);
}

/**
 * 对IMG转码，返回值可以直接用于<img src>
 * @param $file
 * @return null|string
 * chunk_split
 */
function img_code($file, $split = false)
{
    if (!is_readable($file)) return null;
    $ext = image_type_to_extension(exif_imagetype($file), false);
    if (!$ext) return null;
    $file_content = base64_encode(file_get_contents($file));
    if ($split) $file_content = chunk_split($file_content);
    return "data:image/{$ext};base64,{$file_content}";
}


/**
 * XML解析
 * @param $str
 * @return bool|mixed
 */
function xml_parser($str)
{
    libxml_disable_entity_loader(true);
    $xml_parser = xml_parser_create();
    if (!xml_parse($xml_parser, $str, true)) {
        xml_parser_free($xml_parser);
        return false;
    } else {
        $xml = @simplexml_load_string($str, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xml, 256), true);
    }
}


/**
 * 时间友好型提示风格化（即XXX小时前、昨天等等）
 * @param int $timestamp
 * @param int|null $time_now
 * @return string
 */
function date_friendly(int $timestamp, int $time_now = null)
{
    $Q = $timestamp > time() ? '后' : '前';
    $V = $T = null;
    $S = abs((($time_now ?: time()) - $timestamp) ?: 1) and $V = 'S' and $T = '秒';
    $I = floor($S / 60) and $V = 'I' and $T = '分钟';
    $H = floor($I / 60) and $V = 'H' and $T = '小时';
    $D = intval($H / 24) and $V = 'D' and $T = '天';
    $M = intval($D / 30) and $V = 'M' and $T = '个月';
    $Y = intval($M / 12) and $V = 'Y' and $T = '年';
    if ($D === 1) return '昨天';
    if ($D === 2) return '前天';
    if ($M === 1) return '上个月';
    if ($Y === 1) return '去年';
    if ($Y === 2) return '前年';
    return sprintf("%s{$T}{$Q}", ${$V});
}







//END