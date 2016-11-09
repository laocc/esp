<?php
namespace tools;

use \Yaf\Config\Ini;

/**
 * 客服机器人类
 * Class Robot
 *
 * 数据模型：包含【$ask】，
 * 若【$and】存在，则且包含【$and】，
 * 若【$not】存在，则且不包含【$not】，
 * 匹配顺序以数据模型中记录顺序为准，第一次符号即返回
 */
class Robot
{
    private static $name = '';//机器人名称
    private $answer = [];

    public function __construct($set = null)
    {
//        if (!$set) return;
//        self::$name = $set['name'];
        $answer = new Ini(_CONFIG_PATH . 'robot.ini');

        foreach ($answer as $k => &$ans) {
            $this->answer[] = [$this->replace($ans->ask), $this->replace($ans->and), $this->replace($ans->not)];
        }
    }

    private function replace($str)
    {
        $fh = '/[\~\!\@\#\$\%\^\&\*\(\)\_\+\`\-\=\[\]\;\'\,\.\/\{\}\:\"\<\>\020\?\x20\\\]/';
        $new = preg_replace($fh, '|', trim($str));
        $new = str_replace('||', '|', $new);
        return trim($new, '|');
    }

    /**
     * @return mixed
     * 机器人的欢迎语
     */
    public function welcome()
    {
        $val = [
            '亲，有什么需要我帮助的吗？'
        ];
        return $val[array_rand($val)];
    }

    public function name()
    {
        return self::$name;
    }

    public function ask($key)
    {
        foreach ($this->answer as $k => &$val) {
            $ask = preg_match("/({$val->ask})+/is", $key);
            $and = (!$val->and or (!!$val->and and preg_match("/({$val->and})+/is", $key)));
            $not = (!$val->not or (!!$val->not and !preg_match("/({$val->not})+/is", $key)));
            if ($ask and $and and $not) return $val->reply;
        }
        return "亲，我是很笨的机器人，无法回答您的问题，稍后有我们的工作人员和您联系，好吗？";
    }

    private static function reFuHao($str)
    {
        if (!$str) return null;
        $str = str_ireplace(['，', '。', '、'], '|', $str);
        return preg_replace('/[,\. ;:]/is', '|', $str);
    }


}