<?php
namespace io;
final class Output
{

    /**
     * 以Post方式发送并得到返回数据
     * @param string $url
     * @param array $params
     * @return mixed|string
     */
    public static function post($url = '', array $params = [], $ConversionArray = true)
    {
        $value = self::send($url, $params, 'post');
        if (!$ConversionArray) return (string)$value;//不管是不是数组，直接返回
        return self::to_array($value);
    }

    /**
     * 以Get方式发送并得到返回数据
     * @param string $url
     * @param array $params
     * @return mixed|string
     */
    public static function get($url = '', array $params = [], $ConversionArray = true)
    {
        $value = self::send($url, $params, 'get');
        if (!$ConversionArray) return (string)$value;//不管是不是数组，直接返回
        return self::to_array($value);
    }

    private static function to_array($value)
    {
        $arr = json_decode($value, true);
        if (is_array($arr)) return $arr;
        return (string)$value;
    }


    /**
     * @param $url
     * @param array $data 发送的数据
     * @param string $type 请求类型,若是数表则表示为证书,同时为POST请求
     * @param null $cert 请书
     * @return mixed|string
     * 若在已知目标主机IP的情况下，修改/etc/hosts可以获得更快的速度
     */
    public static function send($url, array $data = [], $type = 'post', $cert = null)
    {
        if (is_array($type)) list($type, $cert) = ['POST', $type];

        $type = strtoupper($type);
        if (!in_array($type, ['GET', 'POST', 'PUT', 'HEAD', 'DELETE'])) $type = 'GET';

        $cURL = curl_init();   //初始化一个cURL会话，若出错，则退出。
        if ($cURL === false) return 'Create Protocol Object Error';

        switch ($type) {
            case "GET" :
                curl_setopt($cURL, CURLOPT_HTTPGET, true);
                self::serialize_url($url, $data); //GET时，需格式化数据为字符串
                break;
            case "POST":
                curl_setopt($cURL, CURLOPT_POST, true);
                break;

            case "HEAD" :   //这三种不常用，使用前须确认对方是否接受
            case "PUT" :
            case "DELETE":
                curl_setopt($cURL, CURLOPT_CUSTOMREQUEST, $type);
                break;
        }

        curl_setopt($cURL, CURLOPT_URL, $url);                 //接收页
//        curl_setopt($cURL, CURLOPT_PORT, 80);                  //端口
        curl_setopt($cURL, CURLOPT_HEADER, FALSE);              //不带出head信息
        curl_setopt($cURL, CURLOPT_DNS_CACHE_TIMEOUT, 120);     //内存中保存DNS信息，默认120秒
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 0);          //在发起连接前等待的时间，如果设置为0，则无限等待
        curl_setopt($cURL, CURLOPT_TIMEOUT, 30);                //允许执行的最长秒数，若用毫秒级，用CURLOPT_TIMEOUT_MS
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, TRUE);       //返回文本流

        if (strtoupper(substr($url, 0, 5)) === "HTTPS") {
            curl_setopt($cURL, CURLOPT_HTTP_VERSION, CURLOPT_HTTP_VERSION_2_0);
            curl_setopt($cURL, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, 2);

            if ($cert !== null) {//证书
                curl_setopt($cURL, CURLOPT_SSLCERTTYPE, 'PEM');
                curl_setopt($cURL, CURLOPT_SSLKEYTYPE, 'PEM');
                if (isset($cert['cert'])) curl_setopt($cURL, CURLOPT_SSLCERT, $cert['cert']);
                if (isset($cert['key'])) curl_setopt($cURL, CURLOPT_SSLKEY, $cert['key']);
                if (isset($cert['ca'])) curl_setopt($cURL, CURLOPT_CAINFO, $cert['ca']);
            }
        } else {
            curl_setopt($cURL, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
        }

        //显式指定使用IPv4解析
        if (defined('CURLOPT_IPRESOLVE') and defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($cURL, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        //从可靠的角度，推荐指定CURL_SAFE_UPLOAD的值，明确告知php禁止旧的@语法。
        if ($type === 'UPLOAD') {
            curl_setopt($cURL, CURLOPT_UPLOAD, true);

            if (defined('CURLOPT_SAFE_UPLOAD')) {
                //PHP5.6以后，需要指定此值，且必须在下面附加数据之前，否则对方得不到上传的数据文件
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
        curl_close($cURL);
        return $html;
    }


    /**
     * 序列化数组，将数组转为URL后接参数
     * @param $arr
     */
    private static function serialize_url(&$URL, &$arr)
    {
        if (empty($arr)) return;
        $H = !strpos($URL, '?') ? '?' : '&';
        $URL .= $H . http_build_query($arr);
        $arr = null;
    }


}