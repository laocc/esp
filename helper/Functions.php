<?php
//declare(strict_types=1);

namespace esp\helper;

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
            else echo var_export($v, true);
        }
        echo "</pre>";
    }
}


/**
 * CLI环境中打印彩色字
 * @param $text
 * @param string|null $bgColor
 * @param string|null $ftColor
 */
function _echo($text, string $bgColor = null, string $ftColor = null)
{
    if (is_array($text)) $text = print_r($text, true);
    $text = trim($text, "\n");
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
 * 过滤所有可能的符号，并将连续的符号合并成1个
 * @param string $str
 * @param string $f
 * @return null|string|string[]
 */
function replace_for_split(string $str, string $f = ','): string
{
    if (empty($str)) return '';
    $str = mb_ereg_replace(
        '[  \`\-\=\[\]\\\;\',\.\/\~\!\@\#\$\%\^\&\*\(\)\_\+\{\}\|\:\"\<\>\?\·【】、；‘，。/~！@#￥%……&*（）——+{}|：“《》？]',
        $f, $str);
    if (empty($f)) return $str;
    $ff = '\\' . $f;
    return trim(preg_replace(["/{$ff}+/"], $f, $str), $f);
}


/**
 * 设置HTTP响应头
 * @param int $code
 * @param string|null $text
 * @throws \Exception
 */
function header_state(int $code = 200, string $text = '')
{
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
    return array_map(function ($s) {
        return ord($s);
    }, str_split($string));
}

/**
 * 格式化小数
 * @param float $amount
 * @param int $len
 * @param bool $zero
 * @return string
 */
function rnd(float $amount, int $len = 2, bool $zero = true): string
{
    if (!$amount and !$zero) return '';
    return sprintf("%.{$len}f", $amount);
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
 * 数组，按某个字段排序
 * @param $array
 * @param string $key
 * @param string $order
 */
function array_sort(array &$array, string $key, string $order = 'desc')
{
    $order = strtolower($order);
    usort($array, function ($a, $b) use ($key, $order) {
        if (!isset($b[$key])) return 0;
        if (is_int($b[$key]) or is_float($b[$key])) {
            return ($order === 'asc') ? ($b[$key] - $a[$key]) : ($a[$key] - $a[$key]);
        } else {
            return ($order === 'asc') ? strnatcmp($a[$key], $b[$key]) : strnatcmp($b[$key], $a[$key]);
        }
    });
}

/**
 * 将字符串分割成1个字的数组，主要用于中英文混合时，将中英文安全的分割开
 * @param $str
 * @return array
 */
function str_cut(string $str): array
{
    $arr = Array();
    for ($i = 0; $i < mb_strlen($str); $i++) {
        $arr[] = mb_substr($str, $i, 1, "utf8");
    }
    return $arr;
}


/**
 * 中文left，纯英文时可以直接用substr()
 * @param string $str
 * @param int $len
 * @return string
 */
function str_left(string $str, int $len): string
{
    if (empty($str)) return '';
    return mb_substr($str, 0, $len, "utf8");
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
 * 计算一个数的组成，比如：10=8+2，14=8+4+2，22=16+4+2。
 * @param $num
 * @return array
 */
function numbers(int $num): array
{
    $i = 1;
    $val = [];
    do {
        ($i & $num) && ($val[] = $i) && ($num -= $i);
    } while ($num > 0 && $i <<= 1);
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
 * 将12k,13G转换为字节长度
 * @param $size
 * @return int
 */
function re_size(string $size): int
{
    return (int)preg_replace_callback('/(\d+)([kmGt])b?/i', function ($matches) {
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
    }, $size);
}

/**
 * 字节长度，转换为 12KB,4MB格式
 * @param int $byte
 * @param int $x
 * @return string
 */
function data_size(int $byte, int $x = 2): string
{
    $k = 4;
    while ($k--) if ($byte > pow(1024, $k)) break;
    return round($byte / pow(1024, $k), $x) . ['B', 'KB', 'MB', 'TB'][$k];
}
