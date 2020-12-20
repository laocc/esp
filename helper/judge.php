<?php

namespace esp\helper;


/**
 * 是否为手机号码
 * @param $value
 * @return bool
 */
function is_mob($value): bool
{
    if (empty($value) or !is_scalar($value)) return false;
    return (boolean)preg_match('/^1[3456789]\d{9}$/', $value);
}

/**
 * @param string $value
 * @param bool $canEmpty
 * @return bool
 */
function is_username($value, bool $canEmpty = false): bool
{
    if ($canEmpty and empty($value)) return true;
    if (empty($value) or !is_string($value)) return false;
    return (boolean)preg_match('/^1[3456789]\d{9}$/', $value) or preg_match('/^\w{3,11}$/', $value);
}

/**
 * 电子邮箱地址格式
 * @param string $value
 * @return bool
 */
function is_mail($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    return (bool)filter_var($value, FILTER_VALIDATE_EMAIL);
}

/**
 * 是否一完网址
 * @param string $value
 * @return bool
 */
function is_url($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    return (bool)filter_var($value, FILTER_VALIDATE_URL);
}


function is_integer($value): bool
{
    if (empty($value) || !is_scalar($value) || is_bool($value)) return false;
    if (\is_float($value + 0) && ($value + 0) > PHP_INT_MAX) return false;
    return \is_float($value) ? false : preg_match('~^((?:\+|-)?[0-9]+)$~', $value);
}

function is_float($value): bool
{
    if (empty($value) || !is_scalar($value)) return false;
    return \is_float($value + 0);
}

/**
 * 是否URI格式
 * @param string $value
 * @return bool
 */
function is_uri($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    return (bool)filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^(\/[\w\-\.\~]*)?(\/.+)*$/i']]);
}

function is_datetime($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    $tmp = explode(' ', trim($value), 2);
    if (!isset($tmp[1])) return false;
    return is_date($tmp[0]) and is_time($tmp[1]);
}

/**
 * 日期格式：2015-02-05 或 20150205
 * @param string $value
 * @return bool
 */
function is_date(string $value): bool
{
    if (empty($value) or !is_string($value)) return false;
    if (1) {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $mac)) {
            return checkdate($mac[2], $mac[3], $mac[1]);
        } elseif (preg_match('/^(\d{4})(\d{1,2})(\d{1,2})$/', $value, $mac)) {
            return checkdate($mac[2], $mac[3], $mac[1]);
        } else {
            return false;
        }
    } else {
        return (boolean)preg_match('/^(?:(?:1[789]\d{2}|2[012]\d{2})[-\/](?:(?:0?2[-\/](?:0?1\d|2[0-8]))|(?:0?[13578]|10|12)[-\/](?:[012]?\d|3[01]))|(?:(?:0?[469]|11)[-\/](?:[012]?\d|30)))|(?:(?:1[789]|2[012])(?:[02468][048]|[13579][26])[-\/](?:0?2[-\/]29))$/', $value);
    }
}

/**
 * 时间格式：12:23:45
 * @param string $value
 * @return bool
 */
function is_time($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    return (boolean)preg_match('/^([0-1]\d|2[0-3])(\:[0-5]\d){2}$/', $value);
}

function is_json($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    if (!preg_match('/^(\{.*\})|(\[.*\])$/', $value)) return false;
    try {
        $a = json_decode($value, true);
        if (!is_array($a)) return false;
    } catch (\Exception $exception) {
        return false;
    }
    return true;
}


/**
 * 字串是否为正则表达式
 * @param $value
 * @return bool
 */
function is_match($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    return (bool)filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([\/\#\@\!\~])\^?.+\$?\1[imUuAsDSXxJ]{0,3}$/i']]);
}

/**
 * 是否mac码
 * @param $value
 * @return bool
 */
function is_mac($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    return (bool)filter_var($value, FILTER_VALIDATE_MAC);
}


/**
 * @param string $value
 * @param string $which
 * @return bool
 */
function is_ip(string $value, string $which = 'ipv4'): bool
{
    if (empty($value) or !is_string($value)) return false;
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
    return (bool)filter_var($value, FILTER_VALIDATE_IP, $which);
}

function is_domain($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    return (boolean)preg_match('/^[a-z0-9](([a-z0-9-]){1,62}\.)+[a-z]{2,20}$/i', $value);
}

/**
 * 身份证号码检测，区分闰年，较验最后识别码
 * @param $value
 * @return bool
 */
function is_card($value): bool
{
    if (empty($value) or !is_string($value)) return false;
    if (!preg_match('/^\d{6}(\d{8})\d{3}(\d|x)$/i', $value, $mac)) return false;
    if (!is_date($mac[1])) return false;
    return strtoupper($mac[2]) === make_card($value);
}
