<?php

namespace library\rpc;


class Server
{
    use Sign;

    /**
     * 服务器端侦听请求
     */
    public function listen()
    {
        parse_str(file_get_contents("php://input"), $post);
        empty($post) and exit();

        if (!$this->sign_check($post)) {

        }

        foreach ($post as $i => $action) {

            ob_start();

            $action = isset($post[$this->_form_key['action']]) ? $post[$this->_form_key['action']] : null;
            $data = isset($post[$this->_form_key['data']]) ? $post[$this->_form_key['data']] : null;
            $type = isset($post[$this->_form_key['type']]) ? $post[$this->_form_key['type']] : 'php';

            if (!$this->check_agent()) $this->return_error($type, 1001, '客户端认证失败');
            if (empty($data) or is_null($action)) $this->return_error($type, 1010, '无数据传入');

            if (($this->sign & self::SIGN_C_S) and !Sign::check($this->_form_key['sign'], $this->token, getenv('HTTP_HOST'), $post))
                $this->return_error($type, 1002, '服务端TOKEN验证失败');

            if (strpos($action, '_') === 0) $this->return_error($type, 1030, '禁止调用系统方法');

            $action .= $this->action;
            if (in_array($action, $this->_shield)) $this->return_error($type, 1032, "当前服务端{$action}方法不可用");

            if (!method_exists($this->_server, $action) or !is_callable([$this->_server, $action])) {
                $this->return_error($type, 1033, "当前服务端不存在{$action}方法");
            }

            $data = $this->data_decode($type, $data);
            if (!is_array($data)) $data = [$data];

            $v = $this->_server->{$action}(...$data + array_fill(0, 10, null));
            if (!empty($error = error_get_last())) {
                $msg = json_encode($error, 256);
                error_clear_last();
                $this->return_error($type, $error['type'], $msg);
            }
            if (is_null($v)) {
                $this->return_data($type, ob_get_contents(), true);
            }
            $this->return_data($type, $v);


        }

    }

    private function return_error($type, $code, $value)
    {
        $value = ['_type' => 0 - $code, '_message' => $value];
        ob_end_clean();
        echo $this->data_encode($type, $value);
        ob_flush();
        exit;
    }


    private function return_data($type, $value, $fromEcho = false)
    {
        if ($fromEcho and is_string($value)) {
            $array = json_decode($value, true);
            if (is_array($value)) $value = $array;
        }
        if (!is_array($value)) $value = ['_value_' => $value];
        if ($this->sign & self::SIGN_S_C)
            $value = Sign::create($this->_form_key['sign'], $this->token, getenv('HTTP_HOST'), $value);

        ob_end_clean();
        echo $this->data_encode($type, $value);
        ob_flush();
        exit;
    }


    private function check_agent()
    {
        $ip = getenv('REMOTE_ADDR');
        $agent = getenv('HTTP_USER_AGENT');
        if (!$agent) return false;
        if (!$this->agent) return true;
        return $this->agent === $agent;
    }

    public function shield($action)
    {
        if (is_string($action)) $action = explode(',', $action);
        if (is_array($action)) $this->_shield = $action;
    }

    public function bind($ip)
    {
        if (is_string($ip)) $ip = explode(',', $ip);
        if (is_array($ip)) $this->_bind = $ip;
    }

    private function data_encode($type, $val)
    {
        if (strtolower($type) === 'json') {
            return is_array($val) ? json_encode($val, 256) : $val;
        } else {
            return serialize($val);
        }
    }

    private function data_decode($type, $val)
    {
        if (strtolower($type) === 'json') {
            $arr = json_decode($val, true);
            return is_array($arr) ? $arr : $val;
        } else {
            return unserialize($val);
        }
    }


    public function set($name, $value = null)
    {
        if (is_array($name)) {
            $this->_option = $name + $this->_option;
        } else {
            $this->_option[$name] = $value;
        }
    }

    public function get($name)
    {
        return isset($this->_option[$name]) ? $this->_option[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->_option[$name] = $value;
    }

    public function __get($name)
    {
        return isset($this->_option[$name]) ? $this->_option[$name] : null;
    }

}