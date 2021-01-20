<?php

namespace esp\library\img;


abstract class BaseImg
{
    protected $option;
    protected $im;
    protected $file;

    protected $identifiers = [
        'gif' => IMAGETYPE_GIF,
        'jpg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG,
        'swf' => IMAGETYPE_SWF,
        'psd' => IMAGETYPE_PSD,
        'bmp' => IMAGETYPE_BMP,
        'tiff' => IMAGETYPE_TIFF_II,
        'jpc' => IMAGETYPE_JPC,
        'jp2' => IMAGETYPE_JP2,
        'jpf' => IMAGETYPE_JPX,
        'jb2' => IMAGETYPE_JB2,
        'swc' => IMAGETYPE_SWC,
        'aiff' => IMAGETYPE_IFF,
        'wbmp' => IMAGETYPE_WBMP,
        'xbm' => IMAGETYPE_XBM,
        'ico' => IMAGETYPE_ICO,
    ];

    /**
     * @param resource $im
     * @param null $filename
     */
    /**
     * @param $im
     * @param array $option
     * @return bool
     */
    protected function draw($im, array $option)
    {
        $option += [
            'save' => 0,//0：只显示，1：只保存，2：即显示也保存，3：返回GD数据流
            'filename' => null,
            'type' => IMAGETYPE_PNG,//文件类型
            'quality' => 80,
            'version' => isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : '',
        ];
        if (preg_match('/^\d\.\d$/', $option['version'])) {
            $option['version'] = "HTTP/{$option['version']}";
        }

        //保存到文件
        if ($option['save'] === 1 or $option['save'] === 2) {
            switch ($option['type']) {
                case 'gif':
                case IMAGETYPE_GIF:
                    $option['type'] = IMAGETYPE_GIF;
                    imagegif($im, $option['filename']);
                    break;
                case 'jpg':
                case 'jpeg':
                case IMAGETYPE_JPEG:
                    $option['type'] = IMAGETYPE_JPEG;
                    imagejpeg($im, $option['filename'], $option['quality'] ?: 80);
                    break;
                case 'png':
                case IMAGETYPE_PNG:
                    $option['type'] = IMAGETYPE_PNG;
                    imagepng($im, $option['filename']);
                    break;
                default:
                    imagegd2($im, $option['filename']);
            }
        } elseif ($option['save'] === 3) {//返回base64

            //"<img src='data:image/png;base64,{$data}'>";

//            ob_end_clean();//清空所有缓存
            ob_start();//清除前先打开，否则在有些情况下清空缓存会失败
            imagepng($im);
            $data = ob_get_contents();
            ob_end_clean();
            imagedestroy($im);
            if (!empty($data)) {
                return base64_encode($data);
            } else {
                return '';
            }
        }

        //输出
        if (php_sapi_name() !== 'cli' and ($option['save'] === 0 or $option['save'] === 2)) {
            ob_start();//清除前先打开，否则在有些情况下清空缓存会失败
            ob_end_clean();//清空所有缓存
            header("{$option['version']} 200", true, 200);
            header('Content-type:' . image_type_to_mime_type($option['type']), true);
            header('Access-Control-Allow-Origin: *', true);
            header('Create-by: GD', true);
            header('Save-by: ' . $option['save'], true);

            //没有明确是否缓存，或明确了不缓存
            if (!isset($option['cache']) or !$option['cache']) {
                header('Cache-Control:no-cache,must-revalidate,no-store', true);
                header('Pramga: no-cache', true);
                header('Cache-Info: no cache', true);
            }

            switch ($option['type']) {
                case IMAGETYPE_GIF:
                    imagegif($im);
                    break;
                case IMAGETYPE_JPEG:
                    imagejpeg($im, null, $option['quality'] ?: 80);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($im);
                    break;
                default:
                    imagegd2($im);
            }
        }
        imagedestroy($im);
        return true;
    }

    /**
     * 从某个资源中提取颜色
     * $color接受三种形式：
     * 1，一个色值：#000000，或#abc
     * 2，取值范围：[128,256]
     * 3，指定色值：[123,234,125]
     * @param $im
     * @param string $color
     * @param int $alpha
     * @return int
     */
    protected function createColor(&$im, $color = '#000000', $alpha = 0)
    {
        if (is_int($color)) list($color, $alpha) = ['#000000', $color];
        list($R, $G, $B) = $this->getRGBColor($color);
        if ($alpha > 0) {//透明色
            if ($alpha > 100) $alpha = 100;
            $alpha = $alpha * 1.27;
            return imagecolorallocatealpha($im, $R, $G, $B, $alpha);
        } else {
            return imagecolorallocate($im, $R, $G, $B);
        }
    }


