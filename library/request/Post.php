<?php
declare(strict_types=1);

namespace esp\library\request;

use esp\core\ext\EspError;
use function esp\helper\isFloat;
use function esp\helper\isInteger;
use esp\library\ext\Xss;
use function esp\helper\is_card;
use function esp\helper\is_date;
use function esp\helper\is_domain;
use function esp\helper\is_ip;
use function esp\helper\is_mail;
use function esp\helper\is_time;
use function esp\helper\is_url;
use function esp\helper\xml_encode;
use function esp\helper\xml_decode;

final class Post extends Request
{


    public function string(string $key, int $xssLevel = 1): string
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';

        if (is_array($value)) $value = json_encode($value, 256 | 64);
        $value = trim(strval($value));

        if ($xssLevel === 1) {
            $value = preg_replace('/["\']/', '', $value);

        } elseif ($xssLevel === 2) {
            $value = preg_replace('/[\"\'\%\&\^\$\#\(\)\[\]\{\}\?]/', '', $value);

        } else if ($xssLevel > 2) {
            Xss::clear($value);

        }

        if (empty($value) && $force) $this->recodeError($key);
        if ($chk = $this->errorString($value)) $this->recodeError($key . $chk);

        return $value;
    }

    /**
     * 按规则检查，若不为空则必须要符合规则
     * @param string $key
     * @param string $type
     * @return string
     * @throws EspError
     */
    public function filter(string $key, string $type): string
    {
        $this->_min = null;
        $this->_max = null;
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';
        $value = trim($value);

        if ($value === '' && !$force) return '';

        switch ($type) {
            case 'cn':
                if (!preg_match('/^[\x{4e00}-\x{9fa5}]{$n}$/u', $value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为全中文");
                    return '';
                }
                break;
            case 'en':
                if (!preg_match('/^[a-zA-Z]+$/', $value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为全英文字母");
                    return '';
                }
                break;
            case 'number':
                if (!preg_match('/^\d+$/', $value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须纯数字");
                    return '';
                }
                break;
            case 'decimal':
                if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须数字或小数");
                    return '';
                }
                break;
            case 'alphanumeric':
                if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为全英文或数字");
                    return '';
                }
                break;
            case 'mobile':
                if (!preg_match('/^1\d{10}$/', $value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为手机号码格式");
                    return '';
                }
                break;
            case 'card':
                if (!is_card($value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为符合规则的身份证号码");
                    return '';
                }
                break;
            case 'url':
                if (!is_url($value) && !is_domain($value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为URL格式");
                    return '';
                }
                break;
            case 'mail':
            case 'email':
                if (!is_mail($value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为电子邮箱地址格式");
                    return '';
                }
                break;
            case 'ip':
                if (!is_ip($value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为IP4格式");
                    return '';
                }
                break;
            case 'date':
                if (!is_date($value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为日期格式");
                    return '';
                }
                break;
            case 'time':
                if (!is_time($value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为时间格式");
                    return '';
                }
                break;
            case 'datetime':
                if (strtotime($value) < 1) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值必须为日期时间格式");
                    return '';
                }
                break;
            default:

                if (\esp\helper\is_match($type) and !preg_match($type, $value)) {
                    if ($force or !empty($value)) $this->recodeError($key, "{$key}-值不是指定格式的数据");
                    return '';
                }

        }

        if (empty($value) && $force) $this->recodeError($key);

        return $value;
    }

    public function date(string $key): int
    {
        return $this->datetime($key);
    }

    public function time(string $key): int
    {
        return $this->datetime($key);
    }

    public function datetime(string $key): int
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return 0;
        $value = str_replace(['+', '%3A'], [' ', ':'], $value);
        if (empty($value) && $force) $this->recodeError($key);

        $value = strtotime($value);
        if ($chk = $this->errorNumber($value, 1)) $this->recodeError($key . $chk);
        return $value;
    }

    public function int(string $key, bool $ceil = false): int
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return 0;
        if (is_array($value)) $value = array_sum($value);

        if ($value === '' && $force) $this->recodeError($key);
        if ($ceil) $value = (int)ceil($value);
        $value = intval($value);
        if ($chk = $this->errorNumber($value)) $this->recodeError($key . $chk);
        return $value;
    }

    public function float(string $key): float
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return floatval(0);
        if ($value === '' && $force) $this->recodeError($key);
        $value = floatval($value);
        if ($chk = $this->errorNumber($value)) $this->recodeError($key . $chk);
        return $value;
    }

    public function bool(string $key): bool
    {
        $this->_min = null;
        $this->_max = null;
        $value = $this->getData($key, $force);
        if (is_null($value)) return false;
        if ($value === '' && $force) $this->recodeError($key);
        if (strtolower($value) === 'false') return false;
        return boolval($value);
    }

    /**
     * 返回的是[金额分]，若需要[金额元]级，请用float
     * @param string $key
     * @param bool $cent
     * @return int
     * @throws EspError
     */
    public function money(string $key, bool $cent = true): int
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return 0;
        if ($value === '' && $force) $this->recodeError($key);
        $value = intval(floatval($value) * 100);
        if ($chk = $this->errorNumber($value, 2)) $this->recodeError($key . $chk);
        return $value;
    }

    public function match(string $key, string $pnt): string
    {
        $this->_min = null;
        $this->_max = null;
        if (!\esp\helper\is_match($pnt)) throw new EspError('传入的表达式不合法', 1);
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';
        if (empty($value) && $force) $this->recodeError($key);

        if (!preg_match($pnt, $value)) {
            if ($force) $this->recodeError($key, "{$key}-值不是指定格式的数据");
            return '';
        }
        return strval($value);
    }


    /**
     * 获取json格式值，若收到数组，转换为json
     *
     * @param string $key
     * @param int $options
     * @return string
     * @throws EspError
     */
    public function json(string $key, int $options = 256 | 64): string
    {
        $this->_min = null;
        $this->_max = null;

        $value = $this->getData($key, $force);
        if (is_null($value)) return '';

        if (is_array($value)) $value = json_encode($value, $options);

        if (empty($value) && $force) $this->recodeError($key);

        if (!preg_match('/^[\{\[].+[\]\}]$/', $value)) {
            if ($force) $this->recodeError($key, "{$key}-值不是有效的JSON格式");
            return '';
        }

        return $value;
    }


    /**
     * 获取xml，如果收到的是数组，转换为xml
     * @param string $key
     * @param string $root
     * @return string
     * @throws EspError
     */
    public function xml(string $key, string $root = 'xml'): string
    {
        $this->_min = null;
        $this->_max = null;

        $value = $this->getData($key, $force);
        if (is_null($value)) return '';

        if (is_array($value)) $value = xml_encode($root, $value, false);
        if (empty($value) && $force) $this->recodeError($key);

        if (!preg_match('/^<\w+>.+<\/\w+>$/', $value)) {
            if ($force) $this->recodeError($key, "{$key}-值不是有效的XML格式");
            return '';
        }

        return $value;
    }


    /**
     * 获取数组，若收到的是json或xml，则转换
     *
     * @param string $key
     * @param string $encode
     * @return array
     * @throws EspError
     */
    public function array(string $key, string $encode = 'json'): array
    {
        $this->_min = null;
        $this->_max = null;

        $value = $this->getData($key, $force);
        if (is_null($value)) return [];

        if (is_string($value)) {
            if ($encode === 'xml') {
                $value = xml_decode($value, true);

            } else if ($encode === 'json') {
                $value = json_decode($value, true);

            } else if ($encode === "ini") {
                $value = parse_ini_string($value, true);

            } else {
                parse_str($value, $value);
            }
        }

        if (!is_array($value) or empty($value)) {
            if ($force) $this->recodeError($key, "{$key}-值无法转换为数组或数组为空");
            return [];
        }

        return $value;
    }

    /**
     * @param int $option
     * @return false|mixed|string|null
     *
     * $option:
     * 1：仅显示第一条错误，否则显示全部
     * 2：转为json
     * 4：按行显示
     * 8：加<br>显示
     */
    public function error(int $option = 1)
    {
        $this->_off = true;
        if (empty($this->_error)) return null;
        if (count($this->_error) === 1) return $this->_error[0];
        if ($option & 1) return $this->_error[0];
        if ($option & 2) return json_encode($this->_error, 256 | 64);
        if ($option & 4) return implode("\r\n", $this->_error);
        if ($option & 8) return implode("<br>", $this->_error);
        return $this->_error;
    }

    public function __construct(string $type = null)
    {
        $this->_isPost = true;
        if (is_null($type) or $type === 'post') {
            $this->_data = $_POST;
            return;
        }

        $this->_raw = file_get_contents('php://input');
        if (empty($this->_raw)) return;

        switch ($type) {
            case 'json':
                $this->_data = json_decode($this->_raw, true);
                break;

            case 'xml':
                $this->_data = xml_decode($this->_raw, true);
                break;

            case 'php':
                $this->_data = unserialize($this->_raw);
                break;

            case 'unknown':
                //不确定格式
                if (($this->_raw[0] === '{' and $this->_raw[-1] === '}')
                    or ($this->_raw[0] === '[' and $this->_raw[-1] === ']')) {
                    $this->_data = json_decode($this->_raw, true);

                } else if ($this->_raw[0] === '<' and $this->_raw[-1] === '>') {
                    $this->_data = xml_decode($this->_raw, true);

                }
                break;

            default:
                parse_str($this->_raw, $this->_data);
        }


        if (!is_array($this->_data) or empty($this->_data)) $this->_data = [];
    }


}