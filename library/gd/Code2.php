<?php

namespace esp\library\gd;

use esp\error\EspError;
use esp\library\gd\ext\Gd;

/**
 *
 * 生成二维码
 * 1，可以指定二维码颜色；
 * 2，可以用图片当成二维码主体色；
 * 3，可以加LOGO在二维码中间；
 * 4，可以指定背景色；
 * 5，也可以将有关数据组成一个三维数组，供其他语言以像素方式显式，只需传入文本，前几种功能均无效。
 *
 * "<img src='data:image/png;base64,{$data}'>";
 *
 * $v = \tools\Code2::create($option);
 * 可选参数见函数前面定义，以下几点须注意：
 *
 * $option['level']代表该二维码容错率，
 *      可选：0123，或LMQH分别对应0123
 *      控制：二维码的容错率，分别为：7，15，25，30
 *      其中：当=0时，不可以加LOGO
 *
 * $option['save']代表当前操作是将二维码保存到文件，=false直接显示，=true返回下列数据
 * Array
 * (
 * [root] => /home/web/blog/code/       保存目录，一般不用在URL中
 * [path] => qrCode/                    目录中的文件夹名，用在URL中
 * [name] => 6dc84ecc2ae4a614e6707c0cb3b988c7.png
 * )
 * 最终URL：http://www.domain.com/qrCode/6dc84ecc2ae4a614e6707c0cb3b988c7.png
 * URL须自行组合。
 *
 *
 * $option['color']表示二维码颜色，也可以指定为一个实际存在的图片
 *      注意：若用图片，则该图片大部分应该是以深色为主，否则生成的二维码可能很难识别
 *
 * $option['background']表示二维码背景色，不要太深了
 *
 * $option['logo']如果想在二维码中间加个LOGO，就用它指定一个实际存在的图片
 *      注意：这个图片最好是正方形，否则从左上角按最小边裁切出一个正方形
 *
 * $option['parent']将二维码贴在这个图片指定x,y位置，若不指定位置，则居中
 * $option['shadow']如果有底图，则这个可以定义一个阴影，可以指定偏移量、颜色、透明度
 *
 * TODO:特效加的越多，越耗时。
 *
 */
class Code2
{
    public static function create(array $dimOption)
    {
        $option = array();
        $option['text'] = 'no Value';
        $option['level'] = 'Q';    //可选LMQH
        $option['size'] = 10;    //每条线像素点,一般不需要动，若要固定尺寸，用width限制
        $option['margin'] = 1;    //二维码外框空白，指1个size单位，不是指像素
        $option['save'] = 0;    //0：只显示，1：只保存，2：即显示也保存，3：返回GD数据流，
        $option['width'] = 0;     //生成的二维码宽高，若不指定则以像素点计算
        $option['color'] = '#000000';   //二维码本色，也可以是图片
        $option['background'] = '#ffffff';  //二维码背景色
        $option['root'] = getcwd();  //保存目录
        $option['path'] = 'code2/';        //目录里的文件夹
        $option['filename'] = null;        //生成的文件名

        $option['logo'] = null;         //LOGO图片
        $option['logo_border'] = '#ffffff';  //LOGO外边框颜色

        $option['parent'] = null;//一个文件地址，将二维码贴在这个图片上
        $option['parent_x'] = null;//若指定，则以指定为准
        $option['parent_y'] = null;//为null时，居中

        $option['shadow'] = null;//颜色色值，阴影颜色，只有当parent存在时有效
        $option['shadow_x'] = 2;//阴影向右偏移，若为负数则向左
        $option['shadow_y'] = 2;//阴影向下偏移，若为负数则向上
        $option['shadow_alpha'] = 0;//透明度，百分数


        $option = $dimOption + $option;
        if (is_array($option['text'])) $option['text'] = json_encode($option['text'], 256 | 64);

        $option['root'] = rtrim($option['root'], '/');
        $option['path'] = '/' . trim($option['path'], '/') . '/';

        $option['width'] = is_int($option['width']) ? $option['width'] : 400;
        $option['size'] = is_int($option['size']) ? (($option['size'] < 1 or $option['size'] > 20) ? 10 : $option['size']) : 10;
        $option['margin'] = is_int($option['margin']) ? (($option['margin'] < 0 or $option['margin'] > 20) ? 1 : $option['margin']) : 1;
        if (strlen($option['text']) < 1) $option['text'] = 'null';
        if (strlen($option['text']) > 500) $option['text'] = substr($option['text'], 0, 500);

        if (is_int($option['level']) and $option['level'] > 3) $option['level'] = 3;
        $option['level'] = preg_match('/^[lQmh0123]$/i', $option['level']) ? strtoupper($option['level']) : 'Q';
        $lmqh = ['L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3];
        if (in_array($option['level'], ['L', 'M', 'Q', 'H'])) $option['level'] = $lmqh[$option['level']];

        $file = Gd::getFileName($option['save'], $option['root'], $option['path'], $option['filename'], 'png');

        $ec = new qr_Encode();
        $im = $ec->create($option);

        $option = [
            'save' => $option['save'],//0：只显示，1：只保存，2：即显示也保存，3：返回GD数据流
            'filename' => $file['filename'],
            'type' => IMAGETYPE_PNG,//文件类型
        ];

        $gd = Gd::draw($im, $option);
        if ($option['save'] === 3) return $gd;
        if ($option['save'] !== 1) exit;
        return $file;
    }

    /**
     * 计算要组成二维码的内容为一个三维数组，供JS调用
     * @param $text
     * @return array
     */
    public static function bin($text)
    {
        if (is_array($text)) $text = $text['text'];
        $obj = new qr_Encode();
        $val = $obj->encode($text, 1);
        $jsVal = Array();
        foreach ($val as $i => &$a) {
            $jsVal[$i] = str_split($a);
        }
        return $jsVal;
    }

}

class qr_Spec
{
    public static $capacity = array(
        array(0, 0, 0, array(0, 0, 0, 0)),
        array(21, 26, 0, array(7, 10, 13, 17)), // 1
        array(25, 44, 7, array(10, 16, 22, 28)),
        array(29, 70, 7, array(15, 26, 36, 44)),
        array(33, 100, 7, array(20, 36, 52, 64)),
        array(37, 134, 7, array(26, 48, 72, 88)), // 5
        array(41, 172, 7, array(36, 64, 96, 112)),
        array(45, 196, 0, array(40, 72, 108, 130)),
        array(49, 242, 0, array(48, 88, 132, 156)),
        array(53, 292, 0, array(60, 110, 160, 192)),
        array(57, 346, 0, array(72, 130, 192, 224)), //10
        array(61, 404, 0, array(80, 150, 224, 264)),
        array(65, 466, 0, array(96, 176, 260, 308)),
        array(69, 532, 0, array(104, 198, 288, 352)),
        array(73, 581, 3, array(120, 216, 320, 384)),
        array(77, 655, 3, array(132, 240, 360, 432)), //15
        array(81, 733, 3, array(144, 280, 408, 480)),
        array(85, 815, 3, array(168, 308, 448, 532)),
        array(89, 901, 3, array(180, 338, 504, 588)),
        array(93, 991, 3, array(196, 364, 546, 650)),
        array(97, 1085, 3, array(224, 416, 600, 700)), //20
        array(101, 1156, 4, array(224, 442, 644, 750)),
        array(105, 1258, 4, array(252, 476, 690, 816)),
        array(109, 1364, 4, array(270, 504, 750, 900)),
        array(113, 1474, 4, array(300, 560, 810, 960)),
        array(117, 1588, 4, array(312, 588, 870, 1050)), //25
        array(121, 1706, 4, array(336, 644, 952, 1110)),
        array(125, 1828, 4, array(360, 700, 1020, 1200)),
        array(129, 1921, 3, array(390, 728, 1050, 1260)),
        array(133, 2051, 3, array(420, 784, 1140, 1350)),
        array(137, 2185, 3, array(450, 812, 1200, 1440)), //30
        array(141, 2323, 3, array(480, 868, 1290, 1530)),
        array(145, 2465, 3, array(510, 924, 1350, 1620)),
        array(149, 2611, 3, array(540, 980, 1440, 1710)),
        array(153, 2761, 3, array(570, 1036, 1530, 1800)),
        array(157, 2876, 0, array(570, 1064, 1590, 1890)), //35
        array(161, 3034, 0, array(600, 1120, 1680, 1980)),
        array(165, 3196, 0, array(630, 1204, 1770, 2100)),
        array(169, 3362, 0, array(660, 1260, 1860, 2220)),
        array(173, 3532, 0, array(720, 1316, 1950, 2310)),
        array(177, 3706, 0, array(750, 1372, 2040, 2430)) //40
    );
    public static $eccTable = array(
        array(array(0, 0), array(0, 0), array(0, 0), array(0, 0)),
        array(array(1, 0), array(1, 0), array(1, 0), array(1, 0)), // 1
        array(array(1, 0), array(1, 0), array(1, 0), array(1, 0)),
        array(array(1, 0), array(1, 0), array(2, 0), array(2, 0)),
        array(array(1, 0), array(2, 0), array(2, 0), array(4, 0)),
        array(array(1, 0), array(2, 0), array(2, 2), array(2, 2)), // 5
        array(array(2, 0), array(4, 0), array(4, 0), array(4, 0)),
        array(array(2, 0), array(4, 0), array(2, 4), array(4, 1)),
        array(array(2, 0), array(2, 2), array(4, 2), array(4, 2)),
        array(array(2, 0), array(3, 2), array(4, 4), array(4, 4)),
        array(array(2, 2), array(4, 1), array(6, 2), array(6, 2)), //10
        array(array(4, 0), array(1, 4), array(4, 4), array(3, 8)),
        array(array(2, 2), array(6, 2), array(4, 6), array(7, 4)),
        array(array(4, 0), array(8, 1), array(8, 4), array(12, 4)),
        array(array(3, 1), array(4, 5), array(11, 5), array(11, 5)),
        array(array(5, 1), array(5, 5), array(5, 7), array(11, 7)), //15
        array(array(5, 1), array(7, 3), array(15, 2), array(3, 13)),
        array(array(1, 5), array(10, 1), array(1, 15), array(2, 17)),
        array(array(5, 1), array(9, 4), array(17, 1), array(2, 19)),
        array(array(3, 4), array(3, 11), array(17, 4), array(9, 16)),
        array(array(3, 5), array(3, 13), array(15, 5), array(15, 10)), //20
        array(array(4, 4), array(17, 0), array(17, 6), array(19, 6)),
        array(array(2, 7), array(17, 0), array(7, 16), array(34, 0)),
        array(array(4, 5), array(4, 14), array(11, 14), array(16, 14)),
        array(array(6, 4), array(6, 14), array(11, 16), array(30, 2)),
        array(array(8, 4), array(8, 13), array(7, 22), array(22, 13)), //25
        array(array(10, 2), array(19, 4), array(28, 6), array(33, 4)),
        array(array(8, 4), array(22, 3), array(8, 26), array(12, 28)),
        array(array(3, 10), array(3, 23), array(4, 31), array(11, 31)),
        array(array(7, 7), array(21, 7), array(1, 37), array(19, 26)),
        array(array(5, 10), array(19, 10), array(15, 25), array(23, 25)), //30
        array(array(13, 3), array(2, 29), array(42, 1), array(23, 28)),
        array(array(17, 0), array(10, 23), array(10, 35), array(19, 35)),
        array(array(17, 1), array(14, 21), array(29, 19), array(11, 46)),
        array(array(13, 6), array(14, 23), array(44, 7), array(59, 1)),
        array(array(12, 7), array(12, 26), array(39, 14), array(22, 41)), //35
        array(array(6, 14), array(6, 34), array(46, 10), array(2, 64)),
        array(array(17, 4), array(29, 14), array(49, 10), array(24, 46)),
        array(array(4, 18), array(13, 32), array(48, 14), array(42, 32)),
        array(array(20, 4), array(40, 7), array(43, 22), array(10, 67)),
        array(array(19, 6), array(18, 31), array(34, 34), array(20, 61)),//40
    );

