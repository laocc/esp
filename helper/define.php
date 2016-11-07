<?php
error_reporting(-1);//报告所有错误
date_default_timezone_set('PRC');

define('_CLI', (PHP_SAPI === 'cli' or php_sapi_name() === 'cli'));
define("_AJAX", _CLI ? false : strtolower(server('HTTP_X_REQUESTED_WITH')) === "xmlhttprequest");
define("_HTTPS", _CLI ? false : strtolower(server('HTTPS')) === 'on');
define("_DOMAIN", _CLI ? null : explode(':', server("HTTP_HOST") . ':')[0]);
define("_HOST", _CLI ? null : host(_DOMAIN));
define("_URL", _CLI ? null : ((_HTTPS ? 'https://' : 'http://') . _DOMAIN . server("REQUEST_URI")));
define("_REFERER", _CLI ? null : server("HTTP_REFERER"));
define("_AGENT", _CLI ? null : server("HTTP_USER_AGENT", ''));
define("_ARGV", _CLI ? ('/' . trim(implode('/', array_slice($GLOBALS["argv"], 1)), '/')) : null);
define("_IP_S", _CLI ? '127.0.0.1' : server("SERVER_ADDR", '127.0.0.1'));
define("_IP_C", _CLI ? '127.0.0.1' : ip());

//控制器和动作后缀
define("_CONTROL", 'Controller');
define("_ACTION", 'Action');
define("_DIRECTORY", _ROOT . 'application/');
define("_VIEW_EXT", '.phtml');
