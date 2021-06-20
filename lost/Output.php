<?php
declare(strict_types=1);

namespace esp\lost;

use esp\error\EspError;
use function esp\helper\text;

final class Output
{
    private $option;
    private $url;
    private $data;
    private $value;

    public function __construct(array $option = [])
    {
        $this->option = $option;
    }

    /**
     * @param array $option
     * @return Output
     */
    public static function new(array $option = [])
    {
        return new Output($option);
    }


    public function flush()
    {
        $this->option = [];
        $this->url = '';
        $this->data = null;
        $this->value = null;
        return $this;
    }

    public function debug()
    {
        return ['url' => $this->url, 'option' => $this->option, 'data' => $this->data, 'value' => $this->value];
    }


    /**
     * @param string $uri
     * @param array $rpc
     * @return $this
     * @throws EspError
     */
    public function rpc(string $uri, array $rpc)
    {
        if (_VIRTUAL === 'rpc') throw new EspError('RPC内不能请求rpc', 1);
        $host = ['host' => $rpc['host'], 'port' => $rpc['port'], 'ip' => $rpc['ip']];
        $this->url = sprintf('http://%s:%s/%s', $host['host'], $host['port'], ltrim($uri, '/'));
        $this->option['host'] = [implode(':', $host)];
        $this->option['timeout'] = 3;
        $this->option['encode'] = 'json';
        return $this;
    }


