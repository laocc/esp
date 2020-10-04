<?php

namespace esp\helper;


/**
 * 是否为手机号码
 * @param $mobNumber
 * @return bool
 */
function is_mob(string $mobNumber): bool
{
    if (empty($mobNumber)) return false;
    return (boolean)preg_match('/^1[3456789]\d{9}$/', $mobNumber);
}

/**
 * @param string $name
 * @param bool $canEmpty
 * @return bool
 */
function is_username(string $name, bool $canEmpty = false): bool
{
    if ($canEmpty and empty($name)) return true;
    if (empty($name)) return false;
    return (boolean)preg_match('/^1[3456789]\d{9}$/', $name) or preg_match('/^\w{3,11}$/', $name);
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

function is_datetime(string $time): bool
{
    $tmp = explode(' ', trim($time), 2);
    if (!isset($tmp[1])) return false;
    return is_date($tmp[0]) and is_time($tmp[1]);
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
        return (boolean)preg_match('/^(?:(?:1[789]\d{2}|2[012]\d{2})[-\/](?:(?:0?2[-\/](?:0?1\d|2[0-8]))|(?:0?[13578]|10|12)[-\/](?:[012]?\d|3[01]))|(?:(?:0?[469]|11)[-\/](?:[012]?\d|30)))|(?:(?:1[789]|2[012])(?:[02468][048]|[13579][26])[-\/](?:0?2[-\/]29))$/', $day);
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
    return (boolean)preg_match('/^([0-1]\d|2[0-3])(\:[0-5]\d){2}$/', $time);
}

function is_json(string $json): bool
{
    if (!preg_match('/^(\{.*\})|(\[.*\])$/', $json)) return false;
    try {
        $a = json_decode($json, true);
        if (!is_array($a)) return false;
    } catch (\Exception $exception) {
        return false;
    }
    return true;
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
    return (boolean)preg_match('/^[a-z0-9](([a-z0-9-]){1,62}\.)+[a-z]{2,20}$/i', $domain);
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
