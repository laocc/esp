<?php

function pre(...$str)
{
    $prev = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    if (_CLI) {
        if (isset($prev['file'])) echo "{$prev['file']}[{$prev['line']}]\n";
        foreach ($str as $i => &$v) print_r($v);
    } else {
        if (isset($prev['file'])) {
            $file = "<i style='color:blue;'>{$prev['file']}</i><i style='color:red;'>[{$prev['line']}]</i>\n";
        } else {
            $file = null;
        }
        echo "<pre style='background:#fff;display:block;'>", $file;
        foreach ($str as $i => &$v) print_r($v);
        echo "</pre>";
    }
}


/**
 *
 * 产生一个错误信息，具体处理，由\plugins\Mistake处理
 * @param $str
 * @param int $level 错误级别，012，
 *
 * 0：系统停止执行，严重级别
 * 1：提示错误，继续运行
 * 2：警告级别，在生产环境中不提示，仅发给管理员
 *
 * error("{$filePath} 不是有效文件。");
 */
function error($str, $level = 0, array $errFile = null)
{
    if (is_int($str)) goto state;
    if (is_array($level)) list($level, $errFile) = [0, $level];

    $err = $errFile ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    \esp\extend\Mistake::try_error($str, $level, $err);
    return;

    state:  //模拟成某个错误状态
    $state = \esp\core\Config::states($str);
    $server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : null;
    $html = "<html>\n<head><title>{$str} {$state}</title></head>\n<body bgcolor=\"white\">\n<center><h1>{$str} {$state}</h1></center>\n<hr><center>{$server}</center>\n</body>\n</html>";
    header_state($str, $state);
    header('Content-type:text/html', true);
    exit($html);
}


/**
 * 查询域名的根域名，兼容国别的二级域名
 * @param null $domain
 * @return null|string
 */
function host($domain)
{
    if (empty($domain)) return null;
    $dm1 = 'cn|cm|my|ph|tw|uk|hk';
    $dm2 = 'com|net|org|gov|idv|co|name';
    if (strpos('/', $domain)) {
        $domain = explode('/', $domain . '//')[2];
    }
    if (preg_match("/^(?:[\w\.\-]+\.)?([a-z]+)\.({$dm2})\.({$dm1})$/i", $domain, $match)) {
        return "{$match[1]}.{$match[2]}.{$match[3]}";
    } elseif (preg_match("/^(?:[\w\.\-]+\.)?([a-z]+)\.([a-z]+)$/i", $domain, $match)) {
        return "{$match[1]}.{$match[2]}";
    } else {
        return null;
    }
}

/**
 * 加载文件，同时加载结果被缓存
 * @param $file
 * @return bool|mixed
 */
function load($file)
{
    if (!$file) return false;
    static $recode = [];
    $file = root($file);
    $md5 = md5($file);
    if (isset($recode[$md5])) return $recode[$md5];
    $recode[$md5] = include $file;
    return $recode[$md5];
}

/**
 * 修正为_ROOT开头
 * @param string $path 若最后一个参数是true，则在返回结果后加/
 * @return string|array
 */
function root(...$path)
{
    $len = count($path);
    $folder = false;
    if (is_bool($path[$len - 1])) {
        $folder = $path[$len - 1];
        unset($path[$len - 1]);
    }
    foreach ($path as $i => &$p) {
        if (stripos($p, _ROOT) !== 0) $p = _ROOT . ltrim($p, '/');
        if ($folder) $p = rtrim($p, '/') . '/';
    }
    return count($path) === 1 ? $path[0] : $path;
}


/**
 * @param $path
 * @param int $mode
 * @return bool
 * 文件权限：
 * 类型   所有者  所有者组    其它用户
 * r    read    4
 * w    write   2
 * x    exec    1
 * 通过PHP建立的文件夹权限一般为0740就可以了
 */
function mk_dir($path, $mode = 0740)
{
    if (!$path) return false;
    if (strrchr($path, '/')) $path = dirname($path);
    if (file_exists($path)) return true;
    if (!$mode) $mode = 0740;
    try {
        @mkdir($path, $mode, true);
        return true;
    } catch (\Exception $e) {
        return true;
    }
}

/**
 * 储存文件
 * @param $file
 * @param $content
 * @return int
 */
function save_file($file, $content)
{
    if (is_array($content)) $content = json_encode($content, 256);
    mk_dir($file);
    return file_put_contents($file, $content, LOCK_EX);
}

/**
 * 设置HTTP响应头
 * @param int $code
 * @param null $text
 * @throws Exception
 */
function header_state($code = 200, $text = null)
{
    if (empty($code) OR !is_numeric($code)) {
        error('状态码必须为数字');
    }
    if (empty($text)) {
        $text = \esp\core\Config::states($code);
    }
    if (!stripos(PHP_SAPI, 'cgi')) {
        header('Status: ' . $code . ' ' . $text, true);
    } else {
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header("{$protocol} {$code} {$text}", true, $code);
    }
}


/**
 * XML解析
 * @param $str
 * @return bool|mixed
 */
function xml_decode($str, $toArray = true)
{
    libxml_disable_entity_loader(true);
    $xml = xml_parser_create();
    if (xml_parse($xml, $str, true)) {
        xml_parser_free($xml);
        return $xml;
    } else {
        $xml = @simplexml_load_string($str, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xml, 256), $toArray);
    }
}

/**
 * 将数组转换成XML格式
 * @param $root
 * @param $array
 * @return string
 */
function xml_encode($root, $array)
{
    return (new \esp\extend\io\Xml($array, $root))
        ->render();
}

if (!function_exists("fastcgi_finish_request")) {
    function fastcgi_finish_request()
    {
    }
}