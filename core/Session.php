<?php
//declare(strict_types=1);

namespace esp\core;

use esp\core\ext\SessionRedis;


/**
 * Class Session
 * @package plugins\ext
 *
 *
 * 若某页面原则上是不会改变任何session，为保险起见，可在页面任何地方加：session_abort();用于丢弃当前进程所有对session的改动；
 *
 * 本类只是改变PHP存取session的介质，在使用方面没有影响，如：$_SESSION['v']=123，$v=$_SESSION['v']；
 *
 * 本插件实现用redis保存session，且每个session的生存期从其自身被定义时计算起，而非PHP本身统一设置
 * 有一个问题须注意：$_SESSION['name']=abc；之后若再次给$_SESSION['name']赋其他不同的值，则其生存期以第二次赋值起算起
 * 但是，若第二次赋值与之前的值相同，并不会改变其生存期
 *
 *
 * 如果只是想存到redis也可以直接设置，或修改php.ini
 * ini_set('session.save_handler', 'redis');
 * ini_set('session.save_path', 'tcp://127.0.0.1:6379');
 * ini_set('session.save_path', '/tmp/redis.sock?database=0');
 *
 * php.ini中默认保存到PHP，也就是服务器某个目录中，
 * 比如默认：session.save_path = "/tmp"
 * 则在没有指定其他介质的情况下，在/tmp中所有[sess_****]文件即为session内容
 *
 * 如果指定redis作为介质，则用下列方法可查看session内容
 * [root@localhost ~]# redis-cli
 * 127.0.0.1:6379> ping
 * PONG
 *
 * 列出所有键：
 * 127.0.0.1:6379> keys PHPREDIS*
 * 1) "PHPREDIS_SESSION :57105pkee2ov7b49il470ctv51"
 *
 * 显示内容：
 * 127.0.0.1:6379> get PHPREDIS_SESSION :57105pkee2ov7b49il470ctv51
 * "val|s:19:\"2017-09-16 14:47:45\";"
 *
 *
 */
final class Session
{
    private $SessionHandler;
    private $debug;
    private $run = true;

    public function __construct(array $session, Debug $debug)
    {
        $config = $session['default'];
        if (isset($session[_MODULE])) $config = $session[_MODULE] + $config;
        if (!isset($config['run']) or !$config['run']) {
            if (!is_null($debug)) $debug->relay($config);
            $this->run = 'not run in ' . _MODULE;
            return;
        }
        $this->debug = $debug;

        $config += [
            'key' => 'PHPSESSID',
            'host' => '127.0.0.1',
            'port' => 6379,
            'db' => 0,
            'delay' => 0,
            'prefix' => '',
            'password' => '',
            'httponly' => 1,
            'expire' => 86400,
            'ttl' => 86400
        ];

        $this->SessionHandler = new SessionRedis(boolval($config['delay']), $config['prefix']);
        session_set_save_handler($this->SessionHandler, !_DEBUG);

        $option = [];
        $option['save_path'] = serialize(['host' => $config['host'], 'port' => $config['port'], 'db' => $config['db'], 'password' => $config['password']]);
        $option['cache_expire'] = intval($config['expire']);//session内容生命期
        $option['serialize_handler'] = 'php_serialize';//用PHP序列化存储数据

        $option['use_trans_sid'] = 0;//指定是否启用透明 SID 支持。默认为 0（禁用）。
        $option['use_only_cookies'] = 1;//指定是否在客户端仅仅使用 cookie 来存放会话 ID。。启用此设定可以防止有关通过 URL 传递会话 ID 的攻击
        $option['use_cookies'] = 1;//指定是否在客户端用 cookie 来存放会话 ID

        $option['name'] = $config['key'];//指定会话名以用做 cookie 的名字。只能由字母数字组成，默认为 PHPSESSID
        $option['cookie_lifetime'] = intval($config['ttl']);//以秒数指定了发送到浏览器的 cookie 的生命周期。值为 0 表示"直到关闭浏览器"。
        $option['cookie_path'] = '/';//指定了要设定会话 cookie 的路径。默认为 /。
        $option['cookie_secure'] = _HTTPS;//指定是否仅通过安全连接发送 cookie。默认为 off。如果启用了https则要启用
        $option['cookie_httponly'] = ($config['httponly'] === 1);//只能PHP读取，JS禁止
        $option['cookie_domain'] = (isset($config['domain'])) ? getenv('HTTP_HOST') : _HOST;

        //允许从URL或POST中读取session值
        if ($option['use_trans_sid']) {
            $ptn = "/^{$config['prefix']}[\w\-]{22,32}$/";
            if ((isset($_GET[$option['name']]) and preg_match($ptn, $_GET[$option['name']]))
                or
                (isset($_POST[$option['name']]) and preg_match($ptn, $_POST[$option['name']]))
            ) {
                session_id($_GET[$option['name']]);
            }
        }

        session_start($option);

        if (!is_null($debug)) {
            $debug->relay($config);
            $debug->relay($option);
        }
    }