    private static function set(&$srctab, $x, $y, $repl, $replLen = false)
    {
        $srctab[$y] = substr_replace($srctab[$y], ($replLen !== false) ? substr($repl, 0, $replLen) : $repl, $x, ($replLen !== false) ? $replLen : strlen($repl));
    }


    public static function getDataLength($version, $level)
    {
        return self::$capacity[$version][1] - self::$capacity[$version][3][$level];
    }


    public static function getECCLength($version, $level)
    {
        return self::$capacity[$version][3][$level];
    }


    public static function getWidth($version)
    {
        return self::$capacity[$version][0];
    }


    public static function getRemainder($version)
    {
        return self::$capacity[$version][2];
    }


    public static function getMinimumVersion($size, $level)
    {

        for ($i = 1; $i <= 40; $i++) {
            $words = self::$capacity[$i][1] - self::$capacity[$i][3][$level];
            if ($words >= $size)
                return $i;
        }

        return -1;
    }

    //######################################################################

    public static $lengthTableBits = array(
        array(10, 12, 14),
        array(9, 11, 13),
        array(8, 16, 16),
        array(8, 10, 12)
    );


    public static function lengthIndicator($mode, $version)
    {
        if ($mode == 4)
            return 0;

        if ($version <= 9) {
            $l = 0;
        } else if ($version <= 26) {
            $l = 1;
        } else {
            $l = 2;
        }

        return self::$lengthTableBits[$mode][$l];
    }


    public static function maximumWords($mode, $version)
    {
        if ($mode == 4)
            return 3;

        if ($version <= 9) {
            $l = 0;
        } else if ($version <= 26) {
            $l = 1;
        } else {
            $l = 2;
        }

        $bits = self::$lengthTableBits[$mode][$l];
        $words = (1 << $bits) - 1;

        if ($mode == 3) {
            $words *= 2; // the number of bytes is required
        }

        return $words;
    }


    // CACHEABLE!!!

    public static function getEccSpec($version, $level, array &$spec)
    {
        if (count($spec) < 5) {
            $spec = array(0, 0, 0, 0, 0);
        }

        $b1 = self::$eccTable[$version][$level][0];
        $b2 = self::$eccTable[$version][$level][1];
        $data = self::getDataLength($version, $level);
        $ecc = self::getECCLength($version, $level);

        if ($b2 == 0) {
            $spec[0] = $b1;
            $spec[1] = (int)($data / $b1);
            $spec[2] = (int)($ecc / $b1);
            $spec[3] = 0;
            $spec[4] = 0;
        } else {
            $spec[0] = $b1;
            $spec[1] = (int)($data / ($b1 + $b2));
            $spec[2] = (int)($ecc / ($b1 + $b2));
            $spec[3] = $b2;
            $spec[4] = $spec[1] + 1;
        }
    }



    //对齐模式---------------------------------------------------
    //位置校准图案。
    //这个数组只包含第二和第三的位置
    //对齐方式。其余的人，可以从距离计算
    //他们之间。
    //看附录1表（pp.71）JIS x0510:2004。

    public static $alignmentPattern = array(
        array(0, 0),
        array(0, 0), array(18, 0), array(22, 0), array(26, 0), array(30, 0), // 1- 5
        array(34, 0), array(22, 38), array(24, 42), array(26, 46), array(28, 50), // 6-10
        array(30, 54), array(32, 58), array(34, 62), array(26, 46), array(26, 48), //11-15
        array(26, 50), array(30, 54), array(30, 56), array(30, 58), array(34, 62), //16-20
        array(28, 50), array(26, 50), array(30, 54), array(28, 54), array(32, 58), //21-25
        array(30, 58), array(34, 62), array(26, 50), array(30, 54), array(26, 52), //26-30
        array(30, 56), array(34, 60), array(30, 58), array(34, 62), array(30, 54), //31-35
        array(24, 50), array(28, 54), array(32, 58), array(26, 54), array(30, 58), //35-40
    );


    /** --------------------------------------------------------------------
     * Put an alignment marker.
     * @param array $frame
     * @param $ox
     * @param $oy
     * center coordinate of the pattern
     */
    public static function putAlignmentMarker(array &$frame, $ox, $oy)
    {
        $finder = array(
            "\xa1\xa1\xa1\xa1\xa1",
            "\xa1\xa0\xa0\xa0\xa1",
            "\xa1\xa0\xa1\xa0\xa1",
            "\xa1\xa0\xa0\xa0\xa1",
            "\xa1\xa1\xa1\xa1\xa1"
        );

        $yStart = $oy - 2;
        $xStart = $ox - 2;

        for ($y = 0; $y < 5; $y++) {
            self::set($frame, $xStart, $yStart + $y, $finder[$y]);
        }
    }


