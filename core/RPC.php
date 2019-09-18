<?php

namespace esp\core;


class RPC
{
    public static function put(string $uri, string $filename, $data)
    {
        return self::post($uri, $filename, $data);
    }

    public static function post(string $uri, string $filename, $data)
    {
        if (getenv('SERVER_ADDR') === _RPC['ip']) return false;

        $opt = [];
        $opt['type'] = 'post';
        $opt['encode'] = 'json';
        $opt['host'] = [implode(':', _RPC)];
        if (is_array($data)) $data = json_encode($data, 64 | 128 | 256);

        $post = ['filename' => $filename, 'data' => $data];
        $uri = '/' . ltrim($uri, '/');
        $req = Output::request(sprintf('http://%s:%s%s', _RPC['host'], _RPC['port'], $uri), $post, $opt);
        if ($req['error'] > 0) return $req['message'];
        return $req['array'];
    }

    public static function get(string $uri, bool $json = false)
    {
        if (_RPC['ip'] === getenv('SERVER_ADDR')) return null;

        $opt = [];
        $opt['type'] = 'get';
        $opt['host'] = [implode(':', _RPC)];
        if ($json) $opt['encode'] = 'json';
        $uri = '/' . ltrim($uri, '/');
        $content = Output::request(sprintf('http://%s:%s%s', _RPC['host'], _RPC['port'], $uri), $opt);
        if ($content['error'] > 0) return $content['message'];
        return $json ? $content['array'] : $content['html'];
    }

}