<?php

namespace esp\core;

use esp\core\db\Redis;

/**
 * Class Config
 * @package esp\core
 */
final class Config
{
    static private $_CONFIG_ = null;

    /**
     * @param Redis $buffer
     * @param array $config
     */
    public static function _init(Redis $buffer, array &$config)
    {
        self::$_CONFIG_ = $buffer->get($buffer->key . '_CONFIG_');

        if (!empty(self::$_CONFIG_)) {
            self::$_CONFIG_ = unserialize(self::$_CONFIG_);
            if (!empty(self::$_CONFIG_)) return;
        }
        if (empty($config)) return;

        self::$_CONFIG_ = Array();
        foreach ($config as $i => $file) {
            $_config = self::loadFile($file);
            if (!empty($_config)) self::$_CONFIG_ = array_merge(self::$_CONFIG_, $_config);
        }
        self::$_CONFIG_ = self::re_arr(self::$_CONFIG_);
        $buffer->set($buffer->key . '_CONFIG_', serialize(self::$_CONFIG_));
    }

    /**
     * @param string $file
     * @param bool $byKey
     * @return array
     * @throws \Exception
     */
    public static function loadFile(string $file, $byKey = true): array
    {
        $fullName = root($file);
        if (!is_readable($fullName)) {
            throw new \Exception("配置文件{$file}不存在", 404);
        };
        $info = pathinfo($fullName);

        if ($info['extension'] === 'php') {
            $_config = include($fullName);
            if (!is_array($_config)) $_config = [];

        } elseif ($info['extension'] === 'ini') {
            $_config = parse_ini_file($fullName, true);
            if (!is_array($_config)) $_config = [];

        } elseif ($info['extension'] === 'json') {
            $_config = file_get_contents($fullName);
            $_config = json_decode($_config, true);
            if (!is_array($_config)) $_config = [];
        }

        if (isset($_config['include'])) {
            $include = $_config['include'];
            unset($_config['include']);
            foreach ($include as $key => $fil) {
                if (is_array($fil)) {
                    $_config[$key] = Array();
                    foreach ($fil as $l => $f) {
                        $_inc = self::loadFile(root($f));
                        if (!empty($_inc)) $_config[$key] = $_inc + $_config[$key];
                    }
                } else {
                    $_inc = self::loadFile(root($fil));
                    if (!empty($_inc)) $_config = $_inc + $_config;
                }
            }
        }
        return empty($_config) ? [] : ($byKey ? [$info['filename'] => $_config] : $_config);
    }

    /**
     * 加载在format时没载入的，不经过缓存
     * @param $key
     * @param null $auto
     * @return array|mixed|null
     */
    public static function load($file, $key = null, $auto = null)
    {
        $conf = parse_ini_file(root($file), true);
        $conf = self::re_arr($conf);
        if (is_null($key)) return $conf;

        $key = preg_replace('/[\.\,\/]+/', '.', strtolower($key));
        if (strrpos($key, '.')) {
            $keys = explode('.', trim($key, '.'));
            $_config = $conf;
            foreach ($keys as $k) {
                $_config = isset($_config[$k]) ? $_config[$k] : null;
                if (is_null($_config)) return $auto;
            }
            return $_config;
        }
        return isset($conf[$key]) ? $conf[$key] : $auto;
    }


    private static function re_key($val)
    {
        $search = array('{_HOST}', '{_ROOT}', '{_DOMAIN}', '{_TIME}', '{_DATE}');
        $replace = array(_HOST, _ROOT, _DOMAIN, time(), date('YmdHis'));
        $value = str_ireplace($search, $replace, $val);
        if (substr($value, 0, 1) === '[' and substr($value, -1, 1) === ']') {
            $arr = json_decode($value, true);
            if (is_array($arr)) $value = $arr;
        } else if (is_numeric($value)) {
            $value = intval($value);
        }
        return $value;
    }

    private static function re_arr($array)
    {
        $val = Array();
        foreach ($array as $k => $arr) {
            if (is_array($arr)) {
                $val[strtolower($k)] = self::re_arr($arr);
            } else {
                $val[strtolower($k)] = self::re_key($arr);
            }
        }
        return $val;
    }

