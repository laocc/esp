<?php

namespace esp\library\request;

use esp\error\EspError;

abstract class Request
{
    protected $_isPost = false;
    protected $_data = array();
    protected $_raw = '';
    protected $_error = [];
    protected $_off = false;
    protected $_min;
    protected $_max;


    /**
     * 数字类：表示最大最小值
     * 字串类：表示为最短最长
     * @param $value
     * @return $this
     */
    public function min($value)
    {
        $this->_min = $value;
        return $this;
    }

    public function max($value)
    {
        $this->_max = $value;
        return $this;
    }

    /**
     * 受理post时的原始数据，也就是file_get_contents('php://input')
     * @return string
     */
    public function raw()
    {
        return $this->_raw;
    }

    /**
     * @param $number
     * @param int $type
     * @return string
     * $type=1 时间
     * $type=2 金额
     */
    protected function errorNumber($number, int $type = 0): string
    {
        $min = $this->_min;
        $max = $this->_max;
        if ($type === 1) {
            $min = strtotime($min);
            $max = strtotime($max);
        } else if ($type === 2) {
            $min = intval($min * 100);
            $max = intval($max * 100);
        }

        if (!is_null($this->_min) && $min > $number) {
            $this->_min = null;
            $this->_max = null;
            return "不能小于最小值({$this->_min})";
        }
        if (!is_null($this->_max) && $max < $number) {
            $this->_min = null;
            $this->_max = null;
            return "不能大于最大值({$this->_max})";
        }
        $this->_min = null;
        $this->_max = null;
        return '';
    }

    protected function errorString($string): string
    {
        $len = mb_strlen($string);
        if (!is_null($this->_min) && $this->_min > $len) {
            $this->_min = null;
            $this->_max = null;
            return "不能少于({$this->_min})个字";
        }
        if (!is_null($this->_max) && $this->_max < $len) {
            $this->_min = null;
            $this->_max = null;
            return "不能多于({$this->_max})个字";
        }
        $this->_min = null;
        $this->_max = null;
        return '';
    }


    public function data()
    {
        return $this->_data;
    }

    /**
     * 传入数据签名校验
     *
     * 只能满足常见签名方法 https://pay.weixin.qq.com/wiki/doc/api/H5.php?chapter=4_3
     *
     * @param array $param
     * @return bool|string
     */
    public function signCheck(array $param = [])
    {
        $sKey = $param['sign_key'] ?? 'sign';
        $tKey = $param['token_key'] ?? 'key';
        $token = $param['token'] ?? '';
        if (isset($param['sign_data'])) {
            $data = $param['sign_data'];
        } else {
            $data = $this->_data;
        }
        $sign = $data[$sKey] ?? '';
        unset($data[$sKey]);
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            if ($v === '') continue;
            if (is_array($v)) $v = json_encode($v, 256 | 64);
            $str .= "{$k}={$v}&";
        }
        $md5 = md5("{$str}{$tKey}={$token}");
        if ($sign === 'create') return $md5;

        return hash_equals(strtoupper($sign), strtoupper($md5));
    }

    protected function getData(string &$key, &$force)
    {
        if ($this->_off && $this->_isPost) throw new EspError('POST已被注销，不能再次引用，请在调用error()之前读取所有数据。', 2);

        if (empty($key)) throw new EspError('参数必须明确指定', 2);

        $force = true;
        if ($key[0] === '?') {
            $force = false;
            $key = substr($key, 1);
        }

        $keyName = $key;
        $param = $key;
        $default = null;
        $f = strpos($key, ':');
        $d = strpos($key, '=');
        if ($f && $d === false) {
            $ka = explode(':', $key);
            $param = $ka[0];
            $keyName = $ka[1];
        } else if ($d && $f === false) {
            $ka = explode('=', $key);
            $param = $ka[0];
            $keyName = $ka[0];
            $default = $ka[1];
        } else if ($d && $f) {
            $ka = explode(':', $key);
            if ($d > $f) {//分号在前： 键名:参数名=默认值
                $param = $ka[0];
                $den = explode('=', $ka[1]);
                $keyName = $den[0];
                $default = $den[1];
            } else {
                //分号在后： 键名=默认值:参数名
                $keyName = $ka[1];
                $den = explode('=', $ka[0]);
                $param = $den[0];
                $default = $den[1];
            }
        }

        if (strpos($param, '.') > 0) {
            $val = $this->_data;
            foreach (explode('.', $param) as $k) {
                $val = $val[$k] ?? $default;
                if (is_null($val) or $default === $val) break;
            }
        } else {
            $val = $this->_data[$param] ?? $default;
        }

        if (is_null($val)) {
            //只要是null值，后面会直接退出
            $this->_min = null;
            $this->_max = null;
        }

        $key = $keyName;
        if (is_null($val) && $force) $this->recodeError($keyName);

        return $val;
    }

    protected function recodeError(string $key, string $message = '值不能为空')
    {
        $this->_error[] = "{$key}-{$message}";
    }


    public function __debugInfo()
    {
        return [
            'data' => $this->_data,
            'error' => $this->_error,
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ];
    }

    public function __toString(): string
    {
        return json_encode($this->_data, 256 | 64);
    }


}