<?php

namespace esp\helper;

/**
 * 此文件中的函数不默认加载，如果需要，手工复制到自己项目中去用
 */

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
 * @param $ext
 * @return int
 */
function image_type(string $ext): int
{
    $file = array();
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
 * 不记得在哪个项目用的了
 * @param $time
 * @param $original
 * @param int $extended
 * @param string $text
 * @return string
 */
function date_since($time, $original, $extended = 0, $text = '前')
{
    $time = $time - $original;
    $day = $extended ? floor($time / 86400) : round($time / 86400, 0);
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