    /**
     * 设置或读取过期时间
     * @param int $ttl
     * @return int|bool
     */
    public function ttl(int $ttl = null)
    {
        return $this->SessionHandler->ttl($ttl);
    }

    /**
     * 换新的sessionID
     * @param bool $createNew 换新ID后，原数据清空，一般都要清空，否则会导至数据库暴增
     * @return string
     */
    public function id(bool $createNew = false): string
    {
        if ($createNew) session_regenerate_id(true);
        return session_id();
    }

    /**
     * @return SessionRedis
     */
    public function Handler(): SessionRedis
    {
        return $this->SessionHandler;
    }

    /**
     * 设置某值，同时重新设置有效时间
     * @param $key
     * @param null $value
     * @return bool
     * @throws \Exception
     */
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_SESSION[$k] = $v;
            }
        } else {
            $_SESSION[$key] = $value;
        }
        return $this->SessionHandler->update(true);
    }

    /**
     * @param null $key
     * @param null $autoValue
     * @return bool|float|int|mixed|null|string
     * @throws \Exception
     */
    public function get($key = null, $autoValue = null)
    {
        if (is_null($this->SessionHandler)) {
            throw new \Exception("系统未开启Session", 500);
        }
        if ($key === null) return $_SESSION;
        if (empty($_SESSION)) return null;
        $value = $_SESSION[$key] ?? $autoValue;
        if (is_int($autoValue)) $value = intval($value);
        else if (is_bool($autoValue)) $value = boolval($value);
        else if (is_array($autoValue)) $value = json_decode($value, true);
        else if (is_string($autoValue)) $value = strval($value);
        else if (is_float($autoValue)) $value = floatval($value);
        return $value;
    }

    /**
     * @param string ...$keys
     */
    public function del(string ...$keys)
    {
        foreach ($keys as $key) $_SESSION[$key] = null;
        $this->SessionHandler->update(true);
    }


    public function data(string $key, $val = null)
    {
        if ($this->run !== true) {
            throw new \Exception($this->run, 500);
        }
        if (is_null($val)) {
            $value = $_SESSION[$key] ?? '';
            if (empty($value) or !is_array($value)) return null;
            if ($value['cid'] !== Client::id()) return null;
            return $value['val'] ?? null;

        } else if ($val === false) {
            $_SESSION[$key] = null;

        } else if (is_array($val)) {
            $_SESSION[$key] = $val;

        } else {
            $value = [];
            $value['val'] = $val;
            $value['cid'] = Client::id();
            $value['time'] = time();
            $_SESSION[$key] = $value;
        }
        return $this->SessionHandler->update(true);
    }

    /**
     * 清空session
     */
    public function empty()
    {
        $_SESSION = null;
        $this->SessionHandler->update(true);
        session_destroy();
    }

    /**
     * 撤销本次请求对session的改动
     * @return bool
     */
    public static function reset()
    {
        return session_abort();
    }

    /**
     * 结束session
     */
    public static function destroy()
    {
        return session_destroy();
    }

}