    public static function putAlignmentPattern($version, &$frame, $width)
    {
        if ($version < 2)
            return;

        $d = self::$alignmentPattern[$version][1] - self::$alignmentPattern[$version][0];
        if ($d < 0) {
            $w = 2;
        } else {
            $w = (int)(($width - self::$alignmentPattern[$version][0]) / $d + 2);
        }

        if ($w * $w - 3 == 1) {
            $x = self::$alignmentPattern[$version][0];
            $y = self::$alignmentPattern[$version][0];
            self::putAlignmentMarker($frame, $x, $y);
            return;
        }

        $cx = self::$alignmentPattern[$version][0];
        for ($x = 1; $x < $w - 1; $x++) {
            self::putAlignmentMarker($frame, 6, $cx);
            self::putAlignmentMarker($frame, $cx, 6);
            $cx += $d;
        }

        $cy = self::$alignmentPattern[$version][0];
        for ($y = 0; $y < $w - 1; $y++) {
            $cx = self::$alignmentPattern[$version][0];
            for ($x = 0; $x < $w - 1; $x++) {
                self::putAlignmentMarker($frame, $cx, $cy);
                $cx += $d;
            }
            $cy += $d;
        }
    }

    // Version information pattern -----------------------------------------

    // Version information pattern (BCH coded).
    // See Table 1 in Appendix D (pp.68) of JIS X0510:2004.

    // size: [40 - 6]

    public static $versionPattern = array(
        0x07c94, 0x085bc, 0x09a99, 0x0a4d3, 0x0bbf6, 0x0c762, 0x0d847, 0x0e60d,
        0x0f928, 0x10b78, 0x1145d, 0x12a17, 0x13532, 0x149a6, 0x15683, 0x168c9,
        0x177ec, 0x18ec4, 0x191e1, 0x1afab, 0x1b08e, 0x1cc1a, 0x1d33f, 0x1ed75,
        0x1f250, 0x209d5, 0x216f0, 0x228ba, 0x2379f, 0x24b0b, 0x2542e, 0x26a64,
        0x27541, 0x28c69
    );


    public static function getVersionPattern($version)
    {
        if ($version < 7 || $version > 40)
            return 0;

        return self::$versionPattern[$version - 7];
    }

    // Format information --------------------------------------------------
    // See calcFormatInfo in tests/test_qr_spec.c (orginal qr_encode c lib)

    public static $formatInfo = array(
        array(0x77c4, 0x72f3, 0x7daa, 0x789d, 0x662f, 0x6318, 0x6c41, 0x6976),
        array(0x5412, 0x5125, 0x5e7c, 0x5b4b, 0x45f9, 0x40ce, 0x4f97, 0x4aa0),
        array(0x355f, 0x3068, 0x3f31, 0x3a06, 0x24b4, 0x2183, 0x2eda, 0x2bed),
        array(0x1689, 0x13be, 0x1ce7, 0x19d0, 0x0762, 0x0255, 0x0d0c, 0x083b)
    );

    public static function getFormatInfo($mask, $level)
    {
        if ($mask < 0 || $mask > 7)
            return 0;

        if ($level < 0 || $level > 3)
            return 0;

        return self::$formatInfo[$level][$mask];
    }

    // Frame ---------------------------------------------------------------
    // Cache of initial frames.

    public static $frames = array();

    /** --------------------------------------------------------------------
     * Put a finder pattern.
     * @param frame
     * @param width
     * @param ox ,oy upper-left coordinate of the pattern
     */
    public static function putFinderPattern(&$frame, $ox, $oy)
    {
        $finder = array(
            "\xc1\xc1\xc1\xc1\xc1\xc1\xc1",
            "\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
            "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
            "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
            "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
            "\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
            "\xc1\xc1\xc1\xc1\xc1\xc1\xc1"
        );

        for ($y = 0; $y < 7; $y++) {
            self::set($frame, $ox, $oy + $y, $finder[$y]);
        }
    }


    public static function createFrame($version)
    {
        $width = self::$capacity[$version][0];
        $frameLine = str_repeat("\0", $width);
        $frame = array_fill(0, $width, $frameLine);

        // Finder pattern
        self::putFinderPattern($frame, 0, 0);
        self::putFinderPattern($frame, $width - 7, 0);
        self::putFinderPattern($frame, 0, $width - 7);

        // Separator
        $yOffset = $width - 7;

        for ($y = 0; $y < 7; $y++) {
            $frame[$y][7] = "\xc0";
            $frame[$y][$width - 8] = "\xc0";
            $frame[$yOffset][7] = "\xc0";
            $yOffset++;
        }

        $setPattern = str_repeat("\xc0", 8);

        self::set($frame, 0, 7, $setPattern);
        self::set($frame, $width - 8, 7, $setPattern);
        self::set($frame, 0, $width - 8, $setPattern);

        // Format info
        $setPattern = str_repeat("\x84", 9);
        self::set($frame, 0, 8, $setPattern);
        self::set($frame, $width - 8, 8, $setPattern, 8);

        $yOffset = $width - 8;

        for ($y = 0; $y < 8; $y++, $yOffset++) {
            $frame[$y][8] = "\x84";
            $frame[$yOffset][8] = "\x84";
        }

        // Timing pattern

        for ($i = 1; $i < $width - 15; $i++) {
            $frame[6][7 + $i] = chr(0x90 | ($i & 1));
            $frame[7 + $i][6] = chr(0x90 | ($i & 1));
        }

        // Alignment pattern
        self::putAlignmentPattern($version, $frame, $width);

        // Version information
        if ($version >= 7) {
            $vinf = self::getVersionPattern($version);

            $v = $vinf;

            for ($x = 0; $x < 6; $x++) {
                for ($y = 0; $y < 3; $y++) {
                    $frame[($width - 11) + $y][$x] = chr(0x88 | ($v & 1));
                    $v = $v >> 1;
                }
            }

            $v = $vinf;
            for ($y = 0; $y < 6; $y++) {
                for ($x = 0; $x < 3; $x++) {
                    $frame[$y][$x + ($width - 11)] = chr(0x88 | ($v & 1));
                    $v = $v >> 1;
                }
            }
        }

        // and a little bit...
        $frame[$width - 8][8] = "\x81";

        return $frame;
    }


    public static function serial($frame)
    {
        return gzcompress(join("\n", $frame), 9);
    }


    public static function unserial($code)
    {
        return explode("\n", gzuncompress($code));
    }


    public static function newFrame($version)
    {
        if ($version < 1 || $version > 40)
            return null;

        if (!isset(self::$frames[$version])) {
            self::$frames[$version] = self::createFrame($version);
        }

        if (is_null(self::$frames[$version]))
            return null;

        return self::$frames[$version];
    }


    public static function rsBlockNum($spec)
    {
        return $spec[0] + $spec[3];
    }

    public static function rsBlockNum1($spec)
    {
        return $spec[0];
    }

    public static function rsDataCodes1($spec)
    {
        return $spec[1];
    }

    public static function rsEccCodes1($spec)
    {
        return $spec[2];
    }

    public static function rsBlockNum2($spec)
    {
        return $spec[3];
    }

    public static function rsDataCodes2($spec)
    {
        return $spec[4];
    }

    public static function rsEccCodes2($spec)
    {
        return $spec[2];
    }

    public static function rsDataLength($spec)
    {
        return ($spec[0] * $spec[1]) + ($spec[3] * $spec[4]);
    }

    public static function rsEccLength($spec)
    {
        return ($spec[0] + $spec[3]) * $spec[2];
    }

}

class qr_InputItem
{

    public $mode;
    public $size;
    public $data;
    public $bstream;

    public function __construct($mode, $size, $data, $bstream = null)
    {
        $setData = array_slice($data, 0, $size);

        if (count($setData) < $size) {
            $setData = array_merge($setData, array_fill(0, $size - count($setData), 0));
        }

        if (!qr_Input::check($mode, $size, $setData)) {
            throw new EspError('Error m:' . $mode . ',s:' . $size . ',d:' . join(',', $setData));
        }

        $this->mode = $mode;
        $this->size = $size;
        $this->data = $setData;
        $this->bstream = $bstream;
    }

