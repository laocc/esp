<?php

namespace esp\library\img;


class Text extends BaseImg
{
    private $Quality = 80;    //JPG默认质量，对所有方法都有效

    public function __construct(string $text, array $option = [])
    {
        $option += [
            'width' => 0,
            'height' => 0,
            'size' => 20,
            'area' => '',
            'x' => null,//xy指文字左下角位置
            'y' => null,
            'quality' => $this->Quality,
            'font' => null,
            'color' => '#000000',
            'background' => '#ffffff',
            'alpha' => 0,//透明度
            'save' => 0,//0：只显示，1：只保存，2：即显示也保存
            'root' => _RUNTIME . '/code/',
            'path' => 'text/',
            'angle' => 0,//每个字的角度
            'vertical' => false,//竖向
            'percent' => 1.5,//字体间距与字号比例
        ];
        $tLen = mb_strlen($text);

        //不指定尺寸，自动计算
        if (!$option['width']) {
            if (!$option['vertical']) {
                $option['width'] = $tLen * $option['size'] * $option['percent'] + $option['size'];
            } else {
                $option['width'] = $option['size'] * $option['percent'] + $option['size'];
            }
        }
        if (!$option['height']) {
            if (!$option['vertical']) {
                $option['height'] = $option['size'] * $option['percent'] + $option['size'];
            } else {
                $option['height'] = $tLen * $option['size'] * $option['percent'] + $option['size'];
            }
        }
        if ($option['x'] === null) {
            //一个间距
            $option['x'] = $option['size'] * ($option['percent'] - 1);
        }

        if ($option['y'] === null) {
            $option['y'] = $option['height'] - ($option['size'] * 0.5);
        }

        $im = imagecreatetruecolor($option['width'], $option['height']);//建立一个画板
        $bg = $this->createColor($im, $option['background'], $option['alpha']);//拾取一个完全透明的颜色
        imagefill($im, 0, 0, $bg);
        imagealphablending($im, true);
        $color = $this->createColor($im, $option['color']);

        for ($i = 0; $i < $tLen; $i++) {
            $cn = mb_substr($text, $i, 1, "utf8");
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

        $this->option = $option;
        $this->im = $im;
    }

    /**
     * 写文字到图片，这不是写文字水印，是直接将几个字生成一个图片
     */
    public function display()
    {
        $this->file = $this->getFileName($this->option['save'], $this->option['root'], $this->option['path'], 'png');

        $gdOption = [
            'save' => $this->option['save'],//0：只显示，1：只保存，2：即显示也保存
            'filename' => $this->file['filename'],
            'type' => IMAGETYPE_PNG,//文件类型
            'quality' => $this->option['quality'],
        ];

        $this->draw($this->im, $gdOption);
    }

    public function filename()
    {
        return $this->file;
    }


    /**
     * 计算文字位置
     * @param $iw
     * @param $ih
     * @param $size
     * @param $font
     * @param $txt
     * @return array
     */
    private function get_text_xy($iw, $ih, &$size, $font, $txt)
    {
        $temp = imagettfbbox(ceil($size), 0, $font, $txt);//取得使用 TrueType 字体的文本的范围
        var_dump($temp);
        $w = ($temp[2] - $temp[0]);//文字宽
        $h = ($temp[1] - $temp[7]); //文字高
        unset($temp);
        if ($w * 1.1 > $iw) {
            $size -= 2;
            return $this->get_text_xy($iw, $ih, $size, $font, $txt);
        }
        $x = ($iw - $w) / 2;
        $y = $ih - ($ih - $h) / 2 - 10;
        return [$x, $y];
    }
}