<?php
namespace esp\core;

/**
 *
 * 此处读取/config/config.php中的设置
 *
 * Class Config
 * @package esp\core
 */
final class Config
{
    static private $_conf = [];

    public static function load()
    {
        if (!empty(self::$_conf)) return;

        $file = root('config/config.php', 'config/database.php');
        foreach ($file as &$fil) {
            $_conf = load($fil);
            if (is_array($_conf) && !empty($_conf)) {
                self::$_conf = array_merge(self::$_conf, $_conf);
            }
        }

        reload: //定义此标签用于循环加载后面文件也有include的情况

        if (isset(self::$_conf['include']) and !empty(self::$_conf['include'])) {
            $file = self::$_conf['include'];
            unset(self::$_conf['include']);
            $file = is_array($file) ? root(...$file) : root($file);
            if (!is_array($file)) $file = [$file];
            foreach ($file as $fil) {
                $_conf = is_readable($fil) ? load($fil) : null;
                if (is_array($_conf) && !empty($_conf)) {
                    self::$_conf = $_conf + self::$_conf;
                }
            }
            goto reload;
        }
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
        if (is_null($key)) return self::$_conf;
        $key = preg_replace('/[\.\,\_\/\\\]+/', '.', $key);
        if (strrpos($key, '.')) {
            $keys = explode('.', trim($key, '.'));
            $conf = self::$_conf;
            foreach ($keys as $k) {
                $conf = isset($conf[$k]) ? $conf[$k] : null;
                if (is_null($conf)) return $auto;
            }
            return $conf;
        }
        return isset(self::$_conf[$key]) ? self::$_conf[$key] : $auto;
    }

    public static function has($key)
    {
        return self::get($key, "__Test_Config_{$key}__") !== "__Test_Config_{$key}__";
    }

    public static function set($key, $value)
    {
        self::$_conf[$key] = $value;
    }


    public static function mime($type)
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
    public static function states($code)
    {
        switch ($code) {
            case 200:
                return 'OK';
            case 201:
                return 'Created';
            case 202:
                return 'Accepted';
            case 203:
                return 'Non-Authoritative Information';
            case 204:
                return 'Not Content';
            case 205:
                return 'Reset Content';
            case 206:
                return 'Partial Content';
            case 300:
                return 'Multiple Choices';
            case 301:
                return 'Moved Permanently';
            case 302:
                return 'Found';
            case 303:
                return 'See Other';
            case 304:
                return 'Not Modified';
            case 305:
                return 'Use Proxy';
            case 307:
                return 'Temporary Redirect';
            case 400:
                return 'Bad Request';
            case 401:
                return 'Unauthorized';
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not Found';
            case 405:
                return 'Method Not Allowed';
            case 406:
                return 'Not Acceptable';
            case 407:
                return 'Proxy Authentication Required';
            case 408:
                return 'Request Timeout';
            case 409:
                return 'Conflict';
            case 410:
                return 'Gone';
            case 411:
                return 'Length Required';
            case 412:
                return 'Precondition Failed';
            case 413:
                return 'Request Entity Too Large';
            case 414:
                return 'Request-URI Too Long';
            case 415:
                return 'Unsupported Media Type';
            case 416:
                return 'Requested Range Not Satisfiable';
            case 417:
                return 'Expectation Failed';
            case 422:
                return 'Unprocessable Entity';
            case 500:
                return 'Internal Server Error';
            case 501:
                return 'Not Implemented';
            case 502:
                return 'Bad Gateway';
            case 503:
                return 'Service Unavailable';
            case 504:
                return 'Gateway Timeout';
            case 505:
                return 'HTTP Version Not Supported';
            default:
                return null;
        }
    }


}