    public function encodeModeNum($version)
    {
        try {

            $words = (int)($this->size / 3);
            $bs = new qr_BitStream();

            $val = 0x1;
            $bs->appendNum(4, $val);
            $bs->appendNum(qr_Spec::lengthIndicator(0, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = (ord($this->data[$i * 3]) - ord('0')) * 100;
                $val += (ord($this->data[$i * 3 + 1]) - ord('0')) * 10;
                $val += (ord($this->data[$i * 3 + 2]) - ord('0'));
                $bs->appendNum(10, $val);
            }

            if ($this->size - $words * 3 == 1) {
                $val = ord($this->data[$words * 3]) - ord('0');
                $bs->appendNum(4, $val);
            } else if ($this->size - $words * 3 == 2) {
                $val = (ord($this->data[$words * 3]) - ord('0')) * 10;
                $val += (ord($this->data[$words * 3 + 1]) - ord('0'));
                $bs->appendNum(7, $val);
            }

            $this->bstream = $bs;
            return 0;

        } catch (EspError $e) {
            return -1;
        }
    }


    public function encodeModeAn($version)
    {
        try {
            $words = (int)($this->size / 2);
            $bs = new qr_BitStream();

            $bs->appendNum(4, 0x02);
            $bs->appendNum(qr_Spec::lengthIndicator(1, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = (int)qr_Input::lookAnTable(ord($this->data[$i * 2])) * 45;
                $val += (int)qr_Input::lookAnTable(ord($this->data[$i * 2 + 1]));

                $bs->appendNum(11, $val);
            }

            if ($this->size & 1) {
                $val = qr_Input::lookAnTable(ord($this->data[$words * 2]));
                $bs->appendNum(6, $val);
            }

            $this->bstream = $bs;
            return 0;

        } catch (EspError $e) {
            return -1;
        }
    }


    public function encodeMode8($version)
    {
        try {
            $bs = new qr_BitStream();

            $bs->appendNum(4, 0x4);
            $bs->appendNum(qr_Spec::lengthIndicator(2, $version), $this->size);

            for ($i = 0; $i < $this->size; $i++) {
                $bs->appendNum(8, ord($this->data[$i]));
            }

            $this->bstream = $bs;
            return 0;

        } catch (EspError $e) {
            return -1;
        }
    }


    public function encodeModeStructure()
    {
        try {
            $bs = new qr_BitStream();

            $bs->appendNum(4, 0x03);
            $bs->appendNum(4, ord($this->data[1]) - 1);
            $bs->appendNum(4, ord($this->data[0]) - 1);
            $bs->appendNum(8, ord($this->data[2]));

            $this->bstream = $bs;
            return 0;

        } catch (EspError $e) {
            return -1;
        }
    }


    public function estimateBitStreamSizeOfEntry($version)
    {
        $bits = 0;
        $version = $version ?: 1;
        switch ($this->mode) {
            case 0:
                $bits = qr_Input::estimateBitsModeNum($this->size);
                break;
            case 1:
                $bits = qr_Input::estimateBitsModeAn($this->size);
                break;
            case 2:
                $bits = qr_Input::estimateBitsMode8($this->size);
                break;
            //case 3:        $bits = qr_Input::estimateBitsModeKanji($this->size);break;
            case 4:
                return 20;
            default:
                return 0;
        }

        $l = qr_Spec::lengthIndicator($this->mode, $version);
        $m = 1 << $l;
        $num = (int)(($this->size + $m - 1) / $m);

        $bits += $num * (4 + $l);

        return $bits;
    }


    public function encodeBitStream($version)
    {
        try {

            unset($this->bstream);
            $words = qr_Spec::maximumWords($this->mode, $version);

            if ($this->size > $words) {

                $st1 = new qr_InputItem($this->mode, $words, $this->data);
                $st2 = new qr_InputItem($this->mode, $this->size - $words, array_slice($this->data, $words));

                $st1->encodeBitStream($version);
                $st2->encodeBitStream($version);

                $this->bstream = new qr_BitStream();
                $this->bstream->append($st1->bstream);
                $this->bstream->append($st2->bstream);

                unset($st1);
                unset($st2);

            } else {

                $ret = 0;

                switch ($this->mode) {
                    case 0:
                        $ret = $this->encodeModeNum($version);
                        break;
                    case 1:
                        $ret = $this->encodeModeAn($version);
                        break;
                    case 2:
                        $ret = $this->encodeMode8($version);
                        break;
                    //case 3:        $ret = $this->encodeModeKanji($version);break;
                    case 4:
                        $ret = $this->encodeModeStructure();
                        break;

                    default:
                        break;
                }

                if ($ret < 0)
                    return -1;
            }

            return $this->bstream->size();

        } catch (EspError $e) {
            return -1;
        }
    }
}

class qr_Input
{

    public $items;

    private $version;
    private $level;


    public function __construct($version = 0, $level = 0)
    {
        if ($version < 0 || $version > 40 || $level > 3) {
            throw new EspError('Invalid version no');
        }

        $this->version = $version;
        $this->level = $level;
    }


    public function getVersion()
    {
        return $this->version;
    }


    public function setVersion($version)
    {
        if ($version < 0 || $version > 40) {
            throw new EspError('Invalid version no');
        }

        $this->version = $version;

        return 0;
    }


    public function getErrorCorrectionLevel()
    {
        return $this->level;
    }


    public function setErrorCorrectionLevel($level)
    {
        if ($level > 3) {
            throw new EspError('Invalid ECLEVEL');
        }

        $this->level = $level;

        return 0;
    }


    public function appendEntry(qr_InputItem $entry)
    {
        $this->items[] = $entry;
    }


    public function append($mode, $size, $data)
    {
        try {
            $entry = new qr_InputItem($mode, $size, $data);
            $this->items[] = $entry;
            return 0;
        } catch (EspError $e) {
            return -1;
        }
    }


    public static function checkModeNum($size, $data)
    {
        for ($i = 0; $i < $size; $i++) {
            if ((ord($data[$i]) < ord('0')) || (ord($data[$i]) > ord('9'))) {
                return false;
            }
        }

        return true;
    }


    public static function estimateBitsModeNum($size)
    {
        $w = (int)$size / 3;
        $bits = $w * 10;

        switch ($size - $w * 3) {
            case 1:
                $bits += 4;
                break;
            case 2:
                $bits += 7;
                break;
            default:
                break;
        }

        return $bits;
    }


    public static $anTable = array(
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,
        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 44, -1, -1, -1, -1, -1,
        -1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
        25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1
    );


    public static function lookAnTable($c)
    {
        return (($c > 127) ? -1 : self::$anTable[$c]);
    }


    public static function checkModeAn($size, $data)
    {
        for ($i = 0; $i < $size; $i++) {
            if (self::lookAnTable(ord($data[$i])) == -1) {
                return false;
            }
        }

        return true;
    }


    public static function estimateBitsModeAn($size)
    {
        $w = (int)($size / 2);
        $bits = $w * 11;

        if ($size & 1) {
            $bits += 6;
        }

        return $bits;
    }


    public static function estimateBitsMode8($size)
    {
        return $size * 8;
    }


    public function estimateBitsModeKanji($size)
    {
        return (int)(($size / 2) * 13);
    }


    public static function checkModeKanji($size, $data)
    {
        if ($size & 1)
            return false;

        for ($i = 0; $i < $size; $i += 2) {
            $val = (ord($data[$i]) << 8) | ord($data[$i + 1]);
            if ($val < 0x8140
                || ($val > 0x9ffc && $val < 0xe040)
                || $val > 0xebbf
            ) {
                return false;
            }
        }

        return true;
    }

    /***********************************************************************
     * Validation
     **********************************************************************/

    public static function check($mode, $size, $data)
    {
        if ($size <= 0)
            return false;

        switch ($mode) {
            case 0:
                return self::checkModeNum($size, $data);
                break;
            case 1:
                return self::checkModeAn($size, $data);
                break;
            case 3:
                return self::checkModeKanji($size, $data);
                break;
            case 2:
                return true;
                break;
            case 4:
                return true;
                break;

            default:
                break;
        }

        return false;
    }


    public function estimateBitStreamSize($version)
    {
        $bits = 0;
        foreach ($this->items as &$item) {
            if ($item instanceof qr_InputItem)
                $bits += $item->estimateBitStreamSizeOfEntry($version);
        }
        return $bits;
    }


    public function estimateVersion()
    {
        $version = 0;
        $prev = 0;
        do {
            $prev = $version;
            $bits = $this->estimateBitStreamSize($prev);
            $version = qr_Spec::getMinimumVersion((int)(($bits + 7) / 8), $this->level);
            if ($version < 0) {
                return -1;
            }
        } while ($version > $prev);

        return $version;
    }


    public static function lengthOfCode($mode, $version, $bits)
    {
        $payload = $bits - 4 - qr_Spec::lengthIndicator($mode, $version);
        switch ($mode) {
            case 0:
                $chunks = (int)($payload / 10);
                $remain = $payload - $chunks * 10;
                $size = $chunks * 3;
                if ($remain >= 7) {
                    $size += 2;
                } else if ($remain >= 4) {
                    $size += 1;
                }
                break;
            case 1:
                $chunks = (int)($payload / 11);
                $remain = $payload - $chunks * 11;
                $size = $chunks * 2;
                if ($remain >= 6)
                    $size++;
                break;
            case 2:
                $size = (int)($payload / 8);
                break;
            case 3:
                $size = (int)(($payload / 13) * 2);
                break;
            case 4:
                $size = (int)($payload / 8);
                break;
            default:
                $size = 0;
                break;
        }

        $maxsize = qr_Spec::maximumWords($mode, $version);
        if ($size < 0) $size = 0;
        if ($size > $maxsize) $size = $maxsize;

        return $size;
    }


    public function createBitStream()
    {
        $total = 0;
        foreach ($this->items as &$item) {
            if ($item instanceof qr_InputItem) $a = 0;
            $bits = $item->encodeBitStream($this->version);
            if ($bits < 0) return -1;
            $total += $bits;
        }
        return $total;
    }


    public function convertData()
    {
        $ver = $this->estimateVersion();
        if ($ver > $this->getVersion()) {
            $this->setVersion($ver);
        }

        for (; ;) {
            $bits = $this->createBitStream();

            if ($bits < 0)
                return -1;

            $ver = qr_Spec::getMinimumVersion((int)(($bits + 7) / 8), $this->level);
            if ($ver < 0) {
                throw new EspError('WRONG VERSION');
            } else if ($ver > $this->getVersion()) {
                $this->setVersion($ver);
            } else {
                break;
            }
        }

        return 0;
    }


    public function appendPaddingBit(qr_BitStream &$bstream)
    {
        $bits = $bstream->size();
        $maxwords = qr_Spec::getDataLength($this->version, $this->level);
        $maxbits = $maxwords * 8;

        if ($maxbits == $bits) {
            return 0;
        }

        if ($maxbits - $bits < 5) {
            return $bstream->appendNum($maxbits - $bits, 0);
        }

        $bits += 4;
        $words = (int)(($bits + 7) / 8);

        $padding = new qr_BitStream();
        $ret = $padding->appendNum($words * 8 - $bits + 4, 0);

        if ($ret < 0)
            return $ret;

        $padlen = $maxwords - $words;

        if ($padlen > 0) {

            $padbuf = array();
            for ($i = 0; $i < $padlen; $i++) {
                $padbuf[$i] = ($i & 1) ? 0x11 : 0xec;
            }

            $ret = $padding->appendBytes($padlen, $padbuf);

            if ($ret < 0)
                return $ret;

        }

        return $bstream->append($padding);
    }


    public function mergeBitStream()
    {
        if ($this->convertData() < 0) {
            return null;
        }

        $bstream = new qr_BitStream();

        foreach ($this->items as &$item) {
            $ret = $bstream->append($item->bstream);
            if ($ret < 0) {
                return null;
            }
        }

        return $bstream;
    }


    public function getBitStream()
    {

        $bstream = $this->mergeBitStream();

        if ($bstream == null) {
            return null;
        }

        $ret = $this->appendPaddingBit($bstream);
        if ($ret < 0) {
            return null;
        }

        return $bstream;
    }


    public function getByteStream()
    {
        $bstream = $this->getBitStream();
        if ($bstream == null) {
            return null;
        }

        return $bstream->toByte();
    }
}

class qr_BitStream
{

    public $data = array();


    public function size()
    {
        return count($this->data);
    }


    public function allocate($setLength)
    {
        $this->data = array_fill(0, $setLength, 0);
        return 0;
    }


    public static function newFromNum($bits, $num)
    {
        $bstream = new qr_BitStream();
        $bstream->allocate($bits);

        $mask = 1 << ($bits - 1);
        for ($i = 0; $i < $bits; $i++) {
            if ($num & $mask) {
                $bstream->data[$i] = 1;
            } else {
                $bstream->data[$i] = 0;
            }
            $mask = $mask >> 1;
        }

        return $bstream;
    }


    public static function newFromBytes($size, $data)
    {
        $bstream = new qr_BitStream();
        $bstream->allocate($size * 8);
        $p = 0;

        for ($i = 0; $i < $size; $i++) {
            $mask = 0x80;
            for ($j = 0; $j < 8; $j++) {
                if ($data[$i] & $mask) {
                    $bstream->data[$p] = 1;
                } else {
                    $bstream->data[$p] = 0;
                }
                $p++;
                $mask = $mask >> 1;
            }
        }

        return $bstream;
    }


    public function append(qr_BitStream $arg)
    {
        if (is_null($arg)) {
            return -1;
        }

        if ($arg->size() == 0) {
            return 0;
        }

        if ($this->size() == 0) {
            $this->data = $arg->data;
            return 0;
        }

        $this->data = array_values(array_merge($this->data, $arg->data));

        return 0;
    }


    public function appendNum($bits, $num)
    {
        if ($bits == 0)
            return 0;

        $b = qr_BitStream::newFromNum($bits, $num);

        if (is_null($b))
            return -1;

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }


    public function appendBytes($size, $data)
    {
        if ($size == 0)
            return 0;

        $b = qr_BitStream::newFromBytes($size, $data);

        if (is_null($b))
            return -1;

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }


    public function toByte()
    {

        $size = $this->size();

        if ($size == 0) {
            return array();
        }

        $data = array_fill(0, (int)(($size + 7) / 8), 0);
        $bytes = (int)($size / 8);

        $p = 0;

        for ($i = 0; $i < $bytes; $i++) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v = $v << 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$i] = $v;
        }

        if ($size & 7) {
            $v = 0;
            for ($j = 0; $j < ($size & 7); $j++) {
                $v = $v << 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$bytes] = $v;
        }

        return $data;
    }

}

class qr_Split
{

