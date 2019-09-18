<?php

namespace esp\core;


class RPC
{
    public static function put($uri, $filename, $data)
    {
        if (getenv('SERVER_ADDR') === _RPC['ip']) return false;

        $opt = [];
        $opt['type'] = 'post';
        $opt['encode'] = 'json';
        $opt['host'] = [implode(':', _RPC)];
        if (is_array($data)) $data = json_encode($data, 64 | 128 | 256);

        $post = ['filename' => $filename, 'data' => $data];
        $uri = '/' . ltrim($uri, '/');
        $api = sprintf('http://%s:%s%s?host=%s&filename=%s', _RPC['host'], _RPC['port'], $uri, getenv('HTTP_HOST'), base64_encode($filename));

        $req = Output::request($api, $post, $opt);
        if ($req['error'] > 0) return $req['message'];
        return $req['array'];
    }

    public static function get($uri, bool $json = false)
    {
        if (_RPC['ip'] === getenv('SERVER_ADDR')) return null;

        $opt = [];
        $opt['host'] = [implode(':', _RPC)];
        if ($json) $opt['encode'] = 'json';
        $content = Output::request(sprintf('http://%s:%s', _RPC['host'], _RPC['port']) . $uri, $opt);
        if ($content['error'] > 0) return $content['message'];
        return $json ? $content['array'] : $content['html'];
    }

}