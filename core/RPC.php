<?php

namespace esp\core;


class RPC
{

    public static function post(string $uri, $data)
    {
        if ((_MODULE === 'rpc') or (!_DEBUG and _RPC['ip'] === getenv('SERVER_ADDR'))) {
            if (isset($data['self'])) return $data['self']($data);
            return null;
        }
        $opt = [];
        $opt['type'] = 'post';
        $opt['encode'] = 'json';
        $opt['timeout'] = 3;
        $opt['host'] = [implode(':', _RPC)];

        $uri = '/' . ltrim($uri, '/');
        $req = Output::request(sprintf('http://%s:%s%s', _RPC['host'], _RPC['port'], $uri), $data, $opt);
        if ($req['error'] > 0) return $req['message'];
        return $req['array'];
    }

    public static function get(string $uri, bool $json = false)
    {
        if (_MODULE === 'rpc') return null;

        $opt = [];
        $opt['type'] = 'get';
        $opt['timeout'] = 3;
        $opt['host'] = [implode(':', _RPC)];
        if ($json) $opt['encode'] = 'json';
        $uri = '/' . ltrim($uri, '/');
        $content = Output::request(sprintf('http://%s:%s%s', _RPC['host'], _RPC['port'], $uri), $opt);
        if ($content['error'] > 0) return $content['message'];
        return $json ? $content['array'] : $content['html'];
    }

}