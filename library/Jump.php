<?php

namespace esp\library;


class Jump
{
    private $token = '0ad4b59c4cbf7423a8e7f4cf178ab11a';

    public function __construct(string $token = '')
    {
        if ($token) $this->token = $token;
    }

    public function encode($userID, $userName, $extend = ''): string
    {
        if (is_array($extend)) $extend = json_encode($extend, 256 | 64);
        $sign = md5(date('YmdHi') . $userID . $this->token . $userName . $extend);
        $data = [
            'u' => $userID,
            'n' => $userName,
            'e' => $extend,
            's' => $sign,
        ];
        return urlencode(base64_encode(json_encode($data, 256 | 64)));
    }


    public function decode(string $code): array
    {
        $str = urldecode($code);
        if (!$str) return [];
        $json = base64_decode($str);
        if (!$json) return [];
        $data = json_decode($json, true);
        if (!$data) return [];
        if (!isset($data['u']) or !isset($data['n']) or !isset($data['s'])) return [];
        $sign = md5(date('YmdHi') . $data['u'] . $this->token . $data['n'] . ($data['e'] ?? ''));
        if ($sign !== $data['s']) return [];
        return ['id' => $data['u'], 'name' => $data['u'], 'extend' => $data['e']];
    }


}