<?php

namespace esp\core;


class RPC
{
    public static function put($uri, $filename, $data)
    {
//        print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]);
        if (getenv('SERVER_ADDR') === _RPC['ip']) return false;

        $opt = [];
        $opt['type'] = 'post';
        $opt['host'] = [implode(':', _RPC)];
        if (is_array($data)) $data = json_encode($data, 64 | 128 | 256);

        $post = ['filename' => $filename, 'data' => $data];
        $uri = '/' . ltrim($uri, '/');
        $api = sprintf('http://%s:%s%s?host=%s&filename=%s', _RPC['host'], _RPC['port'], $uri, getenv('HTTP_HOST'), base64_encode($filename));

        $r = Output::request($api, $post, $opt);
        return true;
    }

    public static function get($uri, bool $json = false)
    {
        if (_RPC['ip'] === getenv('SERVER_ADDR')) return null;

        $opt = [];
        $opt['host'] = [implode(':', _RPC)];
        $content = Output::request(sprintf('http://%s:%s', _RPC['host'], _RPC['port']) . $uri, $opt);

        if ($content['error'] > 0) return $content['message'];
        if (!$json) return $content['html'];
        $array = json_decode($content['html'], true);
        if (is_array($array)) return $array;
        return false;
    }

}