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


/**
 * 从ua中提取手机品牌
 * @param null $ua
 * @return string
 */
function brand($ua = null)
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