    /**
     * 读取config，可以用get('key1.key2')的方式读取多维数组值
     * 连接符可以有：.,_/\
     * @param null $key
     * @param null $auto
     * @return array|mixed|null
     */
    public static function get($key = null, $auto = null)
    {
        if (is_null($key)) return self::$_CONFIG_;
        $key = preg_replace('/[\.\,\/]+/', '.', strtolower($key));
        if (strrpos($key, '.')) {
            $keys = explode('.', trim($key, '.'));
            $conf = self::$_CONFIG_;
            foreach ($keys as $k) {
                if (is_null($conf = ($conf[$k] ?? null))) return $auto;
            }
            return $conf;
        }
        return self::$_CONFIG_[$key] ?? $auto;
    }


    public static function has($key)
    {
        return self::get($key, "__Test_Config_{$key}__") !== "__Test_Config_{$key}__";
    }

    public static function set($key, $value)
    {
        self::$_CONFIG_[$key] = $value;
    }


    /**
     * @param $type
     * @return string
     */
    public static function mime(string $type): string
    {
        switch ($type) {
            case 'html':
                return 'text/html';
            case 'htm':
                return 'text/html';
            case 'shtml':
                return 'text/html';
            case 'css':
                return 'text/css';
            case 'xml':
                return 'text/xml';
            case 'mml':
                return 'text/mathml';
            case 'txt':
                return 'text/plain';
            case 'text':
                return 'text/plain';
            case 'jad':
                return 'text/vnd.sun.j2me.app-descriptor';
            case 'wml':
                return 'text/vnd.wap.wml';
            case 'htc':
                return 'text/x-component';
            case 'gif':
                return 'image/gif';
            case 'jpeg':
                return 'image/jpeg';
            case 'jpg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'tif':
                return 'image/tiff';
            case 'tiff':
                return 'image/tiff';
            case 'wbmp':
                return 'image/vnd.wap.wbmp';
            case 'ico':
                return 'image/x-icon';
            case 'jng':
                return 'image/x-jng';
            case 'bmp':
                return 'image/x-ms-bmp';
            case 'svg':
                return 'image/svg+xml';
            case 'svgz':
                return 'image/svg+xml';
            case 'webp':
                return 'image/webp';
            case 'js':
                return 'application/javascript';
            case 'atom':
                return 'application/atom+xml';
            case 'rss':
                return 'application/rss+xml';
            case 'woff':
                return 'application/font-woff';
            case 'jar':
                return 'application/java-archive';
            case 'war':
                return 'application/java-archive';
            case 'ear':
                return 'application/java-archive';
            case 'json':
                return 'application/json';
            case 'hqx':
                return 'application/mac-binhex40';
            case 'doc':
                return 'application/msword';
            case 'pdf':
                return 'application/pdf';
            case 'ps':
                return 'application/postscript';
            case 'eps':
                return 'application/postscript';
            case 'ai':
                return 'application/postscript';
            case 'rtf':
                return 'application/rtf';
            case 'm3u8':
                return 'application/vnd.apple.mpegurl';
            case 'xls':
                return 'application/vnd.ms-excel';
            case 'eot':
                return 'application/vnd.ms-fontobject';
            case 'ppt':
                return 'application/vnd.ms-powerpoint';
            case 'wmlc':
                return 'application/vnd.wap.wmlc';
            case 'kml':
                return 'application/vnd.google-earth.kml+xml';
            case 'kmz':
                return 'application/vnd.google-earth.kmz';
            case '7z':
                return 'application/x-7z-compressed';
            case 'cco':
                return 'application/x-cocoa';
            case 'jardiff':
                return 'application/x-java-archive-diff';
            case 'jnlp':
                return 'application/x-java-jnlp-file';
            case 'run':
                return 'application/x-makeself';
            case 'pl':
                return 'application/x-perl';
            case 'pm':
                return 'application/x-perl';
            case 'prc':
                return 'application/x-pilot';
            case 'pdb':
                return 'application/x-pilot';
            case 'rar':
                return 'application/x-rar-compressed';
            case 'rpm':
                return 'application/x-redhat-package-manager';
            case 'sea':
                return 'application/x-sea';
            case 'swf':
                return 'application/x-shockwave-flash';
            case 'sit':
                return 'application/x-stuffit';
            case 'tcl':
                return 'application/x-tcl';
            case 'tk':
                return 'application/x-tcl';
            case 'der':
                return 'application/x-x509-ca-cert';
            case 'pem':
                return 'application/x-x509-ca-cert';
            case 'crt':
                return 'application/x-x509-ca-cert';
            case 'xpi':
                return 'application/x-xpinstall';
            case 'xhtml':
                return 'application/xhtml+xml';
            case 'xspf':
                return 'application/xspf+xml';
            case 'zip':
                return 'application/zip';
            case 'bin':
                return 'application/octet-stream';
            case 'exe':
                return 'application/octet-stream';
            case 'dll':
                return 'application/octet-stream';
            case 'deb':
                return 'application/octet-stream';
            case 'dmg':
                return 'application/octet-stream';
            case 'iso':
                return 'application/octet-stream';
            case 'img':
                return 'application/octet-stream';
            case 'msi':
                return 'application/octet-stream';
            case 'msp':
                return 'application/octet-stream';
            case 'msm':
                return 'application/octet-stream';
            case 'php':
                return 'application/octet-stream';
            case 'phtml':
                return 'application/octet-stream';
            case 'docx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'xlsx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            case 'pptx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            case 'mid':
                return 'audio/midi';
            case 'midi':
                return 'audio/midi';
            case 'kar':
                return 'audio/midi';
            case 'mp3':
                return 'audio/mpeg';
            case 'ogg':
                return 'audio/ogg';
            case 'm4a':
                return 'audio/x-m4a';
            case 'ra':
                return 'audio/x-realaudio';
            case '3gpp':
                return 'video/3gpp';
            case '3gp':
                return 'video/3gpp';
            case 'ts':
                return 'video/mp2t';
            case 'mp4':
                return 'video/mp4';
            case 'mpeg':
                return 'video/mpeg';
            case 'mpg':
                return 'video/mpeg';
            case 'mov':
                return 'video/quicktime';
            case 'webm':
                return 'video/webm';
            case 'flv':
                return 'video/x-flv';
            case 'm4v':
                return 'video/x-m4v';
            case 'mng':
                return 'video/x-mng';
            case 'asx':
                return 'video/x-ms-asf';
            case 'asf':
                return 'video/x-ms-asf';
            case 'wmv':
                return 'video/x-ms-wmv';
            case 'avi':
                return 'video/x-msvideo';
            default:
                return 'text/html';
        }
    }