    private $dataStr = '';
    private $input;
    private $modeHint;


    public function __construct($dataStr, qr_Input $input, $modeHint)
    {
        $this->dataStr = $dataStr;
        $this->input = $input;
        $this->modeHint = $modeHint;
    }


    private static function isDigitat($str, $pos)
    {
        if ($pos >= strlen($str))
            return false;

        return ((ord($str[$pos]) >= ord('0')) && (ord($str[$pos]) <= ord('9')));
    }


    private static function isalnumat($str, $pos)
    {
        if ($pos >= strlen($str))
            return false;

        return (qr_Input::lookAnTable(ord($str[$pos])) >= 0);
    }


    private function identifyMode($pos)
    {
        if ($pos >= strlen($this->dataStr))
            return -1;

        $c = $this->dataStr[$pos];

        if (self::isDigitat($this->dataStr, $pos)) {
            return 0;
        } else if (self::isalnumat($this->dataStr, $pos)) {
            return 1;
        } else if ($this->modeHint == 3) {

            if ($pos + 1 < strlen($this->dataStr)) {
                $d = $this->dataStr[$pos + 1];
                $word = (ord($c) << 8) | ord($d);
                if (($word >= 0x8140 && $word <= 0x9ffc) || ($word >= 0xe040 && $word <= 0xebbf)) {
                    return 3;
                }
            }
        }

        return 2;
    }


