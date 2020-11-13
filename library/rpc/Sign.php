<?php

namespace library\rpc;


trait Sign
{

    public function sign_create($key, $token, $host, array $arr)
    {
        $arr[$key] = self::make_sign($key, $token, $host, $arr);
        return $arr;
    }

    public function sign_check(array $arr)
    {
        if (!isset($arr[$key])) return false;
        $sign = self::make_sign($key, $token, $host, $arr);
        return hash_equals($sign, $arr[$key]);
    }

    private function make_sign($key, $token, $host, array $arr)
    {
        ksort($arr);
        $host .= $token;
        foreach ($arr as $k => $v) {
            if ($k !== $key) $host .= "&{$k}=$v";
        }
        return md5($host);
    }


}