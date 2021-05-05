<?php
declare(strict_types=1);

namespace esp\library\request;

use esp\error\EspError;
use esp\library\ext\Xss;
use function esp\helper\is_card;
use function esp\helper\is_date;
use function esp\helper\is_domain;
use function esp\helper\is_ip;
use function esp\helper\is_mail;
use function esp\helper\is_time;
use function esp\helper\is_url;
use function esp\helper\is_match;
use function esp\helper\xml_decode;

class Get extends Request
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
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';
        $value = trim($value);
        if ($value === '') return '';

        switch ($type) {
            case 'cn':
                if (!preg_match('/^[\x{4e00}-\x{9fa5}]{$n}$/u', $value)) return '';
                break;
            case 'en':
                if (!preg_match('/^[a-zA-Z]+$/', $value)) return '';
                break;
            case 'number':
                if (!preg_match('/^\d+$/', $value)) return '';
                break;
            case 'decimal':
                if (!preg_match('/^\d+(\.\d+)?$/', $value)) return '';
                break;
            case 'alphanumeric':
                if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) return '';
                break;
            case 'mobile':
                if (!preg_match('/^1\d{10}$/', $value)) return '';
                break;
            case 'card':
                if (!is_card($value)) return '';
                break;
            case 'url':
                if (!is_url($value) && !is_domain($value)) return '';
                break;
            case 'mail':
            case 'email':
                if (!is_mail($value)) return '';
                break;
            case 'ip':
                if (!is_ip($value)) return '';
                break;
            case 'date':
                if (!is_date($value)) return '';
                break;
            case 'time':
                if (!is_time($value)) return '';
                break;
            case 'datetime':
                if (!strtotime($value)) return '';
                break;
            default:
                if (is_match($type) and !preg_match($type, $value)) return '';
        }

        return $value;
    }

    /**
     * 获取一个时间区间，如：
     * date=2020-12-17%2000:00:00,2020-12-18%2014:15:34
     * date=2020-12-17%2000:00:00~2020-12-18%2014:15:34
     * date=2020-12-17 00:00:00 , 2020-12-18 14:15:34
     * date=2020-12-17 00:00:00 ~ 2020-12-18 14:15:34
     * @param string $key
     * @param string $symbol
     * @return array
     * @throws EspError
     */
    public function date_zone(string $key, string $symbol = ','): array
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return [];
        $time = explode($symbol, $value);
        if (count($time) !== 2) return [];

        $time[0] = str_replace(['+', '%3A'], [' ', ':'], $time[0]);
        $time[1] = str_replace(['+', '%3A'], [' ', ':'], $time[1]);
        $time[2] = strtotime($time[0]) ?: 0;
        $time[3] = strtotime($time[1]) ?: 0;
        return $time;
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
        return strtotime($value) ?: 0;
    }

    public function int(string $key, bool $ceil = false): int
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return 0;
        if (is_array($value)) $value = array_sum($value);

        if ($ceil) return (int)ceil($value);
        return intval($value);
    }

    public function float(string $key): float
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return floatval(0);
        return floatval($value);
    }

    public function bool(string $key): bool
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return false;
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
        return intval(strval(floatval($value) * 100));
    }

    public function match(string $key, string $pnt): string
    {
        if (!is_match($pnt)) throw new EspError('传入的表达式不合法', 1);
        $value = $this->getData($key, $force);
        if (is_null($value)) return '';

        if (!preg_match($pnt, $value)) return '';
        return strval($value);
    }


    public function array(string $key, string $encode = 'json'): array
    {
        $value = $this->getData($key, $force);
        if (is_null($value)) return [];

        if (is_string($value)) {
            if ($encode === 'xml') {
                $value = xml_decode($value, true) ?: [];
            } else {
                $value = json_decode($value, true) ?: [];
            }
        }

        return $value;
    }


    public function __construct()
    {
        $this->_data = $_GET ?: [];
    }

}