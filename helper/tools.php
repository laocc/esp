<?php

namespace esp\helper;

/**
 * 读取CPU数量信息
 * @return array
 */
function get_cpu()
{
    if (PHP_OS !== 'Linux') return [];
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
 * 对IMG转码，返回值可以直接用于<img src="***">
 * @param string $file
 * @param bool $split
 * @return string
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
    $str = array();
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
        $sigma += intval($body[$i]) * $wi[$i]; //把从身份证号码中提取的一位数字和加权因子相乘，并累加
    }
    $ai = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');//校验码串
    return $ai[$sigma % 11]; //按照序号从校验码串中提取相应的字符。
}


/**
 * 设置HTTP响应头
 * @param int $code
 * @param string|null $text
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
        if (\is_int($b[$key]) or \is_float($b[$key])) {
            return ($order === 'asc') ? ($b[$key] - $a[$key]) : ($a[$key] - $a[$key]);
        } else {
            return ($order === 'asc') ? strnatcmp($a[$key], $b[$key]) : strnatcmp($b[$key], $a[$key]);
        }
    });
}

/**
 * 数组转为ini字符串
 * @param $arr
 * @return string
 */
function array_ini($arr)
{
    $ini = [];
    foreach ($arr as $k => $a) {
        if (is_array($a)) {
            $ini[] = "[{$k}]";
            foreach ($a as $kk => $aa) {
                if (is_array($aa)) {
                    foreach ($aa as $ak => $av) {
                        if (is_array($av)) $av = "'" . json_encode($av, 256 | 64) . "'";
                        $ini[] = "{$kk}[{$ak}] = {$av}";
                    }
                } else {
                    $ini[] = "{$kk} = {$aa}";
                }
            }
        } else {
            $ini[] = "{$k} = {$a}";
        }
    }
    return implode("\n", $ini);
}

/**
 * 将字符串分割成1个字的数组，主要用于中英文混合时，将中英文安全的分割开
 * @param $str
 * @return array
 */
function str_cut(string $str): array
{
    $arr = array();
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
 * HTML截取
 * str_ireplace中最后几个空白符号，不是空格，是一些特殊空格
 * 0:194,160,32,
 * 1:194,160,
 * 2:227,128,128,
 * 3:9,
 * 4:32,
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
    $v = str_ireplace(["\a", "\b", "\f", "\s", "\t", "\n", "\r", "\v", "\0", "\h", '  ', " ", "　", "	", ' '], '', $v);
    return htmlentities(mb_substr($v, $star, $stop, 'utf-8'));
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
        implode(['  ', " ", "　", "	", ' ']) .//这几个是特殊的空格
        '[\`\-\=\[\]\\\;\',\.\/\~\!\@\#\$\%\^\&\*\(\)\_\+\{\}\|\:\"\<\>\?\·【】、；‘，。/~！@#￥%……&*（）——+{}|：“《》？]',
        $f, $str);
    if (empty($f)) return $str;
    $ff = '\\' . $f;
    return trim(preg_replace(["/{$ff}+/"], $f, $str), $f);
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

