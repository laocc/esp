<?php

if (!class_exists('redis')) echo "当前系统未安装Redis缓存扩展\n";


$status = session_status();
if ($status === PHP_SESSION_DISABLED) {
    echo "Session: 异常，可能session已被禁止，在编译PHP时不带--disable-session可恢复启用\n";
} elseif ($status === PHP_SESSION_ACTIVE or (bool)ini_get('session.auto_start')) {
    echo "Session: session已启动，请关闭php.ini中的session.auto_start\n";
}

if (!version_compare((curl_version())['version'], '7.21.3', '>=')) {
    echo "cURL需要 v7.21.3 以上，否则cURL使用中可能会有异常\n";
}

