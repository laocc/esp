<?php

namespace esp\library\img;

use esp\error\EspError;
use esp\library\img\code1\BCG_FontFile;
use esp\library\img\code1\BCG_code128;
use esp\library\img\code1\BCG_Color;
use esp\library\img\code1\BCG_FontPhp;

class Code1 extends BaseImg
{
    public function __construct(array $option)
    {

    }

    /**
     * @param $option
     * @return mixed
     */
    public function create($option)
    {
        if (!is_array($option)) {
            $option = ['value' => $option];
        }
        $code = Array();
        $code['code'] = microtime(true);        //条码内容
        $code['font'] = null;       //字体，若不指定，则用PHP默认字体
        $code['size'] = 10;         //字体大小
        $code['split'] = 4;         //条码值分组，每组字符个数，=0不分，=null不显示条码值
        $code['pixel'] = 3;         //分辨率即每个点显示的像素，建议3-5
        $code['height'] = 20;       //条码部分高，实际像素为此值乘pixel
        $code['style'] = null;      //条码格式，可选：A,B,C,或null，若为null则等同于C，这基本不需要指定，非C的条码，还不知道用在什么地方
        $code['root'] = getcwd();    //保存文件目录，不含在URL中部分
        $code['path'] = 'code1/';   //含在URL部分
        $code['save'] = 0;          //0：只显示，1：只保存，2：即显示也保存
        $code['filename'] = null;      //不带此参，或此参为false值，则随机产生

        $option += $code;

        $option['code'] = strval($option['code']);
        $option['root'] = rtrim($option['root'], '/');
        $option['path'] = '/' . trim($option['path'], '/') . '/';

        if (!preg_match('/^[\x20\w\!\@\#\$\%\^\&\*\(\)\_\+\`\-\=\[\]\{\}\;\'\\\:\"\|\,\.\/\<\>\?]+$/', $option['code'])) {
            throw new EspError("条形码只能是英文、数字及半角符号组成");
        }

        if (!!$option['split']) {
            $option['label'] = '* ' . implode(' ', str_split($option['code'], intval($option['split']))) . ' *';
        } elseif ($option['split'] === null) {
            $option['label'] = null;
        } else {
            $option['label'] = $option['code'];
        }

        $font = (!!$option['font']) ?
            (new BCG_FontFile($option['font'], intval($option['size']))) :
            (new BCG_FontPhp($option['size']));

        $color = new BCG_Color(0, 0, 0);
        $background = new BCG_Color(255, 255, 255);

        $file = $this->getFileName($option['save'], $option['root'], $option['path'], $option['filename'], 'png');

        $Obj = new BCG_code128();
        $Obj->setLabel($option['label']);
        $Obj->setStart($option['style']);
        $Obj->setThickness($option['height']);
        $Obj->setScale($option['pixel']);
        $Obj->setBackgroundColor($background);
        $Obj->setForegroundColor($color);
        $Obj->setFont($font);
        $Obj->parse($option['code']);

        $size = $Obj->getDimension(0, 0);
        $width = max(1, $size[0]);
        $height = max(1, $size[1]);
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $background->allocate($im));
        $Obj->draw($im);

        $option = [
            'save' => $option["save"],//0：只显示，1：只保存，2：即显示也保存
            'filename' => $file['filename'],
            'type' => IMAGETYPE_PNG,//文件类型
        ];

        $this->draw($im, $option);
        return $file;
    }
}
