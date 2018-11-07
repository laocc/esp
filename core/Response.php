<?php

namespace esp\core;


use esp\library\ext\Xml;

final class Response
{
    private static $_display = Array(
        'type' => null,//显示方式，json,html等
        'value' => null,//显示的内容
        'result' => null,//最终的打印结果
        'content' => null,//最终返给客户端的Content-Type类型
    );

    private static $_autoRun = (!_CLI);

    public static function _init(array $conf)
    {
        self::$_autoRun = $conf['auto'] ?? (!_CLI);
    }


    /**
     * 接受控制器设置页面特殊显示内容
     * @param string $name
     * @param $value
     * @return bool
     * @throws \Exception
     */
    public static function setDisplay(string $name, $value)
    {
        if (!in_array($name, ['json', 'xml', 'php', 'text', 'md', 'html'])) throw new \Exception("不接受{$name}类型的值");
        self::$_display += ['type' => $name, 'value' => $value];
        return true;
    }


    /**
     * @param bool $run
     */
    public static function autoRun(bool $run)
    {
        self::$_autoRun = $run;
    }


    /**
     * 返回当前请求须响应的格式，json,xml,html,text等
     * @return mixed
     */
    public static function getType()
    {
        return self::$_display['type'];
    }

    public static function getResult()
    {
        return self::$_display['result'];
    }


    /**
     * 渲染视图并返回
     * @param void $value 控制器返回的值
     */
    public static function display(&$value): void
    {
        if (self::$_autoRun === false) return;
        if (is_null($value)) goto render;

        if (is_array($value)) {//直接显示为json/jsonP
            self::$_display['content'] = 'application/json';
            if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                self::$_display['result'] = "{$match[1]}" . json_encode($value, 256) . ";";
            } else {
                self::$_display['result'] = json_encode($value, 256);
            }
            goto display;

        } else if (is_string($value)) {//直接按文本显示
            self::$_display['content'] = 'text/plain';
            self::$_display['result'] = &$value;
            goto display;

        } else if (is_int($value)) {//如果是某种错误代码，则显示为错误
            self::$_display['content'] = 'text/html';
            self::$_display['result'] = Error::displayState($value);
            goto display;

        } else if (is_bool($value)) {//简单表示是否立即渲染
            if ($value) goto render;
            goto display;
        }

        render:
        self::render();

        display:
        if (!headers_sent()) {
            header("Content-type: " . self::$_display['content'] . "; charset=UTF-8", true, 200);
        }

        echo self::$_display['result'];
    }

    /**
     * 渲染视图并返回
     * @throws \Exception
     */
    private static function render()
    {
        switch (strtolower(self::$_display['type'])) {
            case 'json':
                self::$_display['result'] = json_encode(self::$_display['value'], 256);
                if (isset($_GET['callback']) and preg_match('/^(\w+)$/', $_GET['callback'], $match)) {
                    self::$_display['result'] = "{$match[1]}(" . self::$_display['result'] . ");";
                }
                break;

            case 'php':
                self::$_display['result'] = serialize(self::$_display['value']);
                break;

            case 'html':
                self::$_display['result'] = print_r(self::$_display['value'], true);
                break;

            case 'text':
                self::$_display['result'] = print_r(self::$_display['value'], true);
                break;

            case 'xml':
                if (is_array(self::$_display['value'][1])) {
                    self::$_display['result'] = (new Xml(self::$_display['value'][1], self::$_display['value'][0]))->render();
                } else {
                    self::$_display['result'] = self::$_display['value'][1];
                }
                break;

            case 'md':
            default:
                self::$_display['type'] = 'html';
                self::$_display['result'] = View::render();
        }

        self::$_display['content'] = Config::mime(self::$_display['type']);

    }


}