    private function eatNum()
    {
        $ln = qr_Spec::lengthIndicator(0, $this->input->getVersion());

        $p = 0;
        while (self::isDigitat($this->dataStr, $p)) {
            $p++;
        }

        $run = $p;
        $mode = $this->identifyMode($p);

        if ($mode == 2) {
            $dif = qr_Input::estimateBitsModeNum($run) + 4 + $ln
                + qr_Input::estimateBitsMode8(1)         // + 4 + l8
                - qr_Input::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        if ($mode == 1) {
            $dif = qr_Input::estimateBitsModeNum($run) + 4 + $ln
                + qr_Input::estimateBitsModeAn(1)        // + 4 + la
                - qr_Input::estimateBitsModeAn($run + 1);// - 4 - la
            if ($dif > 0) {
                return $this->eatAn();
            }
        }

        $ret = $this->input->append(0, $run, str_split($this->dataStr));
        if ($ret < 0)
            return -1;

        return $run;
    }


    private function eatAn()
    {
        $la = qr_Spec::lengthIndicator(1, $this->input->getVersion());
        $ln = qr_Spec::lengthIndicator(0, $this->input->getVersion());
        $run = 0;
        while (self::isalnumat($this->dataStr, $run)) {
            if (self::isDigitat($this->dataStr, $run)) {
                $q = $run;
                while (self::isDigitat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = qr_Input::estimateBitsModeAn($run) // + 4 + la
                    + qr_Input::estimateBitsModeNum($q - $run) + 4 + $ln
                    - qr_Input::estimateBitsModeAn($q); // - 4 - la

                if ($dif < 0) {
                    break;
                } else {
                    $run = $q;
                }
            } else {
                $run++;
            }
        }
        if (!self::isalnumat($this->dataStr, $run)) {
            $dif = qr_Input::estimateBitsModeAn($run) + 4 + $la
                + qr_Input::estimateBitsMode8(1) // + 4 + l8
                - qr_Input::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        $ret = $this->input->append(1, $run, str_split($this->dataStr));
        return $ret < 0 ? -1 : $run;
    }


    private function eatKanji()
    {
        $p = 0;
        while ($this->identifyMode($p) == 3) {
            $p += 2;
        }
        $ret = $this->input->append(3, $p, str_split($this->dataStr));
        return $ret < 0 ? -1 : $ret;
    }


    private function eat8()
    {
        $la = qr_Spec::lengthIndicator(1, $this->input->getVersion());
        $ln = qr_Spec::lengthIndicator(0, $this->input->getVersion());

        $p = 1;
        $dataStrLen = strlen($this->dataStr);

        while ($p < $dataStrLen) {

            $mode = $this->identifyMode($p);
            if ($mode == 3) {
                break;
            }
            if ($mode == 0) {
                $q = $p;
                while (self::isDigitat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = qr_Input::estimateBitsMode8($p) // + 4 + l8
                    + qr_Input::estimateBitsModeNum($q - $p) + 4 + $ln
                    - qr_Input::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else if ($mode == 1) {
                $q = $p;
                while (self::isalnumat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = qr_Input::estimateBitsMode8($p)  // + 4 + l8
                    + qr_Input::estimateBitsModeAn($q - $p) + 4 + $la
                    - qr_Input::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }

        $run = $p;
        $ret = $this->input->append(2, $run, str_split($this->dataStr));

        if ($ret < 0)
            return -1;

        return $run;
    }


    public function splitString()
    {
        while (strlen($this->dataStr) > 0) {
            if ($this->dataStr == '')
                return 0;

            $mode = $this->identifyMode(0);

            switch ($mode) {
                case 0:
                    $length = $this->eatNum();
                    break;
                case 1:
                    $length = $this->eatAn();
                    break;
                case 3:
                    if ($mode == 3)
                        $length = $this->eatKanji();
                    else    $length = $this->eat8();
                    break;
                default:
                    $length = $this->eat8();
                    break;

            }

            if ($length == 0) return 0;
            if ($length < 0) return -1;
            $this->dataStr = substr($this->dataStr, $length);
        }
        return 1;
    }


    public function toUpper()
    {
        $stringLen = strlen($this->dataStr);
        $p = 0;

        while ($p < $stringLen) {
            $mode = self::identifyMode(substr($this->dataStr, $p, $this->modeHint));
            if ($mode == 3) {
                $p += 2;
            } else {
                if (ord($this->dataStr[$p]) >= ord('a') && ord($this->dataStr[$p]) <= ord('z')) {
                    $this->dataStr[$p] = chr(ord($this->dataStr[$p]) - 32);
                }
                $p++;
            }
        }

        return $this->dataStr;
    }


}

class qr_RsItem
{

    public $mm;                  // Bits per symbol
    public $nn;                  // Symbols per block (= (1 << mm)-1)
    public $alpha_to = array();  // log lookup table
    public $index_of = array();  // Antilog lookup table
    public $genpoly = array();   // Generator polynomial
    public $nroots;              // Number of generator roots = number of parity symbols
    public $fcr;                 // First consecutive root, index form
    public $prim;                // Primitive element, index form
    public $iprim;               // prim-th root of 1, index form
    public $pad;                 // Padding bytes in shortened block
    public $gfpoly;


    public function modnn($x)
    {
        while ($x >= $this->nn) {
            $x -= $this->nn;
            $x = ($x >> $this->mm) + ($x & $this->nn);
        }

        return $x;
    }


    public static function init_rs_char($symsize, $gfpoly, $fcr, $prim, $nroots, $pad)
    {
        // Common code for intializing a Reed-Solomon control block (char or int symbols)
        // Copyright 2004 Phil Karn, KA9Q
        // May be used under the terms of the GNU Lesser General Public License (LGPL)

        $rs = null;

        // Check parameter ranges
        if ($symsize < 0 || $symsize > 8) return $rs;
        if ($fcr < 0 || $fcr >= (1 << $symsize)) return $rs;
        if ($prim <= 0 || $prim >= (1 << $symsize)) return $rs;
        if ($nroots < 0 || $nroots >= (1 << $symsize)) return $rs; // Can't have more roots than symbol values!
        if ($pad < 0 || $pad >= ((1 << $symsize) - 1 - $nroots)) return $rs; // Too much padding

        $rs = new qr_RsItem();
        $rs->mm = $symsize;
        $rs->nn = (1 << $symsize) - 1;
        $rs->pad = $pad;

        $rs->alpha_to = array_fill(0, $rs->nn + 1, 0);
        $rs->index_of = array_fill(0, $rs->nn + 1, 0);

        // PHP style macro replacement ;)
        $NN =& $rs->nn;
        $A0 =& $NN;

        // Generate Galois field lookup tables
        $rs->index_of[0] = $A0; // log(zero) = -inf
        $rs->alpha_to[$A0] = 0; // alpha**-inf = 0
        $sr = 1;

        for ($i = 0; $i < $rs->nn; $i++) {
            $rs->index_of[$sr] = $i;
            $rs->alpha_to[$i] = $sr;
            $sr <<= 1;
            if ($sr & (1 << $symsize)) {
                $sr ^= $gfpoly;
            }
            $sr &= $rs->nn;
        }

        if ($sr != 1) {
            // field generator polynomial is not primitive!
            $rs = NULL;
            return $rs;
        }

        /* Form RS code generator polynomial from its roots */
        $rs->genpoly = array_fill(0, $nroots + 1, 0);

        $rs->fcr = $fcr;
        $rs->prim = $prim;
        $rs->nroots = $nroots;
        $rs->gfpoly = $gfpoly;

        /* Find prim-th root of 1, used in decoding */
        for ($iprim = 1; ($iprim % $prim) != 0; $iprim += $rs->nn)
            ; // intentional empty-body loop!

        $rs->iprim = (int)($iprim / $prim);
        $rs->genpoly[0] = 1;

        for ($i = 0, $root = $fcr * $prim; $i < $nroots; $i++, $root += $prim) {
            $rs->genpoly[$i + 1] = 1;

            // Multiply rs->genpoly[] by  @**(root + x)
            for ($j = $i; $j > 0; $j--) {
                if ($rs->genpoly[$j] != 0) {
                    $rs->genpoly[$j] = $rs->genpoly[$j - 1] ^ $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[$j]] + $root)];
                } else {
                    $rs->genpoly[$j] = $rs->genpoly[$j - 1];
                }
            }
            // rs->genpoly[0] can never be zero
            $rs->genpoly[0] = $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genpoly[0]] + $root)];
        }

        // convert rs->genpoly[] to index form for quicker encoding
        for ($i = 0; $i <= $nroots; $i++)
            $rs->genpoly[$i] = $rs->index_of[$rs->genpoly[$i]];

        return $rs;
    }


    public function encode_rs_char($data, &$parity)
    {
        $MM =& $this->mm;
        $NN =& $this->nn;
        $ALPHA_TO =& $this->alpha_to;
        $INDEX_OF =& $this->index_of;
        $GENPOLY =& $this->genpoly;
        $NROOTS =& $this->nroots;
        $FCR =& $this->fcr;
        $PRIM =& $this->prim;
        $IPRIM =& $this->iprim;
        $PAD =& $this->pad;
        $A0 =& $NN;

        $parity = array_fill(0, $NROOTS, 0);

        for ($i = 0; $i < ($NN - $NROOTS - $PAD); $i++) {

            $feedback = $INDEX_OF[$data[$i] ^ $parity[0]];
            if ($feedback != $A0) {
                // feedback term is non-zero

                // This line is unnecessary when GENPOLY[NROOTS] is unity, as it must
                // always be for the polynomials constructed by init_rs()
                $feedback = $this->modnn($NN - $GENPOLY[$NROOTS] + $feedback);

                for ($j = 1; $j < $NROOTS; $j++) {
                    $parity[$j] ^= $ALPHA_TO[$this->modnn($feedback + $GENPOLY[$NROOTS - $j])];
                }
            }

            // Shift
            array_shift($parity);
            if ($feedback != $A0) {
                array_push($parity, $ALPHA_TO[$this->modnn($feedback + $GENPOLY[0])]);
            } else {
                array_push($parity, 0);
            }
        }
    }
}

class qr_Rs
{

