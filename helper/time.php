<?php

namespace esp\helper;

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
function diff_day(int $a, int $b): int
{
    $interval = date_diff(date_create(date('Ymd', $a)), date_create(date('Ymd', $b)));
    return intval($interval->format('%R%a'));
}

/**
 * 相差天数，a>b时为负数
 * @param int $a
 * @param int $b
 * @return string
 */
function diff_time(int $a, int $b): string
{
    $interval = date_diff(date_create(date('YmdHis', $a)), date_create(date('YmdHis', $b)));
    $d = $interval->format('%a') * 1;
    $h = $interval->format('%h') * 1;
    $i = $interval->format('%i') * 1;
    $s = $interval->format('%s') * 1;
    $d = $d > 0 ? "{$d}天" : '';
    $h = $h > 0 ? "{$h}小时" : '';
    $i = $i > 0 ? "{$i}分" : '';
    $s = $s > 0 ? "{$s}秒" : '';
    if ($d) return "{$d}{$h}";//1天以上
    if ($h) return "{$h}{$i}";//1小时以上
    if ($i) return "{$i}{$s}";//1分钟以上
    return $s;
}

function date_diffs(int $timeA, int $timeB): string
{
    $time = $timeA - $timeB;
    if ($time < 86400) {
        if ($time < 60) {
            return "{$time}秒";
        } elseif ($time < 3600) {
            return intval($time / 60) . '分' . ($time % 60) . '秒';
        } else {
            return intval($time / 3660) . '小时' . intval(($time % 3600) / 60) . '分';
        }
    } else {
        return intval($time / 86400) . '天' . intval(($time % 86400) / 3600) . '小时';
    }
}


/**
 * 时间友好型提示风格化（即XXX小时前、昨天等等）
 * @param int $timestamp
 * @param int|null $time_now
 * @return string
 */
function date_friendly(int $timestamp, $time_now = null): string
{
    $Q = $timestamp > _TIME ? '后' : '前';
    $V = $T = $dt = null;
    $S = abs((($time_now ?: _TIME) - $timestamp) ?: 1) and $V = 'S' and $T = '秒';
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
