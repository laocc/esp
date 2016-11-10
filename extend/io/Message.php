<?php
namespace esp\extend\io;

use \Yaf\Registry;


class Message
{
    private $_conf;

    public function __construct()
    {
        $this->_conf = Registry::get('config')->message;
    }

    private function db()
    {
        static $db = null;
        if ($db !== null) return $db;

        if ($this->_conf->driver === 'redis') {
            return $db = new \db\Redis($this->_conf->{$this->_conf->driver});
        } else {
            return $db = new \db\Memcache($this->_conf->{$this->_conf->driver});
        }
    }

    public function check($mob, $code, $msgID = 0)
    {
        $data = $this->db()->get("sms.{$mob}");
        $check = "{$msgID}.{$code}";
        return (!!$data and ($data === $check));
    }

    public function send($mob, $message = null, $expire = 0)
    {
        if (!$this->_conf) return null;

        if (is_string($mob) and is_null($message)) {
            $message = $mob;
            $mob = Registry::get('config')->system->master->mob;
        }

        $check = $this->db()->get("ttl.{$mob}");
        if (!!$check) return '请不要频繁发送';
        $expire = $expire ?: $this->_conf->expire;
        $time = $expire / 60;
        $code = mt_rand($this->_conf->min, $this->_conf->max);
        $id = mt_rand();
        $message = $message ?: sprintf($this->_conf->template, '码农', '注册', $code, $time);
        if (is_array($message)) {
            $message = json_encode($message, 256);
        }

        $this->db()->set("sms.{$mob}", "{$id}.{$code}", $expire);
        $this->db()->set("ttl.{$mob}", "{$id}.{$code}", $this->_conf->ttl);
        $send = self::post($id, $mob, $message);
        return is_numeric($send) ? $id : $send;
    }


    /**
     * 发送短信
     * @param int $id 本系统中记录库ID
     * @param string $mob 手机号
     * @param string $body 内容
     * @return bool|int|string
     */
    private function post($id = 0, $mob = '', $body = '')
    {
        if (!$mob and !$body) return 'err info';
        if (is_array($mob)) {
            $cont = [];//#@#
            foreach ($mob as &$m) {
                if ($m) $cont[] = $body . '#@#' . $m;
            }
            return self::XinXiOne($id, implode('#@#', $cont), null, false);//群发
        } else {
            return self::XinXiOne($id, $body, $mob, true);//单发
        }
    }


    //第一信息接口
    private function XinXiOne($id, $body, $mob, $oneType = true)
    {
        $conf = $this->_conf;

        $argv = [];
        $argv['name'] = $conf->name;
        $argv['pwd'] = $conf->pwd;
        $argv['content'] = $body;
        if ($mob) $argv['mobile'] = $mob;//没手机号，则为群发
        $argv['time'] = date('Y-m-d H:i:s');
        $argv['sign'] = $conf->sign;
        $argv['type'] = $oneType ? 'pt' : 'gx';//pt为单发,gx为群发
        $argv['extno'] = $id;

        $url = $conf->interface . http_build_query($argv); //提交的url地址
        $con = file_get_contents($url);  //获取信息发送后的状态

        if (!$con) return false;
        $split = explode(',', $con . ',,,,,');
        //code,sendid,invalidcount,successcount,blackcount,msg
        //0状态,1发送编号,2无效号码数,3成功提交数,4黑名单数,5消息
        $state = (int)$split[0];
        if ($state === 0) {
            return (int)$split[1];  //发送编号

        } elseif ($state == -1) {
            return '系统异常';

        } else {
            return self::di1state($state) ?: $split[1];   //具体的错误内容
        }

    }

    private static function di1state($state)
    {
        $errInfo = [0 => '提交成功',
            1 => '含有敏感词汇',
            2 => '余额不足',
            3 => '没有号码',
            4 => '包含sql语句',
            10 => '账号不存在',
            11 => '账号注销',
            12 => '账号停用',
            13 => 'IP鉴权失败',
            14 => '格式错误'];
        return isset($errInfo[$state]) ? $errInfo[$state] : null;
    }


    public function cnSms($mob, $str)
    {
        //$url='http://api.sms.cn/mt/?uid=%s&pwd=%s&mobile=%s&mobileids=消息编号&content=%s';
        $url = 'http://api.sms.cn/mt/?uid=kq126';
        $url .= '&pwd=7c5e02f32f2ae99a3087cea34ab4058c';
        $url .= '&mobile=' . $mob;
        $url .= '&encode=utf8&content=' . urlencode($str);

        return file_get_contents($url);
    }


}