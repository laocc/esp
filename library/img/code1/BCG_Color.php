<?php

namespace esp\library\img\code1;

final class BCG_Color
{
    protected $r, $g, $b;

    public function __construct()
    {
        $args = func_get_args();
        $c = count($args);
        if ($c === 3) {
            $this->r = intval($args[0]);
            $this->g = intval($args[1]);
            $this->b = intval($args[2]);
        } elseif ($c === 1) {
            list($this->r, $this->g, $this->b) = $this->getRGBColor($args[0]);

        } else {
            $this->r = $this->g = $this->b = 0;
        }
    }

    public function allocate(&$im)
    {
        return imagecolorallocate($im, $this->r, $this->g, $this->b);
    }


    protected function getRGBColor($color)
    {
        if (is_array($color)) {
            if (count($color) === 1) {
                list($R, $G, $B) = [mt_rand(0, $color[0]), mt_rand(0, $color[0]), mt_rand(0, $color[0])];

            } else if (count($color) === 2) {//是一个取值范围
                list($R, $G, $B) = [mt_rand(...$color), mt_rand(...$color), mt_rand(...$color)];

            } else {
                list($R, $G, $B) = $color;
            }
        } else {
            $color = preg_replace('/^[a-z]+$/i', $this->getColorHex('$1'), $color);//颜色名换色值
            $color = preg_replace('/^\#([a-f0-9])([a-f0-9])([a-f0-9])$/i', '#$1$1$2$2$3$3', $color);//短色值换为标准色值
            $color = preg_match('/^\#[a-f0-9]{6}$/i', $color) ? $color : '#000000';//不是标准色值的，都当成黑色
            $R = hexdec(substr($color, 1, 2));
            $G = hexdec(substr($color, 3, 2));
            $B = hexdec(substr($color, 5, 2));
        }
        return [$R, $G, $B];
    }

    /**
     * 根据颜色名称转换为色值
     * @param $code
     * @return int
     */
    protected function getColorHex($code)
    {
        switch (strtolower($code)) {
            case 'white':
                return '#ffffff';
            case 'black':
                return '#000000';
            case 'maroon':
                return '#800000';
            case 'red':
                return '#ff0000';
            case 'orange':
                return '#ffa500';
            case 'yellow':
                return '#ffff00';
            case 'olive':
                return '#808000';
            case 'purple':
                return '#800080';
            case 'fuchsia':
                return '#ff00ff';
            case 'lime':
                return '#00ff00';
            case 'green':
                return '#008000';
            case 'navy':
                return '#000080';
            case 'blue':
                return '#0000ff';
            case 'aqua':
                return '#00ffff';
            case 'teal':
                return '#008080';
            case 'silver':
                return '#c0c0c0';
            case 'gray':
                return '#808080';
            default:
                return '#ffffff';
        }

//
//        $args[0] = intval($args[0]);
//        $this->r = ($args[0] & 0xff0000) >> 16;
//        $this->g = ($args[0] & 0x00ff00) >> 8;
//        $this->b = ($args[0] & 0x0000ff);
    }

}
