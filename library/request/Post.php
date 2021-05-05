<?php
declare(strict_types=1);

namespace esp\library\request;

use esp\error\EspError;
use esp\library\ext\Xss;
use function esp\helper\is_mob;
use function esp\helper\is_card;
use function esp\helper\is_date;
use function esp\helper\is_domain;
use function esp\helper\is_ip;
use function esp\helper\is_mail;
use function esp\helper\is_phone;
use function esp\helper\is_time;
use function esp\helper\is_match;
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
        if ($chk = $this->errorString($value)) $this->recodeError($key, $chk);

        return $value;
    }

    private function checkString(string $type, $value)
    {
        switch ($type) {
            case 'mobile':
                return is_mob($value);
            case 'phone':
                return is_phone($value);
            case 'card':
                return is_card($value);
            case 'url':
                return is_url($value) || is_domain($value);
            case 'mail':
            case 'email':
                return is_mail($value);
            case 'ip':
            case 'ip4':
                return is_ip($value, 'ipv4');
            case 'ip6':
                return is_ip($value, 'ipv6');
            case 'date':
                return is_date($value);
            case 'time':
                return is_time($value);
            case 'datetime':
                return strtotime($value);
            case 'cn':
                return preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $value);
            case 'en':
                return preg_match('/^[a-zA-Z]+$/', $value);
            case 'number':
                return preg_match('/^\d+$/', $value);
            case 'decimal':
                return preg_match('/^\d+(\.\d+)?$/', $value);
            case 'alphanumeric':
                return preg_match('/^[a-zA-Z0-9]+$/', $value);
            default:
                if (is_match($type)) return preg_match($type, $value);
        }
        return false;
    }

    /**
     * 按规则检查，若不为空则必须要符合规则
     * @param string $key
     * @param string ...$type 可以有多个检查规则，任一个符合即为合法值
     * @return string
     * @throws EspError
     */
    public function filter(string $key, string ...$type): string
    {
        $this->_min = null;
        $this->_max = null;
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';
        $value = trim($value);

        if ($value === '' && !$force) return '';

        $len = count($type);
        if ($len > 1) {
            $isTrue = 0;
            foreach ($type as $t) {
                if ($this->checkString($t, $value)) {
                    $isTrue++;
                    continue;
                }
            }
            if ($isTrue === 0) {
                if ($force or !empty($value)) $this->recodeError($key, "不符合规则要求");
                return '';
            } else {
                return $value;
            }

        } else if (!$this->checkString($type[0], $value)) {
            switch ($type[0]) {
                case 'cn':
                    $this->recodeError($key, "必须为全中文");
                    break;
                case 'en':
                    $this->recodeError($key, "值必须为全英文字母");
                    break;
                case 'number':
                    $this->recodeError($key, "必须纯数字");
                    break;
                case 'decimal':
                    $this->recodeError($key, "必须数字或小数");
                    break;
                case 'alphanumeric':
                    $this->recodeError($key, "必须为全英文或数字");
                    break;
                case 'mobile':
                    $this->recodeError($key, "必须为手机号码格式");
                    break;
                case 'phone':
                    $this->recodeError($key, "必须为电话号码格式");
                    break;
                case 'card':
                    $this->recodeError($key, "必须为符合规则的身份证号码");
                    break;
                case 'url':
                    $this->recodeError($key, "必须为URL格式");
                    break;
                case 'mail':
                case 'email':
                    $this->recodeError($key, "必须为电子邮箱地址格式");
                    break;
                case 'ip':
                case 'ip4':
                    $this->recodeError($key, "必须为IP4格式");
                    break;
                case 'ip6':
                    $this->recodeError($key, "必须为IP6格式");
                    break;
                case 'date':
                    $this->recodeError($key, "必须为日期格式");
                    break;
                case 'time':
                    $this->recodeError($key, "必须为时间格式");
                    break;
                case 'datetime':
                    $this->recodeError($key, "必须为日期时间格式");
                    break;
                default:
                    $this->recodeError($key, "不是指定格式的数据");
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

        $value = strtotime($value) ?: 0;
        if ($chk = $this->errorNumber($value, 1)) $this->recodeError($key, $chk);
        return $value;
    }

    public function number(string $key): string
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';
        if (!preg_match('/^\d+$/', $value)) $this->recodeError($key, "必须为全数字");
        return strval($value);
    }

    public function int(string $key, bool $zero = true): int
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return 0;
        if (is_array($value)) $value = array_sum($value);

        if ($value === '' && $force) $this->recodeError($key);
        $value = intval($value);
        if ($value === 0 && !$zero) $this->recodeError($key, '不能为零或空值');
        if ($chk = $this->errorNumber($value)) $this->recodeError($key, $chk);
        return $value;
    }

    public function float(string $key, bool $zero = true): float
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return floatval(0);
        if ($value === '' && $force) $this->recodeError($key);
        $value = floatval($value);
        if ($value == 0 && !$zero) $this->recodeError($key, '不能为零或空值');
        if ($chk = $this->errorNumber($value)) $this->recodeError($key, $chk);
        return $value;
    }

    public function bool(string $key): bool
    {
        $this->_min = null;
        $this->_max = null;
        $value = $this->getData($key, $force);
        if (is_null($value)) return false;
        if ($value === '' && $force) $this->recodeError($key);
        if (is_bool($value)) return $value;
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
        $value = intval(strval(floatval($value) * 100));
        if ($chk = $this->errorNumber($value, 2)) $this->recodeError($key, $chk);
        return $value;
    }

    public function match(string $key, string $pnt): string
    {
        $this->_min = null;
        $this->_max = null;
        if (!is_match($pnt)) throw new EspError('传入的表达式不合法', 1);
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';
        if (empty($value) && $force) $this->recodeError($key);

        if (!preg_match($pnt, $value)) {
            if ($force) $this->recodeError($key, "不是指定格式的数据");
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
            if ($force) $this->recodeError($key, "不是有效的JSON格式");
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
            if ($force) $this->recodeError($key, "不是有效的XML格式");
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
            if ($force) $this->recodeError($key, "无法转换为数组或数组为空");
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