    public static $items = array();


    public static function init_rs($symsize, $gfpoly, $fcr, $prim, $nroots, $pad)
    {
        foreach (self::$items as &$rs) {
            if ($rs->pad != $pad) continue;
            if ($rs->nroots != $nroots) continue;
            if ($rs->mm != $symsize) continue;
            if ($rs->gfpoly != $gfpoly) continue;
            if ($rs->fcr != $fcr) continue;
            if ($rs->prim != $prim) continue;

            return $rs;
        }

        $rs = qr_RsItem::init_rs_char($symsize, $gfpoly, $fcr, $prim, $nroots, $pad);
        array_unshift(self::$items, $rs);

        return $rs;
    }
}

class qr_Mask
{
    private function writeFormatInformation($width, &$frame, $mask, $level)
    {
        $blacks = 0;
        $format = qr_Spec::getFormatInfo($mask, $level);

        for ($i = 0; $i < 8; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }

            $frame[8][$width - 1 - $i] = chr($v);
            if ($i < 6) {
                $frame[$i][8] = chr($v);
            } else {
                $frame[$i + 1][8] = chr($v);
            }
            $format = $format >> 1;
        }

        for ($i = 0; $i < 7; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }

            $frame[$width - 7 + $i][8] = chr($v);
            if ($i == 0) {
                $frame[8][7] = chr($v);
            } else {
                $frame[8][6 - $i] = chr($v);
            }

            $format = $format >> 1;
        }

        return $blacks;
    }

    private static function mask($i, $x, $y)
    {
        switch ($i) {
            case 0:
                return ($x + $y) & 1;
                break;
            case 1:
                return ($y & 1);
                break;
            case 2:
                return ($x % 3);
                break;
            case 3:
                return ($x + $y) % 3;
                break;
            case 4:
                return (((int)($y / 2)) + ((int)($x / 3))) & 1;
                break;
            case 5:
                return (($x * $y) & 1) + ($x * $y) % 3;
                break;
            case 6:
                return ((($x * $y) & 1) + ($x * $y) % 3) & 1;
                break;
            case 7:
                return ((($x * $y) % 3) + (($x + $y) & 1)) & 1;
                break;
            default:
                return 0;
        }
    }


    private function generateMaskNo($maskNo, $width, $frame)
    {
        $bitMask = array_fill(0, $width, array_fill(0, $width, 0));
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (ord($frame[$y][$x]) & 0x80) {
                    $bitMask[$y][$x] = 0;
                } else {
                    $maskFunc = self::mask($maskNo, $x, $y);
                    $bitMask[$y][$x] = ($maskFunc == 0) ? 1 : 0;
                }
            }
        }
        return $bitMask;
    }


    private function makeMaskNo($maskNo, $width, $s, &$d)
    {
        $bitMask = $this->generateMaskNo($maskNo, $width, $s);
        $d = $s;
        $b = 0;
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($bitMask[$y][$x] == 1) {
                    $d[$y][$x] = chr(ord($s[$y][$x]) ^ (int)$bitMask[$y][$x]);
                }
                $b += (int)(ord($d[$y][$x]) & 1);
            }
        }
        return $b;
    }


    public function makeMask($width, $frame, $maskNo, $level)
    {
        $masked = array_fill(0, $width, str_repeat("\0", $width));
        $this->makeMaskNo($maskNo, $width, $frame, $masked);
        $this->writeFormatInformation($width, $masked, $maskNo, $level);
        return $masked;
    }


}

class qr_RsBlock
{
    public $dataLength;
    public $data = array();
    public $eccLength;
    public $ecc = array();

    public function __construct($dl, $data, $el, &$ecc, qr_RsItem $rs)
    {
        $rs->encode_rs_char($data, $ecc);

        $this->dataLength = $dl;
        $this->data = $data;
        $this->eccLength = $el;
        $this->ecc = $ecc;
    }
}

class qr_RawCode
{
    public $version;
    public $datacode = array();
    public $ecccode = array();
    public $blocks;
    public $rsblocks = array(); //of RSblock
    public $count;
    public $dataLength;
    public $eccLength;
    public $b1;


    public function __construct(qr_Input $input)
    {
        $spec = array(0, 0, 0, 0, 0);

        $this->datacode = $input->getByteStream();
        if (is_null($this->datacode)) {
            throw new EspError('null imput string');
        }

        qr_Spec::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

        $this->version = $input->getVersion();
        $this->b1 = qr_Spec::rsBlockNum1($spec);
        $this->dataLength = qr_Spec::rsDataLength($spec);
        $this->eccLength = qr_Spec::rsEccLength($spec);
        $this->ecccode = array_fill(0, $this->eccLength, 0);
        $this->blocks = qr_Spec::rsBlockNum($spec);

        $ret = $this->init($spec);
        if ($ret < 0) {
            throw new EspError('block alloc error');
        }

        $this->count = 0;
    }


    public function init(array $spec)
    {
        $dl = qr_Spec::rsDataCodes1($spec);
        $el = qr_Spec::rsEccCodes1($spec);
        $rs = qr_Rs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);


        $blockNo = 0;
        $dataPos = 0;
        $eccPos = 0;
        for ($i = 0; $i < qr_Spec::rsBlockNum1($spec); $i++) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new qr_RsBlock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        if (qr_Spec::rsBlockNum2($spec) == 0)
            return 0;

        $dl = qr_Spec::rsDataCodes2($spec);
        $el = qr_Spec::rsEccCodes2($spec);
        $rs = qr_Rs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);

        if ($rs == NULL) return -1;

        for ($i = 0; $i < qr_Spec::rsBlockNum2($spec); $i++) {
            $ecc = array_slice($this->ecccode, $eccPos);
            $this->rsblocks[$blockNo] = new qr_RsBlock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $eccPos), $ecc);

            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        return 0;
    }


    public function getCode()
    {
        if ($this->count < $this->dataLength) {
            $row = $this->count % $this->blocks;
            $col = $this->count / $this->blocks;
            if ($col >= $this->rsblocks[0]->dataLength) {
                $row += $this->b1;
            }
            $ret = $this->rsblocks[$row]->data[$col];
        } else if ($this->count < $this->dataLength + $this->eccLength) {
            $row = ($this->count - $this->dataLength) % $this->blocks;
            $col = ($this->count - $this->dataLength) / $this->blocks;
            $ret = $this->rsblocks[$row]->ecc[$col];
        } else {
            return 0;
        }
        $this->count++;

        return $ret;
    }
}

class qr_FrameFiller
{

    public $width;
    public $frame;
    public $x;
    public $y;
    public $dir;
    public $bit;


    public function __construct($width, &$frame)
    {
        $this->width = $width;
        $this->frame = $frame;
        $this->x = $width - 1;
        $this->y = $width - 1;
        $this->dir = -1;
        $this->bit = -1;
    }


    public function setFrameAt($at, $val)
    {
        $this->frame[$at['y']][$at['x']] = chr($val);
    }


    public function nextXY()
    {
        do {

            if ($this->bit == -1) {
                $this->bit = 0;
                return array('x' => $this->x, 'y' => $this->y);
            }

            $x = $this->x;
            $y = $this->y;
            $w = $this->width;

            if ($this->bit == 0) {
                $x--;
                $this->bit++;
            } else {
                $x++;
                $y += $this->dir;
                $this->bit--;
            }

            if ($this->dir < 0) {
                if ($y < 0) {
                    $y = 0;
                    $x -= 2;
                    $this->dir = 1;
                    if ($x == 6) {
                        $x--;
                        $y = 9;
                    }
                }
            } else {
                if ($y == $w) {
                    $y = $w - 1;
                    $x -= 2;
                    $this->dir = -1;
                    if ($x == 6) {
                        $x--;
                        $y -= 8;
                    }
                }
            }
            if ($x < 0 || $y < 0) return null;

            $this->x = $x;
            $this->y = $y;

        } while (ord($this->frame[$y][$x]) & 0x80);

        return array('x' => $x, 'y' => $y);
    }

}

class qr_Encode
{
    private $casesensitive = true;//区分大小写
    private $version = 0;
    private $hint = 2;

