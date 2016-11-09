<?php
namespace gd;

use \gd\ext\Gd;

class Text
{
    const Quality = 80;    //JPG默认质量，对所有方法都有效

    /**
     * 写文字到图片，这不是写文字水印，是直接将几个字生成一个图片
     * @param string $file
     * @param string $text
     * @param array $option
     */
    public static function create(string $text, array $option = [])
    {
        $dim = [
            'width' => 0,
            'height' => 0,
            'size' => 20,
            'x' => null,
            'y' => null,
            'quality' => self::Quality,
            'font' => null,
            'color' => '#000000',
            'background' => '#ffffff',
            'alpha' => 0,//透明度
            'save' => 0,
            'root' => _ROOT . 'code/',
            'path' => 'text/',
            'angle' => 0,//每个字的角度
            'vertical' => false,//竖向
            'percent' => 1.5,//字体间距与字号比例
        ];
        $option = $option + $dim;
        $cut = str_cut($text);
        $count = count($cut);

        //不指定尺寸，自动计算
        if (!$option['width']) {
            if (!$option['vertical']) {
                $option['width'] = $count * $option['size'] * $option['percent'] + $option['size'];
            } else {
                $option['width'] = $option['size'] * $option['percent'] + $option['size'];
            }
        }
        if (!$option['height']) {
            if (!$option['vertical']) {
                $option['height'] = $option['size'] * $option['percent'] + $option['size'];
            } else {
                $option['height'] = $count * $option['size'] * $option['percent'] + $option['size'];
            }
        }

        if ($option['x'] === null) {
            $option['x'] = $option['size'] * ($option['percent'] - 1);
        }

        if ($option['y'] === null) {
            $option['y'] = $option['height'] - ($option['size'] * 0.5);
        }

        list($file, $filename) = Gd::getFileName($option['save'], $option['root'], $option['path'], 'png');


        $im = imagecreatetruecolor($option['width'], $option['height']);//建立一个画板
        $bg = Gd::createColor($im, $option['background'], $option['alpha'] ?: 100);//拾取一个完全透明的颜色
        imagefill($im, 0, 0, $bg);
        imagealphablending($im, true);
        $color = Gd::createColor($im, $option['color']);
        foreach ($cut as $i => &$cn) {
            imagettftext($im,
                $option['size'],
                $option['angle'],
                $option['x'], $option['y'],
                $color,
                $option['font'],
                $cn);

            if ($option['vertical'])
                $option['y'] += ($option['size'] * $option['percent']);
            else
                $option['x'] += ($option['size'] * $option['percent']);
        }

        imagesavealpha($im, true);//设置保存PNG时保留透明通道信息
        $file = $file + $option;

        $option = [
            'save' => $option['save'],//0：只显示，1：只保存，2：即显示也保存
            'filename' => $filename,
            'type' => IMAGETYPE_PNG,//文件类型
            'quality' => $option['quality'],
        ];

        Gd::draw($im, $option);

        return $file;
    }


    //计算文字位置
    private static function get_text_xy($iw, $ih, &$size, $font, $txt)
    {
        $temp = imagettfbbox(ceil($size), 0, $font, $txt);//取得使用 TrueType 字体的文本的范围
        var_dump($temp);
        $w = ($temp[2] - $temp[0]);//文字宽
        $h = ($temp[1] - $temp[7]); //文字高
        unset($temp);
        if ($w * 1.1 > $iw) {
            $size -= 2;
            return self::get_text_xy($iw, $ih, $size, $font, $txt);
        }
        $x = ($iw - $w) / 2;
        $y = $ih - ($ih - $h) / 2 - 10;
        return [$x, $y];
    }

}