<?php
declare(strict_types=1);

namespace esp\library;

class Result
{
    private $_success = true;
    private $_error = 0;
    private $_message = 'ok';
    private $_data = [];
    private $_paging = null;
    private $_append = [];

    /**
     * 魔术方法获取变量值
     * @param string $key
     * @return |null
     */
    public function __get(string $key)
    {
        $key = "_{$key}";
        if (isset($this->{$key})) return $this->{$key};
        return null;
    }

    /**
     * @param bool $value 可以是bool或int
     * @return $this
     */
    public function success($value = true): Result
    {
        $this->_success = $value;
        return $this;
    }

    /**
     * @param int $value 错误代码
     * @return $this
     */
    public function error(int $value = -1): Result
    {
        if ($value === -1 && $this->_error === 0) $this->_error = 1;
        else $this->_error = $value;
        return $this;
    }

    /**
     * @param string $msg
     * @param bool $append
     * @return $this
     */
    public function message($msg = 'ok', bool $append = false): Result
    {
        if (is_array($msg)) $msg = json_encode($msg, 256 | 64);
        else if (is_object($msg)) $msg = var_export($msg, true);
        if ($append) {
            $this->_message .= strval($msg);
        } else {
            $this->_message = strval($msg);
        }
        return $this;
    }

    /**
     * @param $key
     * @param null $value
     * @return $this
     */
    public function data($key, $value = 'nullValue'): Result
    {
        if (is_string($key) and $value !== 'nullValue') {
            if (strpos($key, '.') > 0) {
                $obj = &$this->_data;
                foreach (explode('.', $key) as $k) {
                    if (!isset($obj[$k])) $obj[$k] = [];
                    $obj = &$obj[$k];
                }
                $obj = $value;
                return $this;
            }
            $this->_data[$key] = $value;
        } else if (is_array($key)) {
            $this->_data = array_merge($this->_data, $key);
        } else {
            $this->_data = $key;

        }
        return $this;
    }

    public function append(string $key, $value): Result
    {
        $this->_append[$key] = $value;
        return $this;
    }

    public function paging(array $value): Result
    {
        $this->_paging = $value;
        return $this;
    }

    public function __debugInfo()
    {
        return $this->display();
    }

    public function __toString(): string
    {
        return json_encode($this->display(), 256 | 64);
    }

    public function display($return = null): array
    {
        if ($return instanceof Result) return $return->display();

        $value = [
            'success' => $this->_success,
            'error' => $this->_error,
            'message' => $this->_message,
            'data' => $this->_data,
        ];
        if (!is_null($this->_paging)) $value['paging'] = $this->_paging;
        if (!empty($this->_append)) $value += $this->_append;

        if (is_string($return)) {
            $value['message'] = $return;
            if ($value['error'] === 0) $value['error'] = 1;

        } else if (is_int($return)) {
            $value['error'] = $return;
            if ($value['message'] === 'ok') $value['message'] = "Error.{$return}";

        } else if (is_array($return)) {
            $value['data'] = $return;

        } else if (is_float($return)) {
            $value['message'] = strval($return);

        } else if ($return === true) {
            if ($value['message'] === 'ok') $value['message'] = 'True';
            $value['error'] = 0;

        } else if ($return === false) {
            if ($value['message'] === 'ok') $value['message'] = 'False';
            if ($value['error'] === 0) $value['error'] = 1;

        }

        return $value;
    }

}