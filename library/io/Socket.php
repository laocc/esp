<?php
namespace io;

class Socket
{
    private $socket;

    /**
     * Socket constructor.
     * @param $ip
     * @param $port
     * @param bool $service
     * @throws \Exception
     *
     * socket_clear_error($this->socket);
     */
    public function __construct($ip, $port, $service = false)
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            error('创建SOCKET：' . socket_strerror($this->socket));
        }
        if ($service) {//创建服务器
            set_time_limit(0);

            if (PHP_SAPI !== 'cli') {
                echo "Socket Service 只能运行在CLI环境下\n";
                exit;
            }
            if (($ret = socket_bind($this->socket, $ip, $port)) < 0) {
                error('SOCKET绑定IP：' . socket_strerror($this->socket));
            }
            if (($ret = socket_listen($this->socket, 4)) < 0) {
                error('SOCKET监听端口：' . socket_strerror($this->socket));
            }
            $this->listen();

        } else {//创建客户端
            $result = socket_connect($this->socket, $ip, $port);
            if ($result < 0) {
                error('连接SOCKET：' . socket_strerror($result));
            }
        }
    }

    /**
     * 监听客户端发来的数据
     */
    private function listen()
    {
        do {
            if (($client = socket_accept($this->socket)) < 0) {
                echo "受理客户端失败：\t" . socket_strerror($client) . "\n";
                break;
            } else {

                //接收到数据
                $buf = socket_read($client, 8192);

                //处理并得到返回数据
                $response = $this->response($buf);

                //写入通道返回
                socket_write($client, $response, strlen($response));

            }
            socket_close($client);
        } while (true);
    }

    private function response($buf)
    {
        echo "收到：\t {$buf}\n";

        $val = json_decode($buf, true);
        if (!is_array($val)) return '送入数据只能是数组格式';

        /**
         * 这里加入业务逻辑
         */

        return "收到了：{$buf}";
    }


    /**
     * 向服务器端发送数据
     * @param $data
     * @return array
     */
    public function send($data)
    {
        if (is_array($data)) {
            $data = json_encode($data, 256);
        }

        //发送数据
        $value = [];
        if (!socket_write($this->socket, $data, strlen($data))) {
            $value['success'] = false;
            $value['error'] = socket_strerror($this->socket);
            return $value;
        }
        //同步等待数据
        while ($get = socket_read($this->socket, 8192)) {
            $value['success'] = true;
            $value['error'] = null;
            $value['value'] = $get;
        }
        return $value;
    }


    /**
     * 析构时消毁
     */
    public function __destruct()
    {
        socket_close($this->socket);
    }

}