    //1 = GIF，2 = JPG，3 = PNG，4 = SWF，5 = PSD，6 = BMP，7 = TIFF(intel byte order)，8 = TIFF(motorola byte order)，
    //9 = JPC，10 = JP2，11 = JPX，12 = JB2，13 = SWC，14 = IFF，15 = WBMP，16 = XBM

    /**
     * 从文件读取资源
     * @param string $pic
     * @param int $type
     * @return null|resource
     */
    protected function createIM($pic, $type = 0)
    {
        if (is_bool($type)) {
            return imagecreatefromstring($pic);
        }
        $type = $type ?: \exif_imagetype($pic);
        switch ($type) {
            case IMAGETYPE_GIF:
                $PM = @imagecreatefromgif($pic);
                break;
            case IMAGETYPE_JPEG:
                $PM = @imagecreatefromjpeg($pic);
                break;
            case IMAGETYPE_PNG:
                $PM = @imagecreatefrompng($pic);
                break;
            case IMAGETYPE_WBMP:
                $PM = @imagecreatefromwbmp($pic);
                break;
            case IMAGETYPE_XBM:
                $PM = @imagecreatefromxbm($pic);
                break;
            case IMAGETYPE_ICO:
                //ICON
                $PM = null;
                break;
            default:
                $PM = $this->createFromImg($pic);
                break;
        }
        return $PM;
    }


    /**
     * 从BMP读取为资源
     * @param string $filename
     * @return null|resource
     */
    protected function createFromImg($filename)
    {
        //打开文件，若出错则退
        if (!$fr = @fopen($filename, "rb")) return null;

        //1 : Chargement des ent tes FICHIER
        $FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($fr, 14));
        if ($FILE['file_type'] != 19778) return null;

        //2 : Chargement des ent tes BMP
        $BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel/Vcompression/Vsize_bitmap/Vhoriz_resolution/Vvert_resolution/Vcolors_used/Vcolors_important', fread($fr, 40));

        $BMP['colors'] = pow(2, $BMP['bits_per_pixel']);
        $BMP['size_bitmap'] = ($BMP['size_bitmap'] === 0) ? $FILE['file_size'] - $FILE['bitmap_offset'] : $BMP['size_bitmap'];
        $BMP['bytes_per_pixel'] = $BMP['bits_per_pixel'] / 8;
        $BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
        $BMP['decal'] = ($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] -= floor($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
        $BMP['decal'] = 4 - (4 * $BMP['decal']);
        $BMP['decal'] = ($BMP['decal'] === 4) ? 0 : $BMP['decal'];

        //3 : Chargement des couleurs de la palette
        $PALETTE = array();
        if ($BMP['colors'] < 16777216) {
            $PALETTE = unpack('V' . $BMP['colors'], fread($fr, $BMP['colors'] * 4));
        }

        //4 : Cr ation de l'image
        $IMG = fread($fr, $BMP['size_bitmap']);
        $VIDE = chr(0);

        $res = imagecreatetruecolor($BMP['width'], $BMP['height']);
        $P = 0;
        $Y = $BMP['height'] - 1;
        while ($Y >= 0) {
            $X = 0;
            while ($X < $BMP['width']) {
                if ($BMP['bits_per_pixel'] === 24)
                    $COLOR = unpack("V", substr($IMG, $P, 3) . $VIDE);
                elseif ($BMP['bits_per_pixel'] === 16) {
                    $COLOR = unpack("n", substr($IMG, $P, 2));
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } elseif ($BMP['bits_per_pixel'] === 8) {
                    $COLOR = unpack("n", $VIDE . substr($IMG, $P, 1));
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } elseif ($BMP['bits_per_pixel'] === 4) {
                    $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                    if (($P * 2) % 2 === 0)
                        $COLOR[1] = ($COLOR[1] >> 4);
                    else
                        $COLOR[1] = ($COLOR[1] & 0x0F);
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } elseif ($BMP['bits_per_pixel'] === 1) {
                    $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                    if (($P * 8) % 8 === 0)
                        $COLOR[1] = $COLOR[1] >> 7;
                    elseif (($P * 8) % 8 === 1)
                        $COLOR[1] = ($COLOR[1] & 0x40) >> 6;
                    elseif (($P * 8) % 8 === 2)
                        $COLOR[1] = ($COLOR[1] & 0x20) >> 5;
                    elseif (($P * 8) % 8 === 3)
                        $COLOR[1] = ($COLOR[1] & 0x10) >> 4;
                    elseif (($P * 8) % 8 === 4)
                        $COLOR[1] = ($COLOR[1] & 0x8) >> 3;
                    elseif (($P * 8) % 8 === 5)
                        $COLOR[1] = ($COLOR[1] & 0x4) >> 2;
                    elseif (($P * 8) % 8 === 6)
                        $COLOR[1] = ($COLOR[1] & 0x2) >> 1;
                    elseif (($P * 8) % 8 === 7)
                        $COLOR[1] = ($COLOR[1] & 0x1);
                    $COLOR[1] = $PALETTE[$COLOR[1] + 1];
                } else
                    return null;
                imagesetpixel($res, $X, $Y, $COLOR[1]);
                $X++;
                $P += $BMP['bytes_per_pixel'];
            }
            $Y--;
            $P += $BMP['decal'];
        }

        fclose($fr); //关闭文件资源
        return $res;
    }


