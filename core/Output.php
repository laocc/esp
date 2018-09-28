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
    public static function post(string $url, array $params = [], array $option = [], $autoVal = '')
    {
        $option['type'] = 'post';
        return self::curl($url, $params, $option);
    }

    /**
     * 以Get方式发送并得到返回数据
     * @param string $url
     * @param array $option
     * @param string $autoVal
     * @return bool|float|int|mixed|null|string
     */
    public static function get(string $url, array $option = [], $autoVal = '')
    {
        $option['type'] = 'get';
        return self::curl($url, null, $option);
    }

    public static function upload()
    {

    }

    /**
     * @param $url
     * @param $data
     * @param string $type
     * @param array $option
     * @param null $autoVal
     * @return bool|float|int|mixed|null|string
     * 若在已知目标主机IP的情况下，修改/etc/hosts可以获得更快的速度
     *
     * $option['port'] 对方端口
     * $option['gzip'] 被读取的页面有gzip压缩
     * $option['headers'] 带出的头信息
     * $option['transfer'] 返回文本流全部信息
     * $option['agent'] 模拟的客户端UA信息
     * $option['proxy'] 代理服务器IP
     * $option['cookies'] 带出的Cookies信息
     * $option['referer'] 指定来路URL，用于欺骗
     * $option['cert'] 带证书
     */
    public static function send($url, $data = null, $type = 'post', array $option = null, $autoVal = null)
    {
        if (empty($url)) return 'empty API url';

        $type = strtoupper($type);
        if (!in_array($type, ['GET', 'POST', 'PUT', 'HEAD', 'DELETE'])) $type = 'GET';
        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        if ($cURL === false) return 'Create Protocol Object Error';
        if (!isset($option['headers'])) $option['headers'] = Array();
        if (!is_array($option['headers'])) $option['headers'] = [$option['headers']];
        if (isset($option['agent'])) {
            if ($option['agent'] === 'default') $option['agent'] = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/63.0.1364.172 Safari/537.22';
            else if ($option['agent'] === 'mobile') $option['agent'] = 'Mozilla/5.0 (Linux; Android 7.0; CPN-AL00 Build/HUAWEICPN-AL00) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30';
            else if ($option['agent'] === 'firefox') $option['agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:57.0) Gecko/20100101 Firefox/57.0';
        }

//        curl_setopt($cURL, CURLOPT_STDERR, root('/cache/curl'));
//        curl_setopt($cURL, CURLOPT_VERBOSE, true);//TRUE 会输出所有的信息，写入到STDERR，或在CURLOPT_STDERR中指定的文件。CURL报告每一件意外的事情

        switch ($type) {
            case "GET" :
                curl_setopt($cURL, CURLOPT_HTTPGET, true);
                if (!empty($data)) self::serialize_url($url, $data); //GET时，需格式化数据为字符串
                break;
            case "POST":
                curl_setopt($cURL, CURLOPT_POST, true);
                $option['headers'][] = "X-HTTP-Method-Override: POST";
                break;
            case "HEAD" :   //这三种不常用，使用前须确认对方是否接受
            case "PUT" :
            case "DELETE":
                break;
        }

        curl_setopt($cURL, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($cURL, CURLOPT_URL, $url);                                                      //接收页
        curl_setopt($cURL, CURLOPT_FRESH_CONNECT, true);                                            //强制新连接，不用缓存中的
        if (isset($option['port'])) curl_setopt($cURL, CURLOPT_PORT, intval($option['port']));      //端口
        if (isset($option['proxy'])) curl_setopt($cURL, CURLOPT_PROXY, $option['proxy']);           //指定代理
        if (isset($option['cookies'])) curl_setopt($cURL, CURLOPT_COOKIE, $option['cookies']);      //带Cookies
        if (isset($option['referer']) and $option['referer']) curl_setopt($cURL, CURLOPT_REFERER, $option['referer']);//来源页
        if (isset($option['gzip']) and $option['gzip']) curl_setopt($cURL, CURLOPT_ENCODING, "gzip");   //有压缩
        if (isset($option['agent'])) curl_setopt($cURL, CURLOPT_USERAGENT, $option['agent']);           //客户端UA
        if (!empty($option['headers'])) curl_setopt($cURL, CURLOPT_HTTPHEADER, $option['headers']);     //头信息

        curl_setopt($cURL, CURLOPT_HEADER, (isset($option['transfer']) and $option['transfer']));//带回头信息
        curl_setopt($cURL, CURLOPT_DNS_CACHE_TIMEOUT, 120);     //内存中保存DNS信息，默认120秒
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 10);         //在发起连接前等待的时间，如果设置为0，则无限等待
        curl_setopt($cURL, CURLOPT_TIMEOUT, 10);                //允许执行的最长秒数，若用毫秒级，用CURLOPT_TIMEOUT_MS
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, TRUE);       //返回文本流
        curl_setopt($cURL, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);//指定使用IPv4解析

        if (strtoupper(substr($url, 0, 5)) === "HTTPS") {
//            curl_setopt($cURL, CURLOPT_HTTP_VERSION, CURLOPT_HTTP_VERSION_2_0);
//            curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, true);
//            curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, false);//禁止 cURL 验证对等证书，就是不验证对方证书
            curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);

            if (isset($option['cert'])) {//证书

//                curl_setopt($cURL, CURLOPT_CERTINFO, true);//TRUE 将在安全传输时输出 SSL 证书信息到 STDERR。

                curl_setopt($cURL, CURLOPT_SSLCERTTYPE, 'PEM');
                curl_setopt($cURL, CURLOPT_SSLKEYTYPE, 'PEM');
                if (isset($option['cert']['cert'])) curl_setopt($cURL, CURLOPT_SSLCERT, $option['cert']['cert']);
                if (isset($option['cert']['key'])) curl_setopt($cURL, CURLOPT_SSLKEY, $option['cert']['key']);
                if (isset($option['cert']['ca'])) curl_setopt($cURL, CURLOPT_CAINFO, $option['cert']['ca']);
            }
        } else {
            curl_setopt($cURL, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }

        //从可靠的角度，推荐指定CURL_SAFE_UPLOAD的值，明确告知php禁止旧的@语法。
        if ($type === 'UPLOAD') {
            curl_setopt($cURL, CURLOPT_UPLOAD, true);

            if (defined('CURLOPT_SAFE_UPLOAD')) {
                //PHP5.6以后，需要指定此值，且须在附加数据之前，否则对方得不到上传的数据文件
                curl_setopt($cURL, CURLOPT_SAFE_UPLOAD, false);
            } elseif (class_exists('\CURLFile')) {//低版本
                curl_setopt($cURL, CURLOPT_SAFE_UPLOAD, true);
            }
        }

        //提交上传数据放在最后
        if (!!$data) curl_setopt($cURL, CURLOPT_POSTFIELDS, $data);

        $html = curl_exec($cURL);
        $err = curl_error($cURL);
        if ($err) return $err;
        if (isset($option['transfer']) and $option['transfer']) {
            $headerSize = curl_getinfo($cURL, CURLINFO_HEADER_SIZE);
            return [self::header(substr($html, 0, $headerSize)), trim(substr($html, $headerSize))];
        }
        curl_close($cURL);
        $html = trim($html);
        if (empty($html)) return $autoVal;
        if (is_int($autoVal)) return intval($html);
        if (is_float($autoVal)) return floatval($html);
        if (is_bool($autoVal)) return boolval($html);
        if (is_array($autoVal)) return self::to_array($html);
        return $html;
    }


    private static function curl(string $url, array $data = null, array $option = null)
    {
        if (empty($url)) return 'empty API url';

        $option['type'] = strtoupper($option['type']);
        if (!in_array($option['type'], ['GET', 'POST', 'PUT', 'HEAD', 'DELETE'])) $option['type'] = 'GET';
        if (!isset($option['headers'])) $option['headers'] = Array();
        if (!is_array($option['headers'])) $option['headers'] = [$option['headers']];
        if (isset($option['agent'])) {
            if ($option['agent'] === 'default') $option['agent'] = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/63.0.1364.172 Safari/537.22';
            else if ($option['agent'] === 'mobile') $option['agent'] = 'Mozilla/5.0 (Linux; Android 7.0; CPN-AL00 Build/HUAWEICPN-AL00) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30';
            else if ($option['agent'] === 'firefox') $option['agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:57.0) Gecko/20100101 Firefox/57.0';
        }
        $cOption = [];

        if (0) {
            $cOption[CURLOPT_VERBOSE] = true;//输出所有的信息，写入到STDERR(直接打印到屏幕)
//        $cOption[CURLOPT_STDERR] = root('/cache/curl');//若不指定，则输出到屏幕
            $cOption[CURLOPT_CERTINFO] = true;//TRUE 将在安全传输时输出 SSL 证书信息到 STDERR。
            $cOption[CURLOPT_FAILONERROR] = true;//当 HTTP 状态码大于等于 400，TRUE 将将显示错误详情。 默认情况下将返回页面，忽略 HTTP 代码。
        }

        if (isset($option['redirect'])) {
            $cOption[CURLOPT_FOLLOWLOCATION] = true;//根据服务器返回 HTTP 头中的 "Location: " 重定向
            $cOption[CURLOPT_MAXREDIRS] = $option['redirect'];//指定最多的 HTTP 重定向次数
            $cOption[CURLOPT_POSTREDIR] = 1;//什么情况下需要再次 HTTP POST 到重定向网址:1 (301 永久重定向), 2 (302 Found) 和 4 (303 See Other)
            $cOption[CURLOPT_UNRESTRICTED_AUTH] = 1;//重定向时，时继续发送用户名和密码信息，哪怕主机名已改变
        }


        switch ($option['type']) {
            case "GET" :
                $cOption[CURLOPT_HTTPGET] = true;
                if (!empty($data)) self::serialize_url($url, $data); //GET时，需格式化数据为字符串
                break;
            case "POST":
                $cOption[CURLOPT_POST] = true;//类型为：application/x-www-form-urlencoded
                $option['headers'][] = "X-HTTP-Method-Override: POST";
                break;
            case "HEAD" :   //这三种不常用，使用前须确认对方是否接受
            case "PUT" :
            case "DELETE":
                break;
        }

//        $option['headers'][] = "Cache-Control: no-cache";
        $option['headers'][] = "Cache-Control: max-age=0";
        $option['headers'][] = "Connection: keep-alive";
//        $option['headers'][] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $option['headers'][] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.5,image/webp,image/apng,*/*;q=0.8";

        if (isset($option['lang'])) {
            if ($option['lang'] === 'en') {
                $option['headers'][] = "Accept-Language: en-us,en;q=0.5";
            } else {
                $option['headers'][] = "Accept-Language: zh-CN,zh;q=0.9";
            }
        }

//        $cOption[CURLOPT_AUTOREFERER] = true;//根据 Location: 重定向时，自动设置 header 中的Referer:信息


        $cOption[CURLOPT_CUSTOMREQUEST] = $option['type'];
        $cOption[CURLOPT_URL] = $url;                                                      //接收页
        $cOption[CURLOPT_FRESH_CONNECT] = true;                                            //强制新连接，不用缓存中的
        if (isset($option['port'])) $cOption[CURLOPT_PORT] = intval($option['port']);      //端口

        if (isset($option['proxy'])) {//指定代理
            $cOption[CURLOPT_PROXY] = $option['proxy'];
//            $cOption[CURLOPT_PROXYPORT] = 80;  //代理的端口
        }

        if (isset($option['cookies'])) $cOption[CURLOPT_COOKIE] = $option['cookies'];      //带Cookies
        if (isset($option['referer']) and $option['referer']) $cOption[CURLOPT_REFERER] = $option['referer'];//来源页
        if (isset($option['gzip']) and $option['gzip']) $cOption[CURLOPT_ENCODING] = "gzip, deflate";   //有压缩
        if (isset($option['agent'])) $cOption[CURLOPT_USERAGENT] = $option['agent'];           //客户端UA
        if (!empty($option['headers'])) $cOption[CURLOPT_HTTPHEADER] = $option['headers'];     //头信息

        $cOption[CURLOPT_HEADER] = (isset($option['transfer']) and $option['transfer']);//带回头信息
        $cOption[CURLOPT_DNS_CACHE_TIMEOUT] = 120;     //内存中保存DNS信息，默认120秒
        $cOption[CURLOPT_CONNECTTIMEOUT] = 10;         //在发起连接前等待的时间，如果设置为0，则无限等待
        $cOption[CURLOPT_TIMEOUT] = 10;                //允许执行的最长秒数，若用毫秒级，用CURLOPT_TIMEOUT_MS
        $cOption[CURLOPT_RETURNTRANSFER] = TRUE;       //返回文本流
        $cOption[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;//指定使用IPv4解析

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

        //从可靠的角度，推荐指定CURL_SAFE_UPLOAD的值，明确告知php禁止旧的@语法。
        if ($option['type'] === 'UPLOAD') {
            $cOption[CURLOPT_UPLOAD] = true;

            if (defined('CURLOPT_SAFE_UPLOAD')) {
                //PHP5.6以后，需要指定此值，且须在附加数据之前，否则对方得不到上传的数据文件
                $cOption[CURLOPT_SAFE_UPLOAD] = false;
            } elseif (class_exists('\CURLFile')) {//低版本
                $cOption[CURLOPT_SAFE_UPLOAD] = true;
            }
        }

        if (!!$data) $cOption[CURLOPT_POSTFIELDS] = $data;////提交上传数据放在最后

        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        if ($cURL === false) return 'Create Protocol Object Error';
        pre($cOption);
        curl_setopt_array($cURL, $cOption);
        $html = curl_exec($cURL);
        $err = curl_error($cURL);

//        curl_getinfo($cURL, CURLINFO_COOKIELIST);

        if ($err) {
            return ['error' => $err, 'info' => curl_getinfo($cURL, CURLINFO_COOKIELIST)];
        }

        if (isset($option['transfer']) and $option['transfer']) {
            $headerSize = curl_getinfo($cURL, CURLINFO_HEADER_SIZE);
            return [self::header(substr($html, 0, $headerSize)), trim(substr($html, $headerSize))];
        }
        curl_close($cURL);
        return trim($html);
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


    /**
     * 序列化数组，将数组转为URL后接参数
     * @param string $URL
     * @param array $arr
     */
    private static function serialize_url(string &$URL, array &$arr)
    {
        if (empty($arr)) return;
        $H = !strpos($URL, '?') ? '?' : '&';
        $URL .= $H . http_build_query($arr);
    }


}