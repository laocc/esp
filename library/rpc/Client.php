<?php

namespace library\rpc;


use esp\core\ext\EspError;

class Client
{
    use Sign;

    private $_task = [];
    private $_encode = 'php';
    private $_async = false;
    private $_timeout = 10;
    private $_agent;
    private $_api;
    private $_port = -1;

    public function __set(string $name, $value)
    {
        if (in_array($name, ['api', 'port', 'encode', 'agent', 'async', 'timeout'])) {
            $this->${"_{$name}"} = $value;
        } else {
            $this->_task[$name] = $value;
        }
    }

    public function api(string $api)
    {
        $this->_api = $api;
    }

    public function encode(string $encode)
    {
        $this->_encode = $encode;
        return $this;
    }

    public function async(bool $bool)
    {
        $this->_async = $bool;
        return $this;
    }

    public function send(callable $callback = null)
    {
        if (empty($this->_task)) throw new EspError('当前队列任务为空');
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('could not fork');
        } else if ($pid) {
            pcntl_wait($status);
        } else {
            $timeout = intval($this->_timeout ?: 1);
            $port = intval($this->_port);

            //短连接
            $fp = fsockopen($this->_api, $port, $err_no, $err_str, $timeout);
            $_data = http_build_query($item['data']);
            $pLen = strlen($_data);

            $header = "POST {$item['uri']} {$item['version']}\r\n";
            $header .= "Host:{$item['host']}\r\n";
            $header .= "Content-type:application/x-www-form-urlencoded\r\n";
            $header .= "User-Agent:{$this->_agent}\r\n";
            $header .= "Content-length:{$pLen}\r\n";
            $header .= "Connection:Close\r\n\r\n{$_data}";

            echo strlen($header) / 1024 / 1024, "\n";

            $gLen = fwrite($fp, $header);
            if ($gLen === false) {
                $error = error_get_last();
                $callback(new \Error('数据发送失败', 1, $error));
            } elseif ($gLen !== $pLen) {
                $error = error_get_last();
                $callback(new \Error('数据发送失败，与实际需发送数据长度相差=' . ($gLen - $pLen), 2, $error));
                error_clear_last();

            } else {
                //接收数据
                $value = $tmpValue = '';
                $len = null;
                while (!feof($fp)) {
                    $line = fgets($fp);

                    if ($line == "\r\n" and is_null($len)) {
                        $len = 0;//已过信息头区
                    } elseif ($len === 0) {
                        $len = hexdec($line);//下一行的长度
                    } elseif (is_int($len)) {
                        $tmpValue .= $line;//中转数据，防止收到的一行不是一个完整包
                        if (strlen($tmpValue) >= $len) {
                            $value .= substr($tmpValue, 0, $len);
                            $tmpValue = '';
                            $len = 0;//收包后归0
                        }
                    }
                }
                //接收结束

                $data = $this->data_decode($value);
                if (is_array($data) and isset($data['_message'])) {
                    $callback($index, new \Error($data['_message'], $data['_type']));

                } elseif ($this->sign & self::SIGN_S_C) {//要对返回数据签名验证
                    if (!is_array($data)) {
                        $callback($index, new \Error("返回数据异常\n{$data}", -1));

                    } elseif (!Sign::check($this->_form_key['sign'], $this->token, $item['host'], $data)) {
                        $callback($index, new \Error('服务端返回数据TOKEN验证失败', 1001));

                    } else {
                        $callback($index, (isset($data['_value_']) ? $data['_value_'] : $data));
                    }
                } else {
                    $callback($index, (isset($data['_value_']) ? $data['_value_'] : $data));
                }

            }
            fclose($fp);
        }
        foreach ($this->_task as $action => $item) {
            $pid = ($index === 0 or !$this->fork) ? 0 : pcntl_fork();

            if ($pid == -1) {
                die('could not fork');
            } else if ($pid) {
                pcntl_wait($status);
            } else {
                $callback = $item['callback'] ?: $callback;
                if (is_null($callback) and !$item['async']) throw new EspError('同步请求，必须提供处理返回数据的回调函数');

                //短连接
                $fp = fsockopen($item['host'], $item['port'], $err_no, $err_str, $timeout);

                //长连接
//                $fp = pfsockopen($item['host'], $item['port'], $err_no, $err_str, $timeout);
//                stream_set_blocking($fp, 0);//1阻塞;0非阻塞

                if (!$fp) {//连接失败
                    if (!is_null($callback)) {
                        $callback($index, new EspError($err_str, $err_no));
                    } else {//异步时，直接抛错
                        throw new EspError($err_str, $err_no);
                    }
                } else {
                    $_data = http_build_query($item['data']);
                    $len = strlen($_data);

                    $data = "POST {$item['uri']} {$item['version']}\r\n";
                    $data .= "Host:{$item['host']}\r\n";
                    $data .= "Content-type:application/x-www-form-urlencoded\r\n";
                    $data .= "User-Agent:{$item['agent']}\r\n";
                    $data .= "Content-length:{$len}\r\n";
                    $data .= "Connection:Close\r\n\r\n{$_data}";

                    echo strlen($data) / 1024 / 1024, "\n";

                    $win = fwrite($fp, $data);
                    print_r(['fa' => $win, 'len' => $len, 'all' => strlen($data)]);

                    if (0 and $win !== $len) {
                        if (!is_null($callback)) {
                            $error = error_get_last();
                            if ($win === false) {
                                $callback($index, new \Error('数据发送失败', 1, $error));
                            } else {
                                $callback($index, new \Error('数据发送失败，与实际需发送数据长度相差=' . ($win - $len), 2, $error));
                            }
                            error_clear_last();
                        }
                        fclose($fp);
                        continue;
                    }

                    if ($item['async']) {//异步，直接返index，不带数据
                        if (!is_null($callback)) {
                            $callback($index, null);
                        }
                    } else {


                    }
                    fclose($fp);
                }
            }
        }
        return true;
    }

    /**
     * @param $url
     * @param $action
     * @param $data
     * @param $async
     * @return array
     * @throws \Exception
     */
    private function realUrl(string $url, string $action, $data, $async)
    {
        if (!\esp\helper\is_url($url)) throw new EspError("请求调用地址不是一个合法的URL");

        $_data = [
            $this->_form_key['action'] => $action,
            $this->_form_key['data'] => $this->data_encode($data)
        ];
        if ($this->type) $_data[$this->_form_key['type']] = $this->type;//编码格式

        $info = parse_url($url);
        $port = intval($info['port'] ?? 0);
        if (strtolower($info['scheme'] ?? '') === 'https') {
            $version = 'HTTP/1.1';
            if (!$port) $port = 80;
        } else {
            $version = 'HTTP/2.0';
            if (!$port) $port = 443;
        }

        if ($this->sign & self::SIGN_C_S) {
            $_data = Sign::create($this->_form_key['sign'], $this->token, $info[2], $_data);
        }

        return [
            'version' => $version,
            'host' => $info['host'],
            'port' => $port,
            'uri' => $info['path'],
            'url' => $url,
            'agent' => ($this->agent ?: getenv('HTTP_USER_AGENT')),
            'data' => $_data,
            'async' => $async,
        ];
    }


    private function data_encode($val)
    {
        if (strtolower($this->_type) === 'json') {
            return is_array($val) ? json_encode($val, 256) : $val;
        } else {
            return serialize($val);
        }
    }

    private function data_decode($val)
    {
        if (strtolower($this->_type) === 'json') {
            $arr = json_decode($val, true);
            return is_array($arr) ? $arr : $val;
        } else {
            return @unserialize($val) ?: $val;
        }
    }


    /**
     * 清空一个或全部
     * @param null $index
     */
    public function flush($index = null)
    {
        if (is_null($index)) {
            $this->_task = [];
            $this->_index = 0;
        } elseif (is_array($index)) {
            array_map('self::flush', $index);
        } else {
            unset($this->_task[$index]);
        }
    }

}