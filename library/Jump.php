<?php
declare(strict_types=1);

namespace esp\library;

/**
 * Class Jump
 * @package esp\library
 *
 * 两个系统后台相互跳
 * 条件：
 * 1，两个服务器的时间相差不能太大；
 * 2，跳入链接有效时间60秒，在临近58秒以上时跳入有可能会失败。
 *
 * 程序容错1秒
 *
 */
final class Jump
{
    private $token = '0ad4b59c4cbf7423a8e7f4cf178ab11a';

    public function __construct(string $token = '')
    {
        if ($token) $this->token = $token;
    }

    public function encode(int $userID, string $userName, $extend = ''): string
    {
        if (!$extend) $extend = _TIME;
        $extend = serialize($extend);
        $sign = md5(date('YmdHi', _TIME) . $userID . $this->token . $userName . $extend);
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
        $time = _TIME;
        $sign = md5(date('YmdHi', $time) . $data['u'] . $this->token . $data['n'] . ($data['e'] ?? ''));
        if ($sign !== $data['s']) {
            $sign = md5(date('YmdHi', $time - 1) . $data['u'] . $this->token . $data['n'] . ($data['e'] ?? ''));
            if ($sign !== $data['s']) return [];
        }
        $ext = unserialize($data['e'] ?? '');
        return ['id' => $data['u'], 'name' => $data['n'], 'extend' => $ext];
    }


}