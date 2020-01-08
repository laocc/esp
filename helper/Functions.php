<?php

/**
 * @param array ...$str
 */
function pre(...$str)
{
    $prev = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    if (_CLI) {
        if (isset($prev['file'])) echo "{$prev['file']}[{$prev['line']}]\n";
        foreach ($str as $i => &$v) print_r($v);
    } else {
        unset($prev['file']);
        if (isset($prev['file'])) {
            $file = "<i style='color:blue;'>{$prev['file']}</i><i style='color:red;'>[{$prev['line']}]</i>\n";
        } else {
            $file = null;
        }
        echo "<pre style='background:#fff;display:block;'>", $file;
        foreach ($str as $i => &$v) {
            if (is_array($v)) print_r($v);
            elseif (is_string($v) and !empty($v) and ($v[0] === '[' or $v[0] === '{')) echo($v);
            else var_dump($v);
        }
        echo "</pre>";
    }
}


/**
 * CLI环境中打印彩色字
 * @param $text
 * @param null $bgColor
 * @param null $ftColor
 */
function _echo($text, string $bgColor = null, string $ftColor = null)
{
    if (is_array($text)) $text = print_r($text, true);
    $text = trim($text, "\n");
    if (defined('_SWOOLE_HIDE') and _SWOOLE_HIDE === true) {
        echo $text . "\n";
        return;
    }
    $front = ['green' => 32, 'g' => 32, 'red' => 31, 'r' => 31, 'yellow' => 33, 'y' => 33, 'blue' => 34, 'b' => 34, 'white' => 37, 'w' => 37, 'black' => 30, 'h' => 30];
    $ground = ['green' => 42, 'g' => 42, 'red' => 41, 'r' => 41, 'yellow' => 43, 'y' => 43, 'blue' => 44, 'b' => 44, 'white' => 47, 'w' => 47, 'black' => 40, 'h' => 40];
    $color = '[' . ($ground[$bgColor] ?? 40) . ';' . ($front[$ftColor] ?? 37) . 'm';//默认黑底白字
    echo chr(27) . $color . $text . chr(27) . "[0m\n";
}


/**
 * 过滤用于sql的敏感字符，建议用Xss::clear()处理
 * @param string $str
 * @return string
 */
function safe_replace(string $str): string
{
    if (empty($str)) return '';
    return preg_replace('/[\"\'\%\&\$\#\(\)\[\]\{\}\?]/', '', $str);
}


/**
 * 查询域名的根域名，兼容国别的二级域名
 * @param $domain
 * @return string
 */
function host(string $domain): string
{
    if (empty($domain)) return '';
    $dm1 = 'cn|cm|my|ph|tw|uk|hk';
    $dm2 = 'com|net|org|gov|idv|co|name';
    if (strpos('/', $domain)) $domain = explode('/', "{$domain}//")[2];

    if (preg_match("/^(?:[\w\.\-]+\.)?([a-z]+)\.({$dm2})\.({$dm1})$/i", $domain, $match)) {
        return "{$match[1]}.{$match[2]}.{$match[3]}";

    } elseif (preg_match("/^(?:[\w\.\-]+\.)?([a-z0-9]+)\.([a-z]+)$/i", $domain, $match)) {
        return "{$match[1]}.{$match[2]}";

    } else {
        return '';
    }
}

/**
 * 提取URL中的域名
 * @param $url
 * @return null
 */
function domain(string $url): string
{
    if (empty($url)) return '';
    if (substr($url, 0, 4) !== 'http') return $url;
    return explode('/', "{$url}//")[2];
}


/**
 * 加载文件，同时加载结果被缓存
 * @param $file
 * @return bool|mixed
 */
function load(string $file)
{
    $file = root($file);
    if (!$file or !is_readable($file)) return false;
    static $recode = Array();
    $md5 = md5($file);
    if (isset($recode[$md5])) return $recode[$md5];
    $recode[$md5] = include $file;
    return $recode[$md5];
}