    /**
     * @param $option
     * @return resource
     */
    public function create(&$option)
    {
        $array = $this->encode($option['text'], $option['level']);
        $QR_PNG_MAXIMUM_SIZE = 1024;//最大宽度
        $maxSize = (int)($QR_PNG_MAXIMUM_SIZE / (count($array) + 2 * $option['margin']));
        $pixelPerPoint = min(max(1, $option['size']), $maxSize);
        return (new qr_Image)->image($array, $pixelPerPoint, $option);
    }


    public function encode($text, $level = 0)
    {
        $data = self::encodeString($text, $this->version, $level, $this->hint, $this->casesensitive);
        return self::binarize($data);
    }


    private static function encodeString($string, $version, $level, $hint, $casesensitive)
    {
        if (is_null($string) || $string == '\0' || $string == '') {
            throw new EspError('empty string');
        }
        if ($hint != 2 && $hint != 3) {
            throw new EspError('bad hint');
        }

        $input = new qr_Input($version, $level);
        if ($input == NULL) return NULL;

        $split = new qr_Split($string, $input, $hint);
        if (!$casesensitive) $split->toUpper();//不区分大小写
        $ret = $split->splitString();

        if ($ret < 0) {
            return NULL;
        }

        return self::encodeMask($input, -1);
    }


    private static function encodeMask(qr_Input $input, $mask)
    {
        if ($input->getVersion() < 0 || $input->getVersion() > 40) {
            throw new EspError('wrong version');
        }
        if ($input->getErrorCorrectionLevel() > 3) {
            throw new EspError('wrong level');
        }

        $raw = new qr_RawCode($input);
        $version = $input->getVersion();
        $width = qr_Spec::getWidth($version);
        $frame = qr_Spec::newFrame($version);

        $filler = new qr_FrameFiller($width, $frame);
        if (is_null($filler)) {
            return NULL;
        }

        // inteleaved data and ecc codes
        for ($i = 0; $i < $raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;
            for ($j = 0; $j < 8; $j++) {
                $addr = $filler->nextXY();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }
        unset($raw);
        $j = qr_Spec::getRemainder($version);
        for ($i = 0; $i < $j; $i++) {
            $addr = $filler->nextXY();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);

        $maskObj = new qr_Mask();
        $mask = $mask >= 0 ? $mask : 2;

        return $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
    }


    //------进行二值化处理----------------------------------------------------------------
    private static function binarize($frame)
    {
        $len = count($frame);
        foreach ($frame as &$frameLine) {
            for ($i = 0; $i < $len; $i++) {
                $frameLine[$i] = (ord($frameLine[$i]) & 1) ? '1' : '0';
            }
        }
        return $frame;
    }

}

class qr_Image
{
    /**
     * @param array $frame
     * @param int $pixelPerPoint
     * @param $option
     * @return resource
     */
    public function image(array $frame, $pixelPerPoint = 4, $option)
    {
        $h = count($frame);
        $w = strlen($frame[0]);

        $imgW = $w + 2 * $option['margin'];//在1像素时的宽度
        $imgH = $h + 2 * $option['margin'];

        if ($option['width'] === 0) {
            $width = $imgW * $pixelPerPoint;//乘以实际像数后的宽度
            $height = $imgH * $pixelPerPoint;
        } else {//指定了大小
            $width = $height = $option['width'];
            $pixelPerPoint = $width / $imgW;
        }

        if (preg_match('/^([a-z]+)|(\#[a-f0-9]{3})|(\#[a-f0-9]{6})$/i', $option['background'])) {
            $resource_im = \imagecreate($imgW, $imgH);
            $bgColor = Gd::createColor($resource_im, $option['background']);//二维码的背景色
            \imagefill($resource_im, 0, 0, $bgColor);//填充背景色
        } else {
            $resource_im = Gd::createIM($option['background']);
        }

        //最成最终二维码的尺寸：每点为1像素时的宽度，乘设定的每个像素的实际宽度
        //不要用imagecreatetruecolor，否则后面抽除颜色时有问题
        $base_im = \imagecreate($width, $height);

        //二维码的主色，若主色是图片，则这儿得到的是#000的黑色
        $qrColor = Gd::createColor($resource_im, $option['color']);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($frame[$y][$x] == '1') {
                    \imagesetpixel($resource_im, $x + $option['margin'], $y + $option['margin'], $qrColor);
                }
            }
        }

        //把刚才生成的二维码放大，并放到实际大小的二维码上去
        \imagecopyresampled($base_im, $resource_im, 0, 0, 0, 0, $width, $height, $imgW, $imgH);
        \imagedestroy($resource_im);


        //用图片做前景色
        if (is_file($option['color'])) {
            //先把图片复制到空白容器里去
            $IM = \imagecreatetruecolor($width, $height);//用真彩色
            $info = \getimagesize($option['color']);
            $PM = Gd::createIM($option['color'], $info[2]);

            //原图写入临时容器，缩放
            \imagecopyresampled($IM, $PM, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            \imagedestroy($PM);

            //然后把前面生成的二维码，前景色部分扣掉
            \imagecolortransparent($base_im, $qrColor);

            //最后把扣掉的二维码合并到图片的容器里
            \imagecopyresampled($IM, $base_im, 0, 0, 0, 0, $width, $height, $width, $height);
            \imagedestroy($base_im);
            $base_im = $IM;
        }

        //加LOGO
        if (!!$option['logo'] and $option['level'] > 0 and is_file($option['logo'])) {
            $info = \getimagesize($option['logo']);
            if ($info[0] > $info[1]) {//长方形
                $logoWidth = $info[1];
            } else {
                $logoWidth = $info[0];
            }

            $logoWH = $width * 0.2;//计算LOGO部分的尺寸，含边框，即：整体二维码的五分之一
            $logoXY = ($width - $logoWH) / 2;//计算LOGO开始的XY点
            $bgWidth = $logoWidth * 2;

            //LOGO外部留空像素
            $lgBorder = $pixelPerPoint * 0.5;

            //圆角半径
            $radius = $bgWidth * 0.15;

            //生成圆角的背景
            $bgIM = Gd::createRectangle($bgWidth, $bgWidth, $option['logo_border'], $radius);

            //将背景写到图片上
            \imagecopyresampled($base_im, $bgIM, $logoXY - $lgBorder, $logoXY - $lgBorder, 0, 0, $logoWH + $lgBorder * 2, $logoWH + $lgBorder * 2, $bgWidth, $bgWidth);

            //创建一个圆角遮罩层
            $filter = Gd::createCircle($logoWidth, $logoWidth, $option['logo_border'], $radius * 0.5);

            //将圆角遮罩层合并到LOGO上
            $logoIM = Gd::createIM($option['logo'], $info[2]);
            \imagecopyresampled($logoIM, $filter, 0, 0, 0, 0, $logoWidth, $logoWidth, $logoWidth, $logoWidth);

            //将LOGO写到图上
            \imagecopyresampled($base_im, $logoIM, $logoXY, $logoXY, 0, 0, $logoWH, $logoWH, $logoWidth, $logoWidth);


            \imagedestroy($logoIM);
            \imagedestroy($filter);
            \imagedestroy($bgIM);
        }

        //加底图
        if (!!$option['parent'] and is_file($option['parent'])) {
            $sInfo = \getimagesize($option['parent']);
            $shIM = Gd::createIM($option['parent'], $sInfo[2]);

            if ($option['width'] === 0) {
                $width = $imgW * $pixelPerPoint;//乘以实际像数后的宽度
                $height = $imgH * $pixelPerPoint;
            } else {//指定了大小
                $width = $height = $option['width'];
            }

            $x = (isset($option['parent_x']) and is_int($option['parent_x'])) ? $option['parent_x'] : ($sInfo[0] - $width) / 2;
            $y = (isset($option['parent_y']) and is_int($option['parent_y'])) ? $option['parent_y'] : ($sInfo[1] - $width) / 2;

            //加阴影
            if (!!$option['shadow']) {
                $shadow_im = \imagecreate($width, $height);
                $shadow_color = Gd::createColor($shadow_im, $option['shadow'], $option['shadow_alpha']);
                \imagefill($shadow_im, 0, 0, $shadow_color);
                $shadow_x = \intval($option['shadow_x']);
                $shadow_y = \intval($option['shadow_y']);
                \imagecopyresampled($shIM, $shadow_im, $x + $shadow_x, $y + $shadow_y, 0, 0, $width, $height, $width, $height);
            }

            \imagecopyresampled($shIM, $base_im, $x, $y, 0, 0, $width, $height, $width, $height);
            $base_im = $shIM;


        }


        return $base_im;
    }


}
