<?php

namespace esp\core;

final class Output
{

    /**
     * 以Post方式发送并得到返回数据
     * @param string $url
     * @param array $params
     * @param array $option
     * @param string $autoVal
     * @return bool|float|int|mixed|null|string
     */
    public static function post(string $url, $params = null, array $option = [])
    {
        $option['type'] = 'post';
        $post = self::curl($url, $params, $option);
        if ($post['error']) return $post['message'];
        return $post['html'];
    }

    /**
     * 以Get方式发送并得到返回数据
     * @param string $url
     * @param array $option
     * @param string $autoVal
     * @return bool|float|int|mixed|null|string
     */
    public static function get(string $url, array $option = [])
    {
        $option['type'] = 'get';
        $get = self::curl($url, null, $option);
        return $get['html'];
    }

    public static function upload()
    {

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
    public static function curl(string $url, $data = null, array $option = [])
    {
        $response = [];
        $response['error'] = 100;

        if (empty($url)) {
            $response['message'] = 'empty API url';
            return $response;
        }

        if (!isset($option['headers'])) $option['headers'] = Array();
        if (!is_array($option['headers'])) $option['headers'] = [$option['headers']];

        if (isset($option['agent'])) {
            if (strpos($option['agent'], '.') === false) $option['agent'] = "win.{$option['agent']}";
            $agent = Config::ua($option['agent']);
            if ($agent) $option['agent'] = $agent;
        }

        $cOption = [];

        if (0) {
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
                if (!is_ip($option['host'])) {
                    $response['message'] = 'host must be a IP address';
                    return $response;
                }

                $urlDom = explode('/', $url);

                if (1 or version_compare((curl_version())['version'], '7.21.3', '>=')) {//cURL 7.21.3 以上可用
                    if (strpos($urlDom[2], ':')) {//将端口移到port中
                        $dom = explode(':', $urlDom[2]);
                        $urlDom[2] = $dom[0];
                        $option['port'] = intval($dom[1]);
                    } else if (strtolower(substr($url, 0, 5)) === 'https') {
                        $option['port'] = 443;
                    } else {
                        $option['port'] = 80;
                    }

                    $cOption[CURLOPT_RESOLVE] = ["{$urlDom[2]}:{$option['port']}:{$option['host']}"];

                } else {
                    $urlDom[0] = 'http:';//只能用http
                    if (strpos($urlDom[2], ':')) {//将端口移到port中
                        $dom = explode(':', $urlDom[2]);
                        $urlDom[2] = $dom[0];
                        $option['port'] = intval($dom[1]);
                    }
                    $option['headers'][] = "HOST: {$urlDom[2]}";
                    $urlDom[2] = $option['host'];
                    $url = implode('/', $urlDom);
                }
            }
        }

//        $option['headers'][] = "Cache-Control: no-cache";
        $option['headers'][] = "Cache-Control: max-age=0";
        $option['headers'][] = "Connection: keep-alive";
        $option['headers'][] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.5,image/webp,image/apng,*/*;q=0.8";

        if (isset($option['lang'])) {
            if ($option['lang'] === 'en') {
                $option['headers'][] = "Accept-Language: en-us,en;q=0.5";
            } elseif ($option['lang'] === 'cn') {
                $option['headers'][] = "Accept-Language: zh-CN,zh;q=0.9";
            }
        }

        if (isset($option['redirect'])) {
            $cOption[CURLOPT_MAXREDIRS] = max($option['redirect'], 2);//指定最多的 HTTP 重定向次数，最小要为2
            $cOption[CURLOPT_POSTREDIR] = 1;//什么情况下需要再次 HTTP POST 到重定向网址:1 (301 永久重定向), 2 (302 Found) 和 4 (303 See Other)
            $cOption[CURLOPT_FOLLOWLOCATION] = true;//根据服务器返回 HTTP 头中的 "Location: " 重定向
            $cOption[CURLOPT_AUTOREFERER] = true;//根据 Location: 重定向时，自动设置 header 中的Referer:信息
            $cOption[CURLOPT_UNRESTRICTED_AUTH] = 1;//重定向时，时继续发送用户名和密码信息，哪怕主机名已改变
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

        if (isset($option['cookies'])) {//带Cookies
            if (substr($option['cookies'], 0, 1) === '/') {
                $cOption[CURLOPT_COOKIEFILE] = $option['cookies'];
                $cOption[CURLOPT_COOKIEJAR] = $option['cookies'];
            } else {
                $cOption[CURLOPT_COOKIE] = $option['cookies'];
            }
        }

        if (isset($option['proxy'])) $cOption[CURLOPT_PROXY] = $option['proxy'];            //指定代理
        if (isset($option['referer']) and $option['referer']) $cOption[CURLOPT_REFERER] = $option['referer'];//来源页
        if (isset($option['gzip']) and $option['gzip']) $cOption[CURLOPT_ENCODING] = "gzip, deflate";   //有压缩
        if (isset($option['agent'])) $cOption[CURLOPT_USERAGENT] = $option['agent'];           //客户端UA
        if (!empty($option['headers'])) $cOption[CURLOPT_HTTPHEADER] = $option['headers'];     //头信息

        $cOption[CURLOPT_HEADER] = (isset($option['transfer']) and $option['transfer']);//带回头信息
        $cOption[CURLOPT_DNS_CACHE_TIMEOUT] = 120;     //内存中保存DNS信息，默认120秒
        $cOption[CURLOPT_CONNECTTIMEOUT] = $option['wait'] ?? 10;         //在发起连接前等待的时间，如果设置为0，则无限等待
        $cOption[CURLOPT_TIMEOUT] = ($option['timeout'] ?? 10);    //允许执行的最长秒数，若用毫秒级，用CURLOPT_TIMEOUT_MS
        $cOption[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;//指定使用IPv4解析
        $cOption[CURLOPT_RETURNTRANSFER] = true;       //返回文本流，若不指定则是直接打印

        if (strtoupper(substr($url, 0, 5)) === "HTTPS") {
//            $cOption[CURLOPT_HTTP_VERSION]=CURLOPT_HTTP_VERSION_2_0;
//            $cOption[CURLOPT_SSL_VERIFYPEER]=true;
//            $cOption[CURLOPT_SSL_VERIFYHOST]=2;
            $cOption[CURLOPT_SSL_VERIFYPEER] = false;//禁止 cURL 验证对等证书，就是不验证对方证书
            $cOption[CURLOPT_SSL_VERIFYHOST] = false;

            if (isset($option['cert'])) {//证书
                $cOption[CURLOPT_SSLCERTTYPE] = 'PEM';
                $cOption[CURLOPT_SSLKEYTYPE] = 'PEM';
                if (isset($option['cert']['cert'])) $cOption[CURLOPT_SSLCERT] = $option['cert']['cert'];
                if (isset($option['cert']['key'])) $cOption[CURLOPT_SSLKEY] = $option['cert']['key'];
                if (isset($option['cert']['ca'])) $cOption[CURLOPT_CAINFO] = $option['cert']['ca'];
            }
        } else {
            $cOption[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
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
                if (is_array($data)) $data = json_encode($data, 256);
                $option['headers'][] = "X-HTTP-Method-Override: POST";
                $cOption[CURLOPT_POST] = true;//类型为：application/x-www-form-urlencoded
                $cOption[CURLOPT_POSTFIELDS] = $data;
                break;

            case 'UPLOAD':
                $field = (isset($option['field']) ? $option['field'] : 'files');
                $option['headers'][] = "X-HTTP-Method-Override: POST";
                $option['headers'][] = "Content-Type: multipart/form-data; boundary=-------------" . uniqid();

                if (!is_array($data)) {
                    $response['message'] = '上传数据只能为数组，被上传的文件置于files字段内';
                    return $response;
                }
                if (isset($data['files'])) {
                    foreach ($data['files'] as $fil => $file) {
                        $data["{$field}[{$fil}]"] = new \CURLFile($file);
                    }
                    unset($data['files']);
                }
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

        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        if ($cURL === false) {
            $response['message'] = 'Create Protocol Object Error';
            return $response;
        }
        $response['post'] = $data;
        $response['option'] = $cOption;

        curl_setopt_array($cURL, $cOption);
        $response['html'] = curl_exec($cURL);
        $response['info'] = curl_getinfo($cURL);

        if (($err = curl_errno($cURL)) > 0) {
            $response['error'] = $err;
            $response['url'] = $url;
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

        if (isset($option['charset'])) {
            $response['html'] = iconv(strtoupper($option['charset']), 'UTF-8//IGNORE', $response['html']);
        }

        if (intval($response['info']['http_code']) !== 200) {
            $response['error'] = intval($response['info']['http_code']);
            if ($response['error'] === 0) $response['error'] = 10;
            $response['url'] = $url;
            $response['message'] = $response['html'];
            unset($response['html']);
            return $response;
        }

        if (isset($option['encode'])) {
            if ($option['encode'] === 'json') {
                $response['array'] = json_decode($response['html'], true);
            } else if ($option['encode'] === 'xml') {
                $response['array'] = (array)simplexml_load_string(trim($response['html']), 'SimpleXMLElement', LIBXML_NOCDATA);
            }
        }


        end:
        return $response;
    }

    private static function header(string $text)
    {
        $line = explode("\r\n", trim($text));
        $arr = Array();
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


    private static function to_array($value)
    {
        $arr = json_decode($value, true);
        if (is_array($arr)) return $arr;
        return (string)$value;
    }


}