    /**
     * 创建一个空间圆角矩形
     * @param $w
     * @param $h
     * @param $color
     * @return resource
     */
    protected function createCircle($w, $h = 0, $color, $radius)
    {
        $h = $h ?: $w;
        $im = imagecreate($w, $h);
        if (is_string($color)) $color = $this->createColor($im, $color);
        imagefill($im, 0, 0, $color);

        $black = $this->createColor($im, '#000');

        //画四个角的圆弧，各为1/4圆
        imagefilledarc($im, $w - $radius, $h - $radius, $radius * 2, $radius * 2, 0, 90, $black, IMG_ARC_PIE);
        imagefilledarc($im, $radius, $h - $radius, $radius * 2, $radius * 2, 90, 180, $black, IMG_ARC_PIE);
        imagefilledarc($im, $radius, $radius, $radius * 2, $radius * 2, 180, 270, $black, IMG_ARC_PIE);
        imagefilledarc($im, $w - $radius, $radius, $radius * 2, $radius * 2, 270, 0, $black, IMG_ARC_PIE);

        //画两个相交的矩形
        imagefilledpolygon($im, [$radius, 0, $w - $radius, 0, $w - $radius, $h, $radius, $h], 4, $black);
        imagefilledpolygon($im, [0, $radius, $w, $radius, $w, $h - $radius, 0, $h - $radius], 4, $black);

        imagecolortransparent($im, $black);//再抽取掉所有黑色变成透明

        return $im;
    }


    /**
     * 创建一个实心圆角矩形
     * @param $w
     * @param int $h
     * @param $color
     * @param int $radius 倒角半径
     * @param int $border倒角半径
     * @return resource
     */
    protected function createRectangle($w, $h = 0, $color, $radius = 0)
    {
        $h = $h ?: $w;
        $im = imagecreate($w, $h);
        $black = $this->createColor($im, '#00000f');
        imagefill($im, 0, 0, $black);//填成黑色
        imagecolortransparent($im, $black);//再抽取掉所有黑色变成透明

        $color = $this->createColor($im, $color);

        if ($radius === 0) {
            imagefill($im, 0, 0, $color);//填充
            return $im;//不倒角，直接返回
        }

        //画四个角的圆弧，各为1/4圆
        imagefilledarc($im, $w - $radius, $h - $radius, $radius * 2, $radius * 2, 0, 90, $color, IMG_ARC_PIE);
        imagefilledarc($im, $radius, $h - $radius, $radius * 2, $radius * 2, 90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($im, $radius, $radius, $radius * 2, $radius * 2, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($im, $w - $radius, $radius, $radius * 2, $radius * 2, 270, 0, $color, IMG_ARC_PIE);

        //画两个相交的矩形
        imagefilledpolygon($im, [$radius, 0, $w - $radius, 0, $w - $radius, $h, $radius, $h], 4, $color);
        imagefilledpolygon($im, [0, $radius, $w, $radius, $w, $h - $radius, 0, $h - $radius], 4, $color);

        return $im;
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


    /**
     * @param $save
     * @param $root
     * @param $path
     * @param null $name
     * @param string $ext
     * @return array
     */
    protected function getFileName($save, $root, $path, $name = null, $ext = 'png')
    {
        $fileInfo = ['filename' => null];
        if ($save === 1 or $save === 2) {
            if ($name and strlen($name) < 5) list($name, $ext) = [null, $name];
            if (!is_dir($root)) @mkdir($root, 0740, true);
            $fileInfo = [
                'root' => $root,
                'path' => $path,
                'file' => $name ?: (md5(uniqid(mt_rand(), true)) . '.' . ltrim($ext, '.')),
            ];
            if (!is_dir("{$root}{$path}")) @mkdir("{$root}{$path}", 0740, true);
            $fileInfo['filename'] = "{$root}{$path}{$fileInfo['file']}";
        }
        return $fileInfo;
    }


}