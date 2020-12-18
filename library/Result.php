<?php
declare(strict_types=1);

namespace esp\library;

class Result
{
    private $_success = true;
    private $_error = 0;
    private $_message = 'ok';
    private $_data = [];
    private $_page = null;
    private $_append = [];

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
    public function error(int $value = 1): Result
    {
        $this->_error = $value;
        return $this;
    }

    public function message($msg = 'ok'): Result
    {
        if (is_array($msg)) $msg = json_encode($msg, 256 | 64);
        else if (is_object($msg)) $msg = var_export($msg, true);
        $this->_message = strval($msg);
        return $this;
    }

    public function data($key, $value = null): Result
    {
        if (is_string($key) and !is_null($value)) {
            $this->_data[$key] = $value;
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

    public function page(array $value): Result
    {
        $this->_page = $value;
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
        $value = [
            'success' => $this->_success,
            'error' => $this->_error,
            'message' => $this->_message,
            'data' => $this->_data,
        ];
        if (!is_null($this->_page)) $value['page'] = $this->_page;
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