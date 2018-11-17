<?php

namespace esp\core;


class Check
{

    /**
     * 检查来路是否本站相同域名
     * 本站_HOST，总是被列入查询，另外自定义更多的host，
     * 若允许本站或空来路，则用：$this->check_host('');
     *
     * @param array ...$host
     * @throws \Exception
     */
    public static function referer(...$host)
    {
        if (isset($host[0]) and is_array($host[0])) $host = $host[0];
        if (!in_array(host(Request::getReferer()), array_merge([_HOST], $host))) {
            throw new \Exception('禁止接入', 401);
        }
    }

    /**
     * @throws \Exception
     */
    public static function mozilla(string $txt = '禁止接入')
    {
        if (!Client::is_Mozilla()) throw new \Exception($txt, 401);
    }

    /**
     * 爬虫
     */
    public static function spider(string $txt = '')
    {
        if (Client::is_spider()) exit($txt);
    }


}