    /**
     * @param $code
     * @return null|string
     */
    public static function states(int &$code)
    {
        $state = [];
        $state[200] = 'OK';
        $state[201] = 'Created';
        $state[202] = 'Accepted';
        $state[203] = 'Non-Authoritative Information';
        $state[204] = 'Not Content';
        $state[205] = 'Reset Content';
        $state[206] = 'Partial Content';
        $state[300] = 'Multiple Choices';
        $state[301] = 'Moved Permanently';
        $state[302] = 'Found';
        $state[303] = 'See Other';
        $state[304] = 'Not Modified';
        $state[305] = 'Use Proxy';
        $state[307] = 'Temporary Redirect';
        $state[400] = 'Bad Request';
        $state[401] = 'Unauthorized';
        $state[403] = 'Forbidden';
        $state[404] = 'Not Found';
        $state[405] = 'Method Not Allowed';
        $state[406] = 'Not Acceptable';
        $state[407] = 'Proxy Authentication Required';
        $state[408] = 'Request Timeout';
        $state[409] = 'Conflict';
        $state[410] = 'Gone';
        $state[411] = 'Length Required';
        $state[412] = 'Precondition Failed';
        $state[413] = 'Request Entity Too Large';
        $state[414] = 'Request-URI Too Long';
        $state[415] = 'Unsupported Media Type';
        $state[416] = 'Requested Range Not Satisfiable';
        $state[417] = 'Expectation Failed';
        $state[422] = 'Unprocessable Entity';
        $state[500] = 'Internal Server Error';
        $state[501] = 'Not Implemented';
        $state[502] = 'Bad Gateway';
        $state[503] = 'Service Unavailable';
        $state[504] = 'Gateway Timeout';
        $state[505] = 'HTTP Version Not Supported';
        if (!isset($state[$code])) $code = 400;
        return isset($state[$code]) ? $state[$code] : 'Unexpected';
    }


}