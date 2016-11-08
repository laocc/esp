<?php
error_reporting(-1);                //报告所有错误
date_default_timezone_set('PRC');

//下面部分基本不需要修改，都是系统公用常量
define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
define("_AJAX", _CLI ? false : strtolower(server('HTTP_X_REQUESTED_WITH')) === "xmlhttprequest");
define("_HTTPS", _CLI ? false : strtolower(server('HTTPS')) === 'on');
define("_DOMAIN", _CLI ? null : explode(':', server("HTTP_HOST") . ':')[0]);
define("_HOST", _CLI ? null : host(_DOMAIN));
define("_AGENT", _CLI ? null : server("HTTP_USER_AGENT", ''));
define("_IP_S", _CLI ? '127.0.0.1' : server("SERVER_ADDR", '127.0.0.1'));
define("_IP_C", _CLI ? '127.0.0.1' : ip());