    public function url(string $url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * post数据，若要传json，请先编码
     * @param $data
     * @return $this
     */
    public function data($data)
    {
        if (is_array($data)) $data = http_build_query($data);
        $this->data = $data;
        return $this;
    }

    /**
     * get方式读取
     * @param string $encode
     * @return array|mixed
     */
    public function get(string $encode = '')
    {
        if (!in_array($encode, ['json', 'xml', 'html', 'text', 'auto'])) $encode = '';
        $this->option['encode'] = $encode;
        $this->option['type'] = 'get';
        $this->value = $this->request($this->url, null, $this->option);
        if ($this->value['error']) return $this->value['message'];
        if (!$encode) return $this->value;
        if (in_array($encode, ['json', 'xml', 'auto'])) return $this->value['array'] ?: [];
        if ($encode === 'text') return text($this->value['html']);
        return $this->value['html'];
    }

    /**
     * post方式
     * @param string $encode
     * @return array|mixed
     */
    public function post(string $encode = '')
    {
        if (!in_array($encode, ['json', 'xml', 'html', 'text', 'auto'])) $encode = '';
        $this->option['encode'] = $encode;
        $this->option['type'] = 'post';
        $this->value = $this->request($this->url, $this->data, $this->option);
        if ($this->value['error']) return $this->value['message'];
        if (!$encode) return $this->value;
        if (in_array($encode, ['json', 'xml', 'auto'])) return $this->value['array'] ?: [];
        if ($encode === 'text') return text($this->value['html']);
        return $this->value['html'];
    }

    /**
     * 上传文件，需要同时用files/field附加文件和指定表单文件名
     * @param string $encode
     * @return array|mixed
     */
    public function upload(string $encode = '')
    {
        if (!in_array($encode, ['json', 'xml', 'html', 'text', 'auto'])) $encode = '';
        $this->option['encode'] = $encode;
        $this->option['type'] = 'upload';
        $this->value = $this->request($this->url, null, $this->option);
        if ($this->value['error']) return $this->value['message'];
        if (!$encode) return $this->value;
        if (in_array($encode, ['json', 'xml', 'auto'])) return $this->value['array'] ?: [];
        if ($encode === 'text') return text($this->value['html']);
        return $this->value['html'];
    }

    /**
     * 上传文件
     * @param string $filename
     * @param $filepath
     * @return $this
     */
    public function files(string $filename, $filepath)
    {
        $this->option['files'][$filename] = $filepath;
        return $this;
    }

    public function field(string $field)
    {
        $this->option['field'] = $field;
        return $this;
    }

    /**
     * 指定主机
     * @param string $host
     * @return $this
     */
    public function host(string $host)
    {
        $this->option['host'] = $host;
        return $this;
    }

    /**
     * 指定主机端口，若不设置，则自动判断80或443
     * @param string $port
     * @return $this
     */
    public function port(string $port)
    {
        $this->option['port'] = $port;
        return $this;
    }

    /**
     * 语言，中文cn，英文en
     * @param string $lang
     * @return $this
     */
    public function lang(string $lang = 'cn')
    {
        $this->option['lang'] = $lang;
        return $this;
    }

    /**
     * 指定referer
     * @param string $referer
     * @return $this
     */
    public function referer(string $referer)
    {
        $this->option['referer'] = $referer;
        return $this;
    }

    /**
     * 指定客户端ip
     * @param string $ip
     * @return $this
     */
    public function ip(string $ip)
    {
        $this->option['ip'] = $ip;
        return $this;
    }

    /**
     * 请求密码
     * @param string $pwd
     * @return $this
     */
    public function password(string $pwd)
    {
        $this->option['auth'] = $pwd;
        return $this;
    }

    /**
     * 是否跟随 redirect 跳转次数，最小值2
     * @param int $num
     * @return $this
     */
    public function redirect(int $num)
    {
        $this->option['redirect'] = $num;
        return $this;
    }

    /**
     * 请求等待（连接阶段）
     * @param int $scd
     * @return $this
     */
    public function wait(int $scd = 10)
    {
        $this->option['wait'] = $scd;
        return $this;
    }

    /**
     * 请求等待时间（运行阶段）
     * @param int $scd
     * @return $this
     */
    public function timeout(int $scd = 10)
    {
        $this->option['timeout'] = $scd;
        return $this;
    }

    /**
     * 指定header，可多次
     * @param string $header
     * @return $this
     */
    public function headers(string $header)
    {
        $this->option['headers'][] = $header;
        return $this;
    }

    /**
     * 指定代理服务器
     * @param string $proxy
     * @return $this
     */
    public function proxy(string $proxy)
    {
        $this->option['proxy'] = $proxy;
        return $this;
    }

    /**
     * 编码转换
     * @param string $charset
     * @return $this
     */
    public function charset(string $charset = 'utf8')
    {
        $this->option['charset'] = $charset;
        return $this;
    }

    /**
     * 设置，或读取 cookies
     * @param string|null $cookies
     * @return $this|bool|mixed|string
     */
    public function cookies(string $cookies = null)
    {
        if (is_null($cookies)) {
            if (substr($this->option['cookies'] ?? '', 0, 1) === '/') {
                $text = file_get_contents($this->option['cookies']);
                return $text;
            } else {
                return $this->option['cookies'];
            }
        }
        if ($cookies === 'temp' or $cookies === 'rand') {
            $cookies = "/tmp/ck_" . microtime(true) . mt_rand();
        }
        $this->option['cookies'] = $cookies;
        return $this;
    }

    /**
     * 浏览器
     * @param string $ua
     * @return $this
     */
    public function ua(string $ua)
    {
        $this->option['agent'] = $ua;
        return $this;
    }

    /**
     * 模仿真人
     * @return $this
     */
    public function human()
    {
        $this->option['human'] = true;
        return $this;
    }

    /**
     * gZip解压
     * @return $this
     */
    public function gzip()
    {
        $this->option['gzip'] = true;
        return $this;
    }

    /**
     * 带回信息流
     * @param bool $transfer
     * @return $this
     */
    public function transfer(bool $transfer = true)
    {
        $this->option['transfer'] = $transfer;
        return $this;
    }


    /**
     * 携带证书
     * @param $key
     * @param null $value
     * @return $this
     */
    public function cert($key, $value = null)
    {
        if (is_array($key)) {
            $this->option['cert'] = $key;
        } else {
            //cert,key,ca
            $this->option['cert'][$key] = $value;
        }
        return $this;
    }


    /**
     * 直接设置
     * @param string $key
     * @param $value
     * @return $this
     */
    public function set(string $key, $value)
    {
        $this->option[$key] = $value;
        return $this;
    }


    /**
     * @param string $url
     * @param null $data
     * @param array $option
     * @return array
     *
     * $option['type']      请求方式，get,post,upload
     * $option['port']      对方端口
     * $option['gzip']      被读取的页面有gzip压缩
     * $option['headers']   带出的头信息
     * $option['transfer']  返回文本流全部信息，在返回的header里
     * $option['agent']     模拟的客户端UA信息
     * $option['proxy']     代理服务器IP
     * $option['cookies']   带出的Cookies信息，或cookies文件
     * $option['referer']   指定来路URL
     * $option['cert']      带证书
     * $option['charset']   目标URL编码，转换为utf-8
     * $option['redirect']  是否跟着跳转，>0时为跟着跳
     * $option['encode']    将目标html转换为数组，在返回的array里，可选：json,xml
     * $option['host']      目标域名解析成此IP
     * $option['ip']        客户端IP，相当于此cURL变成一个代理服务器
     * $option['lang']      语言，cn或en
     */
    public static function request(string $url, $data = null, array $option = [])
    {
        $response = [];
        $response['error'] = 100;
        $response['message'] = '';
        $response['html'] = '';
        $response['array'] = [];

        if (empty($url)) {
            $response['message'] = '目标API为空';
            return $response;
        }

        if (is_array($data) and empty($option)) [$data, $option] = [null, $data];
        if (!isset($option['headers'])) $option['headers'] = array();
        if (!is_array($option['headers'])) $option['headers'] = [$option['headers']];

        $cOption = [];

        if ($option['echo'] ?? 0) {
            $cOption[CURLOPT_VERBOSE] = true;//输出所有的信息，写入到STDERR(直接打印到屏幕)
//        $cOption[CURLOPT_STDERR] = root('/cache/curl');//若不指定，则输出到屏幕
            $cOption[CURLOPT_CERTINFO] = true;//TRUE 将在安全传输时输出 SSL 证书信息到 STDERR。
            $cOption[CURLOPT_FAILONERROR] = true;//当 HTTP 状态码大于等于 400，TRUE 将将显示错误详情。 默认情况下将返回页面，忽略 HTTP 代码。
        }

//        if (isset($option['port'])) $cOption[CURLOPT_PORT] = intval($option['port']);      //端口

        if (isset($option['host'])) {
            if (is_array($option['host'])) {
                $cOption[CURLOPT_RESOLVE] = $option['host'];
            } else {
                if (!\esp\helper\is_ip($option['host'])) {
                    $response['message'] = 'Host必须是IP格式';
                    return $response;
                }
                $urlDom = explode('/', $url);
                if (strpos($urlDom[2], ':')) {//将端口移到port中
                    $dom = explode(':', $urlDom[2]);
                    $urlDom[2] = $dom[0];
                    $option['port'] = intval($dom[1]);
                } else if (!isset($option['port'])) {
                    if (strtolower(substr($url, 0, 5)) === 'https') {
                        $option['port'] = 443;
                    } else {
                        $option['port'] = 80;
                    }
                }
                $cOption[CURLOPT_RESOLVE] = ["{$urlDom[2]}:{$option['port']}:{$option['host']}"];
            }
        }

        if (isset($option['human'])) {
//            $option['headers'][] = "Cache-Control: no-cache";
            $option['headers'][] = "Cache-Control: max-age=0";
            $option['headers'][] = "Connection: keep-alive";
//            $option['headers'][] = "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8";
            $option['headers'][] = "Upgrade-Insecure-Requests: 1";
            $option['headers'][] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.5,image/webp,image/apng,*/*;q=0.8";
        }

        if (isset($option['lang'])) {
            if ($option['lang'] === 'en') {
                $option['headers'][] = "Accept-Language: en-us,en;q=0.8";
            } elseif ($option['lang'] === 'cn') {
                $option['headers'][] = "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8";
            }
        }

        if (isset($option['redirect'])) {
            $cOption[CURLOPT_MAXREDIRS] = max($option['redirect'], 2);//指定最多的 HTTP 重定向次数，最小要为2
            $cOption[CURLOPT_POSTREDIR] = 1;//什么情况下需要再次 HTTP POST 到重定向网址:1 (301 永久重定向), 2 (302 Found) 和 4 (303 See Other)
            $cOption[CURLOPT_FOLLOWLOCATION] = true;//根据服务器返回 HTTP 头中的 "Location: " 重定向
            $cOption[CURLOPT_AUTOREFERER] = true;//根据 Location: 重定向时，自动设置 header 中的Referer:信息
            $cOption[CURLOPT_UNRESTRICTED_AUTH] = true;//重定向时，时继续发送用户名和密码信息，哪怕主机名已改变
        }

        $cOption[CURLOPT_URL] = $url;                                                      //接收页
        $cOption[CURLOPT_FRESH_CONNECT] = true;                                            //强制新连接，不用缓存中的

        if (isset($option['ip'])) {     //指定客户端IP
            $option['headers'][] = "CLIENT-IP: {$option['ip']}";
            $option['headers'][] = "X-FORWARDED-FOR: {$option['ip']}";
        }

        foreach ($option['headers'] as $k => $h) {
            if (is_string($k)) {
                $option['headers'][] = "{$k}: {$h}";
                unset($option['headers'][$k]);
            }
        }

        if (isset($option['cookies']) and !empty($option['cookies'])) {//带Cookies
            if ($option['cookies'][0] === '/') {
                $cOption[CURLOPT_COOKIEFILE] = $option['cookies'];
                $cOption[CURLOPT_COOKIEJAR] = $option['cookies'];
            } else {
                $cOption[CURLOPT_COOKIE] = $option['cookies'];//直接指定值
            }
        }

        $option['type'] = strtoupper($option['type'] ?? 'get');
        if (!in_array($option['type'], ['GET', 'POST', 'PUT', 'HEAD', 'DELETE', 'UPLOAD'])) $option['type'] = 'GET';
        switch ($option['type']) {
            case "GET" :
                $cOption[CURLOPT_HTTPGET] = true;
                if (!empty($data)) {//GET时，需格式化数据为字符串
                    if (is_array($data)) $data = http_build_query($data);
                    $url .= (!strpos($url, '?') ? '?' : '&') . $data;
                }
                break;

            case "POST":
                if (is_array($data)) $data = json_encode($data, 256 | 64);
//                $option['headers'][] = "X-HTTP-Method-Override: POST";
                $option['headers'][] = "Expect: ";  //post大于1024时，会带100 ContinueHTTP标头的请求，加此指令禁止
                $cOption[CURLOPT_POST] = true;      //类型为：application/x-www-form-urlencoded
                $cOption[CURLOPT_POSTFIELDS] = $data;
                break;

            case 'UPLOAD':
                $field = (isset($option['field']) ? $option['field'] : 'files');
//                $option['headers'][] = "X-HTTP-Method-Override: POST";
//                $option['headers'][] = "Content-Type: multipart/form-data; boundary=-------------" . uniqid();

                if (!is_array($data)) {
                    $response['message'] = '上传数据只能为数组，被上传的文件置于data中';
                    return $response;
                }
//                if (isset($data['files'])) {
//                    foreach ($data['files'] as $fil => $file) {
//                        $data[$field] = new \CURLFile($file);
////                        $data["{$field}[{$fil}]"] = new \CURLFile($file);
//                    }
//                    unset($data['files']);
//                }
                $cOption[CURLOPT_POST] = true;
                $cOption[CURLOPT_POSTFIELDS] = $data;
                break;

            case "HEAD" :   //这三种不常用，使用前须确认对方是否接受
            case "PUT" :
            case "DELETE":
                //不确定服务器支持这个自定义方法则不要使用它。
                $cOption[CURLOPT_CUSTOMREQUEST] = $option['type'];
                break;
        }

        if (isset($option['auth'])) {
            $cOption[CURLOPT_USERPWD] = $option['auth'];
        }

        //指定代理
        if (isset($option['proxy'])) {
            if (strpos($option['proxy'], ';')) {
                $pro = explode(';', $option['proxy']);
                $cOption[CURLOPT_PROXY] = $pro[0];
                if (!empty($pro[1])) $cOption[CURLOPT_PROXYUSERPWD] = $pro[1];
            } else {
                $cOption[CURLOPT_PROXY] = $option['proxy'];
            }
        }
        if (isset($option['referer']) and $option['referer']) $cOption[CURLOPT_REFERER] = $option['referer'];//来源页
        if (isset($option['gzip']) and $option['gzip']) {//有压缩
            $option['headers'][] = "Accept-Encoding: gzip, deflate";
            $cOption[CURLOPT_ENCODING] = "gzip, deflate";
        }
        if (!empty($option['headers'])) $cOption[CURLOPT_HTTPHEADER] = $option['headers'];     //头信息
        if (isset($option['ua'])) $option['agent'] = $option['ua'];
        if (isset($option['agent'])) {
            $cOption[CURLOPT_USERAGENT] = $option['agent'];
        }

        $cOption[CURLOPT_HEADER] = (isset($option['transfer']) and $option['transfer']);        //带回头信息
        $cOption[CURLOPT_DNS_CACHE_TIMEOUT] = 120;                    //内存中保存DNS信息，默认120秒
        $cOption[CURLOPT_CONNECTTIMEOUT] = $option['wait'] ?? 10;     //在发起连接前等待的时间，如果设置为0，则无限等待
        $cOption[CURLOPT_TIMEOUT] = ($option['timeout'] ?? 10);       //允许执行的最长秒数，若用毫秒级，用CURLOPT_TIMEOUT_MS
        $cOption[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;              //指定使用IPv4解析
        $cOption[CURLOPT_RETURNTRANSFER] = true;                      //返回文本流，若不指定则是直接打印

        if (strtoupper(substr($url, 0, 5)) === "HTTPS") {

            if (isset($option['ssl']) and $option['ssl'] > 0) {
                if ($option['ssl'] > 2) $option['ssl'] = 2;
//                $cOption[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
                $cOption[CURLOPT_SSL_VERIFYPEER] = true;
                $cOption[CURLOPT_SSL_VERIFYHOST] = intval($option['ssl']);
                /**
                 * 1 是检查服务器SSL证书中是否存在一个公用名(common name)。
                 *      译者注：公用名(Common Name)一般来讲就是填写你将要申请SSL证书的域名 (domain)或子域名(sub domain)。
                 * 2，会检查公用名是否存在，并且是否与提供的主机名匹配。
                 * 0 为不检查名称。
                 * 在生产环境中，这个值应该是 2（默认值）。
                 */
            } else {
                $cOption[CURLOPT_SSL_VERIFYPEER] = false;//禁止 cURL 验证对等证书，就是不验证对方证书
                $cOption[CURLOPT_SSL_VERIFYHOST] = 0;
            }

            if (isset($option['cert'])) {       //证书
                $cOption[CURLOPT_SSLCERTTYPE] = 'PEM';
                $cOption[CURLOPT_SSLKEYTYPE] = 'PEM';
                if (isset($option['cert']['cert'])) $cOption[CURLOPT_SSLCERT] = $option['cert']['cert'];
                if (isset($option['cert']['key'])) $cOption[CURLOPT_SSLKEY] = $option['cert']['key'];
                if (isset($option['cert']['ca'])) $cOption[CURLOPT_CAINFO] = $option['cert']['ca'];
            }
        } else {
//            $cOption[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }

        $cOption[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_NONE;//自动选择http版本

        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        if ($cURL === false) {
            $response['message'] = 'cUrl初始化错误';
            return $response;
        }
        if ($option['type'] === 'POST') {
            $response['post'] = $data;
        }
        $response['option'] = $cOption;
        $time = microtime(true);
        curl_setopt_array($cURL, $cOption);
        $response['html'] = curl_exec($cURL);
        $response['info'] = curl_getinfo($cURL);
        $response['time_used'] = microtime(true) - $time;

        if (($err = curl_errno($cURL)) > 0) {
            $response['error'] = $err;
            $response['message'] = curl_error($cURL);
            return $response;
        }
        curl_close($cURL);
        $response['error'] = 0;
        $response['message'] = '';

        if (isset($option['transfer']) and $option['transfer']) {
            $response['header'] = self::header(substr($response['html'], 0, $response['info']['header_size']));
            $response['html'] = trim(substr($response['html'], $response['info']['header_size']));
        }

        if ($response['info']['content_type'] && preg_match('/charset=([gbk2312]{3,6})/i', $response['info']['content_type'], $chat)) {
            $response['html'] = mb_convert_encoding($response['html'], 'UTF-8', $chat[1]);

        } else if (isset($option['charset'])) {
            if ($option['charset'] === 'auto') {
                //自动识别gbk/gb2312转换为utf-8
                if (preg_match('/<meta.+?charset=[\'\"]?([gbk2312]{3,6})[\'\"]?/i', $response['html'], $chat)) {
                    $option['charset'] = $chat[1];
                } else {
                    $option['charset'] = null;
                }
            }
            if (is_null($option['charset'])) {
                $response['html'] = mb_convert_encoding($response['html'], 'UTF-8');
            } else {
                $response['html'] = mb_convert_encoding($response['html'], 'UTF-8', $option['charset']);
            }
        }

        if (intval($response['info']['http_code']) !== 200) {
            $response['error'] = intval($response['info']['http_code']);
            if ($response['error'] === 0) $response['error'] = 10;
            $response['message'] = $response['html'];
        }

        if (empty($response['html'])) {
            $response['message'] = '请求目标结果为空';
            $response['error'] = 400;
            return $response;
        }

        if (!isset($option['encode']) or empty($option['encode'])) return $response;


        if ($option['encode'] === 'json') {
            if ($response['html'][0] === '{' or $response['html'][0] === '[') {
                $response['array'] = json_decode($response['html'], true);
                if (empty($response['array'])) {
                    $response['array'] = [];
                    $response['error'] = 500;
                }
            } else {
                $response['message'] = '请求结果不是json格式';
                $response['error'] = 500;
            }
        } else if ($option['encode'] === 'xml') {
            if ($response['html'][0] === '<') {
                $response['array'] = (array)simplexml_load_string(trim($response['html']), 'SimpleXMLElement', LIBXML_NOCDATA);
                if (empty($response['array'])) {
                    $response['array'] = [];
                    $response['error'] = 500;
                }
            } else {
                $response['message'] = '请求结果不是xml格式';
                $response['error'] = 500;
            }
        } else if ($option['encode'] === 'array') {
            if ($response['html'][0] === '{' or $response['html'][0] === '[') {
                $response['array'] = json_decode($response['html'], true);
                if (empty($response['array'])) {
                    $response['array'] = [];
                    $response['error'] = 500;
                }
            } else if ($response['html'][0] === '<') {
                $response['array'] = (array)simplexml_load_string(trim($response['html']), 'SimpleXMLElement', LIBXML_NOCDATA);
                if (empty($response['array'])) {
                    $response['array'] = [];
                    $response['error'] = 500;
                }
            }
        }

        return $response;
    }


    private static function header(string $text)
    {
        $line = explode("\r\n", trim($text));
        $arr = array();
        foreach ($line as $i => $ln) {
            if (strpos($ln, ':')) {
                $tmp = explode(':', $ln, 2);
                $arr[strtoupper($tmp[0])] = trim($tmp[1]);
            } else {
                $arr[] = $ln;
            }
        }
        return $arr;
    }


}
