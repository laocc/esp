<?php
declare(strict_types=1);
ini_set('error_reporting', strval(E_ALL));
ini_set('display_errors', 'true');
ini_set('date.timezone', 'Asia/Shanghai');

define("_ROOT", dirname(__DIR__, 1));
is_readable($auto = (_ROOT . "/vendor/autoload.php")) ? include($auto) : exit('RUN: composer install');

define('_RUNTIME', _ROOT . '/runtime');
define('_DOMAIN', explode(':', getenv('HTTP_HOST') . ':')[0]);
define('_HOST', host(_DOMAIN));//域名的根域
define('_HTTPS', (getenv('HTTP_HTTPS') === 'on' or getenv('HTTPS') === 'on'));
define('_HTTP_', (_HTTPS ? 'https://' : 'http://'));
define('_URL', _HTTP_ . _DOMAIN . getenv('REQUEST_URI'));

define('_CONFIG_LOAD', 1);

$option = array();
$option['config']['path'] = '/common/config';
return $option;