/**
 * 修正为_ROOT开头
 * @param string $path
 * @return string|array
 */
function root(string $path, bool $real = false): string
{
    if (stripos($path, '/home/') === 0) {
        if ($real) $path = realpath($path);
        return rtrim($path, '/');
    } else if (stripos($path, '/mnt/') === 0) {
        if ($real) $path = realpath($path);
        return rtrim($path, '/');
    }
    if (stripos($path, _ROOT) !== 0) $path = _ROOT . "/" . trim($path, '/');
    if ($real) $path = realpath($path);
    return rtrim($path, '/');
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
function mk_dir(string $path, int $mode = 0740): bool
{
    if (!$path) return false;
    if (strrchr($path, '/')) $path = dirname($path);
    try {
        if (!is_dir($path)) {
            $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            @mkdir($path, $mode ?: 0740, true);
        }
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * 储存文件
 * @param $file
 * @param $content
 * @return int
 */
function save_file(string $file, string $content, bool $append = false): int
{
    if (is_array($content)) $content = json_encode($content, 256);
    mk_dir($file);
    return file_put_contents($file, $content, $append ? FILE_APPEND : LOCK_EX);
}

/**
 * 设置HTTP响应头
 * @param int $code
 * @param null $text
 * @throws Exception
 */
function header_state(int $code = 200, string $text = null)
{
    if (empty($code) OR !is_numeric($code)) {
        throw new \Exception('状态码必须为数字');
    }
    if (empty($text)) {
        $text = \esp\core\Config::states($code);
    }
    if (!stripos(PHP_SAPI, 'cgi')) {
        header("Status: {$code} {$text}", true);
    } else {
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header("{$protocol} {$code} {$text}", true, $code);
    }
}

/**
 * 返回字符的 ASCII 码值
 * @param string $string
 * @return array
 */
function string_ord(string $string): array
{
    $val = Array();
    $arr = str_split($string);
    foreach ($arr as $s) {
        $val[] = ord($s);
    }
    return $val;
}

/**
 * 十进制转换二进制，不足4位的前面补0
 * @param int $num
 * @param bool $space 是否分割每4位一节
 * @return string
 */
function dec_bin(int $num, bool $space = true): string
{
    if ($len = strlen($bin = decbin($num)) % 4) $bin = str_repeat('0', 4 - $len) . $bin;
    if (!$space) return $bin;
    return implode(' ', str_split($bin, 4));
}

/**
 * 清除BOM
 * @param $loadStr
 */
function clearBom(&$loadStr)
{
    if (ord(substr($loadStr, 0, 1)) === 239 and ord(substr($loadStr, 1, 1)) === 187 and ord(substr($loadStr, 2, 1)) === 191)
        $loadStr = substr($loadStr, 3);
}

/**
 * XML解析成数组或对象
 * @param $str
 * @return bool|mixed
 */
function xml_decode(string $str, bool $toArray = true)
{
    if (!$str) return null;
    $xml_parser = xml_parser_create();
    if (!xml_parse($xml_parser, $str, true)) {
        xml_parser_free($xml_parser);
        return null;
    }
    return json_decode(json_encode(@simplexml_load_string($str, "SimpleXMLElement", LIBXML_NOCDATA)), $toArray);
}


/**
 * 将数组转换成XML格式
 * @param $root
 * @param array $array
 * @param bool $outHead
 * @return string
 * @throws Exception
 */
function xml_encode($root, array $array, bool $outHead = true)
{
    return (new \esp\library\ext\Xml($array, $root))->render($outHead);
}


/**
 * 格式化小数
 * @param $amount
 * @param int $len
 * @return string
 */
function rnd(float $amount, int $len = 2, bool $zero = true): string
{
    if (!$amount and !$zero) return '';
    return sprintf("%.{$len}f", $amount);
}

/**
 * 读取CPU数量信息
 * @return array
 */
function get_cpu()
{
    $str = file_get_contents("/proc/cpuinfo");
    if (!$str) return ['number' => 0, 'name' => 'null'];
    $cpu = [];
    if (preg_match_all("/model\s+name\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $str, $model)) {
        $cpu['number'] = count($model[1]);
        $cpu['name'] = $model[1][0];
    }
    return $cpu;
}

/**
 * @param $number
 * @param int $len
 * @param string $add
 * @param string $lr
 * @return string
 *
 * %% - 返回一个百分号 %
 * %b - 二进制数
 * %c - ASCII 值对应的字符
 * %d - 包含正负号的十进制数（负数、0、正数）
 * %e - 使用小写的科学计数法（例如 1.2e+2）
 * %E - 使用大写的科学计数法（例如 1.2E+2）
 * %u - 不包含正负号的十进制数（大于等于 0）
 * %f - 浮点数（本地设置）
 * %F - 浮点数（非本地设置）
 * %g - 较短的 %e 和 %f
 * %G - 较短的 %E 和 %f
 * %o - 八进制数
 * %s - 字符串
 * %x - 十六进制数（小写字母）
 * %X - 十六进制数（大写字母）
 * 附加的格式值。必需放置在 % 和字母之间（例如 %.2f）：
 * + （在数字前面加上 + 或 - 来定义数字的正负性。默认情况下，只有负数才做标记，正数不做标记）
 * ' （规定使用什么作为填充，默认是空格。它必须与宽度指定器一起使用。例如：%'x20s（使用 "x" 作为填充））
 * - （左调整变量值）
 * [0-9] （规定变量值的最小宽度）
 * .[0-9] （规定小数位数或最大字符串长度）
 * 注释：如果使用多个上述的格式值，它们必须按照以上顺序使用。
 */
function full(string $number, int $len = 2, string $add = '0', string $lr = 'left'): string
{
    if (in_array($add, ['left', 'right', 'l', 'r'])) list($add, $lr) = ['0', $add];
    $fh = ($lr === 'left') ? '' : '-';//减号右补，无减号为左补
    return sprintf("%{$fh}'{$add}{$len}s", $number);
}


/**
 * 随机字符，唯一值用：uniqid(true)
 * @param int $min 最小长度
 * @param int|null $max 最大长度，若不填，则以$min为固定长度
 * @return string
 */
function str_rand(int $min = 10, int $max = null): string
{
    $max = $max ? mt_rand($min, $max) : $min;
    if ($max > 60) $max = 60;
    $arr = array_rand(['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0, 'H' => 0, 'I' => 0, 'J' => 0, 'K' => 0, 'L' => 0, 'M' => 0, 'N' => 0, 'O' => 0, 'P' => 0, 'Q' => 0, 'R' => 0, 'S' => 0, 'T' => 0, 'U' => 0, 'V' => 0, 'W' => 0, 'X' => 0, 'Y' => 0, 'Z' => 0, 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0, 'e' => 0, 'f' => 0, 'g' => 0, 'h' => 0, 'i' => 0, 'j' => 0, 'k' => 0, 'l' => 0, 'm' => 0, 'n' => 0, 'o' => 0, 'p' => 0, 'q' => 0, 'r' => 0, 's' => 0, 't' => 0, 'u' => 0, 'v' => 0, 'w' => 0, 'x' => 0, 'y' => 0, 'z' => 0, '0' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0, '8' => 0, '9' => 0], $max ?: 10);
    if ($max === 1) return $arr;
    shuffle($arr);//取得的字串是顺序的，要打乱
    return implode($arr);
}


/**
 * 查询某年第n周的星期一是哪天
 * @param int $week
 * @param int $year
 * @return string
 */
function week_from(int $week = 0, int $year = 0): string
{
    if (!$year) {
        $year = intval(date('Y'));
    } elseif ($week > 60) {
        list($week, $year) = [$year, $week];
    }
    if ($week > 60) return '';
    $yTime = strtotime("{$year}-01-01");//元旦当天时间戳
    $yWeek = intval(date('W', $yTime));//元旦当天处于第多少周
    $yWeekD = intval(date('N', $yTime));//元旦当天是星期几
    if ($yWeek === 1) {//当天是第一周，则要查这一周的星期一是哪天
        $yTime -= (($yWeekD - 1) * 86400);
    } else {//上年的最后一周
        $yTime += ((8 - $yWeekD) * 86400);
    }
    $yTime += (($week - 1) * 7 * 86400);
    return date('Y-m-d', $yTime);
}

/**
 * 某一周所有的天日期
 * @param int $week
 * @param int $year
 * @return array
 */
function week_days(int $week = 0, int $year = 0): array
{
    if (!$year) {
        $year = intval(date('Y'));
    } elseif ($week > 60) {
        list($week, $year) = [$year, $week];
    }
    if ($week > 60) return [];
    $yTime = strtotime("{$year}-01-01");//元旦当天时间戳
    $yWeek = intval(date('W', $yTime));//元旦当天处于第多少周
    $yWeekD = intval(date('N', $yTime));//元旦当天是星期几
    if ($yWeek === 1) {//当天是第一周，则要查这一周的星期一是哪天
        $yTime -= (($yWeekD - 1) * 86400);
    } else {//上年的最后一周
        $yTime += ((8 - $yWeekD) * 86400);
    }
    $yTime += (($week - 1) * 7 * 86400);
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = date('Y-m-d', ($yTime + ($i * 86400)));
    }
    return $days;
}

/**
 * 查询某年最后一周是第多少周，或某年共多少周
 * @param int $year
 * @return int
 */
function week_last(int $year): int
{
    $tim = strtotime("{$year}-12-31");
    $week = intval(date('W', $tim));
    if ($week === 1) {
        $week = intval(date('W', $tim - intval(date('N', $tim)) * 86400));
    }
    return $week;
}

/**
 * 相差天数，a>b时为负数
 * @param int $a
 * @param int $b
 * @return int
 */
function diff_day(int $a, int $b)
{
    $interval = date_diff(date_create(date('Ymd', $a)), date_create(date('Ymd', $b)));
    return intval($interval->format('%R%a'));
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
    $str = Array();
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
 * 是否为手机号码
 * @param $mobNumber
 * @return bool
 */
function is_mob(string $mobNumber): bool
{
    if (empty($mobNumber)) return false;
    return preg_match('/^1[3456789]\d{9}$/', $mobNumber);
}

/**
 * 电子邮箱地址格式
 * @param string $eMail
 * @return bool
 */
function is_mail(string $eMail): bool
{
    if (empty($eMail)) return false;
    return (bool)filter_var($eMail, FILTER_VALIDATE_EMAIL);
//    return preg_match('/^\w+([-\.]\w+)*@\w+([-\.]\w+)*\.\w+([-\.]\w+)*$/', $eMail);
}

/**
 * 是否一完网址
 * @param string $url
 * @return bool
 */
function is_url(string $url): bool
{
    if (empty($url)) return false;
    return (bool)filter_var($url, FILTER_VALIDATE_URL);
//    return preg_match('/^https?\:\/\/[\w\-]+(\.[\w\-]+)+\/.+$/i', $url);
}


/**
 * 是否URI格式
 * @param string $string
 * @return bool
 */
function is_uri(string $string): bool
{
    if (empty($string)) return false;
    return (bool)filter_var($string, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^(\/[\w\-\.\~]*)?(\/.+)*$/i']]);
}


/**
 * 日期格式：2015-02-05 或 20150205
 * @param string $day
 * @return bool
 */
function is_date(string $day): bool
{
    if (empty($day)) return false;
    if (1) {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $day, $mac)) {
            return checkdate($mac[2], $mac[3], $mac[1]);
        } elseif (preg_match('/^(\d{4})(\d{1,2})(\d{1,2})$/', $day, $mac)) {
            return checkdate($mac[2], $mac[3], $mac[1]);
        } else {
            return false;
        }
    } else {
        return preg_match('/^(?:(?:1[789]\d{2}|2[012]\d{2})[-\/](?:(?:0?2[-\/](?:0?1\d|2[0-8]))|(?:0?[13578]|10|12)[-\/](?:[012]?\d|3[01]))|(?:(?:0?[469]|11)[-\/](?:[012]?\d|30)))|(?:(?:1[789]|2[012])(?:[02468][048]|[13579][26])[-\/](?:0?2[-\/]29))$/', $day);
    }
}

/**
 * 时间格式：12:23:45
 * @param string $time
 * @return bool
 */
function is_time(string $time): bool
{
    if (empty($time)) return false;
    return preg_match('/^([0-1]\d|2[0-3])(\:[0-5]\d){2}$/', $time);
}

/**
 * 字串是否为正则表达式
 * @param $string
 * @return bool
 */
function is_match(string $string): bool
{
    if (empty($string)) return false;
    return (bool)filter_var($string, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([\/\#\@\!\~])\^?.+\$?\1[imUuAsDSXxJ]{0,3}$/i']]);
}

/**
 * 是否mac码
 * @param $mac
 * @return bool
 */
function is_mac(string $mac): bool
{
    if (empty($mac)) return false;
    return (bool)filter_var($mac, FILTER_VALIDATE_MAC);
}


/**
 * @param string $ip
 * @param string $which
 * @return bool
 */
function is_ip(string $ip, string $which = 'ipv4'): bool
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

function is_domain(string $domain): bool
{
    return preg_match('/^[a-z0-9](([a-z0-9-]){1,62}\.)+[a-z]{2,20}$/i', $domain);
}

/**
 * 身份证号码检测，区分闰年，较验最后识别码
 * @param $number
 * @return bool
 */
function is_card(string $number): bool
{
    if (empty($number)) return false;
    if (!preg_match('/^\d{6}(\d{8})\d{3}(\d|x)$/i', $number, $mac)) return false;
    if (!is_date($mac[1])) return false;
    return strtoupper($mac[2]) === make_card($number);
}


/**
 * 生成身份证最后一位识别码
 * @param $zone
 * @param string $day
 * @param string $number
 * @return mixed
 */
function make_card($zone, $day = '', $number = '')
{
    $body = "{$zone}{$day}{$number}";
    $wi = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);//加权因子
    $sigma = 0;
    for ($i = 0; $i < 17; $i++) {
        $sigma += intval($body{$i}) * $wi[$i]; //把从身份证号码中提取的一位数字和加权因子相乘，并累加
    }
    $ai = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');//校验码串
    return $ai[$sigma % 11]; //按照序号从校验码串中提取相应的字符。
}


/**
 * 根据权重随机选择一个值
 * @param array $array
 * @param string $key
 * @param bool $returnValue
 * @return int|array|string
 */
function array_rank(array $array, string $key, bool $returnValue = false)
{
    $index = null;
    $cursor = 0;
    $rand = mt_rand(0, array_sum(array_column($array, $key)));
    foreach ($array as $k => $v) {
        if ((($cursor += intval($v[$key])) > $rand) and ($index = $k)) break;
    }
    if (is_null($index)) $index = array_rand($array);
    if (!$returnValue) return $index;
    return $array[$index];
}

/**
 * 将字符串分割成1个字的数组，主要用于中英文混合时，将中英文安全的分割开
 * @param $str
 * @return array
 */
function str_cut(string $str): array
{
    $len = mb_strlen($str);
    $arr = Array();
    for ($i = 0; $i < $len; $i++) {
        $arr[] = mb_substr($str, $i, 1, "utf8");
    }
    return $arr;
}

/**
 * @param string $str
 * @return int
 */
function str_len(string $str): int
{
    return count(str_cut($str));
}

/**
 * @param string $str
 * @param int $len
 * @return string
 */
function str_left($str, int $len): string
{
    if (empty($str)) return '';
    return implode(array_slice(str_cut($str), 0, $len));
}

/**
 * 计算一个数的组成，比如：10=8+2，14=8+4+2，22=16+4+2。
 * @param $value
 * @return array
 */
function numbers(int $value): array
{
    $val = Array();
    if ($value % 2 != 0) {//非偶数
        $value -= 1;
        $val[] = 1;
    }
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
 * GB2312转UTF8
 * @param $str
 * @return string
 */
function utf8(string $str): string
{
//    return iconv('GB2312', 'UTF-8//IGNORE', $str);
    return mb_convert_encoding($str, 'UTF-8', 'auto');
}

/**
 * @param $code
 * @return string
 */
function unicode_decode(string $code): string
{
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($matches) {
        return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");
    }, $code);
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
function text(string $html, int $star = null, int $stop = null): string
{
    if ($stop === null) list($star, $stop) = [0, $star];
    $v = preg_replace(['/\&lt\;(.*?)\&gt\;/is', '/&[a-z]+?\;/', '/<(.*?)>/is', '/[\s\x20\xa\xd\'\"\`]/is'], '', trim($html));
    $v = str_ireplace(["\a", "\b", "\f", "\s", "\t", "\n", "\r", "\v", "\0", "\h", " ", "　", "	"], '', $v);
    return htmlentities(mb_substr($v, $star, $stop, 'utf-8'));
}


/**
 * 判断$n是否介于$a和$b之间
 * @param int $n
 * @param int $a
 * @param int $b
 * @param bool $han
 * @return bool
 */
function between(int $n, int $a, int $b, bool $han = true): bool
{
    return $han ? ($n >= $a and $n <= $b) : ($n > $a and $n < $b);
}


function between_value($value, $min, $max)
{
    if ($min > $value) return $min;
    if ($max < $value) return $max;
    return $value;
}

/**
 * 将12k,13G转换为字节长度
 * @param $size
 * @return int
 */
function re_size(string $size): int
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

function data_size(int $byte, int $x = 2)
{
    if ($byte > pow(1024, 4)) {
        return round($byte / pow(1024, 4), $x) . 'TB';
    }
    if ($byte > pow(1024, 3)) {
        return round($byte / pow(1024, 3), $x) . 'GB';
    }
    if ($byte > pow(1024, 2)) {
        return round($byte / pow(1024, 2), $x) . 'MB';
    }
    if ($byte > 1024) {
        return round($byte / 1024, $x) . 'KB';
    }
    return $byte . 'B';
}

/**
 * @param $ext
 * @return int
 */
function image_type(string $ext): int
{
    $file = Array();
    $file['gif'] = IMAGETYPE_GIF;
    $file['jpg'] = IMAGETYPE_JPEG;
    $file['jpeg'] = IMAGETYPE_JPEG;
    $file['png'] = IMAGETYPE_PNG;
    $file['swf'] = IMAGETYPE_SWF;
    $file['psd'] = IMAGETYPE_PSD;
    $file['bmp'] = IMAGETYPE_BMP;
    $file['wbmp'] = IMAGETYPE_WBMP;
    $file['bmp'] = IMAGETYPE_XBM;
    return isset($file[$ext]) ? $file[$ext] : 0;
}

/**
 * 格式化字符串
 * @param $str
 * @return mixed
 */
function format(string $str): string
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
 * 将字符串中相应指定键替换为数据相应值
 * @param string $str ="姓名:{name},年龄:{age}"
 * @param array $arr =['name'=>'张三','age'=>20]
 * @return string
 */
function replace_kv(string $str, array $arr): string
{
    return str_replace(array_map(function ($k) {
        return "{{$k}}";
    }, array_keys($arr)), array_values($arr), $str);
}

/**
 * 对IMG转码，返回值可以直接用于<img src="***">
 * @param $file
 * @return null|string
 * chunk_split
 */
function img_base64(string $file, bool $split = false): string
{
    if (!is_readable($file)) return '';
    if (function_exists('exif_imagetype')) {
        $t = exif_imagetype($file);
    } else {
        $ti = getimagesize($file);
        $t = $ti[2];
    }
    $ext = image_type_to_extension($t, false);
    if (!$ext) return '';
    $file_content = base64_encode(file_get_contents($file));
    if ($split) $file_content = chunk_split($file_content);
    return "data:image/{$ext};base64,{$file_content}";
}

/**
 * 将base64转换为图片
 * @param string $base64Code
 * @param string|null $fileName 不带名时为直接输出
 * @return bool
 */
function base64_img(string $base64Code, string $fileName = null)
{
    if (substr($base64Code, 0, 4) === 'data') $base64Code = substr($base64Code, strpos($base64Code, 'base64,') + 7);
    $data = base64_decode($base64Code);
    if (!$data) return false;
    $im = @imagecreatefromstring($data);
    if ($im === false) return false;

    if (is_null($fileName)) {
        header('Content-Type: image/png');
        imagepng($im);
        imagedestroy($im);
    } else {
        mk_dir($fileName);
        $ext = strtolower(substr($fileName, -3));
        if ($ext === 'png') return imagepng($im, $fileName);
        elseif ($ext === 'gif') return imagegif($im, $fileName);
        elseif ($ext === 'bmp') return imagewbmp($im, $fileName);
        elseif ($ext === 'jpg') return imagejpeg($im, $fileName, 80);
        else return imagepng($im, $fileName);
    }
    return false;
}

/**
 * 时间友好型提示风格化（即XXX小时前、昨天等等）
 * @param int $timestamp
 * @param int|null $time_now
 * @return string
 */
function date_friendly($timestamp, $time_now = null)
{
    $Q = $timestamp > time() ? '后' : '前';
    $V = $T = $dt = null;
    $S = abs((($time_now ?: time()) - $timestamp) ?: 1) and $V = 'S' and $T = '秒';
    $I = floor($S / 60) and $V = 'I' and $T = '分钟';
    $H = floor($I / 60) and $V = 'H' and $T = '小时';
    $D = intval($H / 24) and $V = 'D' and $T = '天';
    $M = intval($D / 30) and $V = 'M' and $T = '个月';
    $Y = intval($M / 12) and $V = 'Y' and $T = '年';
    if ($D === 1) return '昨天 ' . date('H:i', $timestamp);
    if ($D === 2) return '前天 ' . date('H:i', $timestamp);
    if ($M === 1) return '上个月 ' . date('m-d', $timestamp);
    if ($Y === 1) return '去年 ' . date('m-d', $timestamp);
    if ($Y === 2) return '前年 ' . date('m-d', $timestamp);
//    if ($D > 2) $dt = date('m-d', $timestamp);
    if ($M > 1) $dt = date('m-d', $timestamp);
    if ($Y > 2) $dt = date('m-d', $timestamp);
    return sprintf("%s{$T}{$Q} %s", ${$V}, $dt);
}

function date_since($time, $original, $extended = 0, $text = '前')
{
    $time = $time - $original;
    $day = $extended ? floor($time / 86400) : round($time / 86400, 0);
    $amount = 0;
    $unit = '';
    if ($time < 86400) {
        if ($time < 60) {
            $amount = $time;
            $unit = '秒';
        } elseif ($time < 3600) {
            $amount = floor($time / 60);
            $unit = '分钟';
        } else {
            $amount = floor($time / 3600);
            $unit = '小时';
        }
    } elseif ($day < 14) {
        $amount = $day;
        $unit = '天';
    } elseif ($day < 56) {
        $amount = floor($day / 7);
        $unit = '周';
    } elseif ($day < 672) {
        $amount = floor($day / 30);
        $unit = '月';
    } else {
        $amount = intval(2 * ($day / 365)) / 2;
        $unit = '年';
    }

    if ($amount != 1) {
        $unit .= 's';
    }
    if ($extended && $time > 60) {
        $text = ' ' . date_since($time, $time < 86400 ? ($time < 3600 ? $amount * 60 : $amount * 3600) : $day * 86400, 0, '') . $text;
    }

    return $amount . ' ' . $unit . ' ' . $text;
}
