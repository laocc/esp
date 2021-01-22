<?php

namespace esp\library\gd;

use esp\error\EspError;
use esp\library\gd\ext\Gd;

/**
 * 普通图片转换ico。
 *
 * Class Icon
 * @package gd
 */
class Icon
{

    public static function create_default($file, $code = null)
    {
        if ($code === null) $code = 'data:image/bmp;base64,AAAQEP';//AAAQEP
        self::create($code, 32);
//        header('Content-type:text/x-icon', true);
//        echo $code;
        return null;
    }

    /**
     * @param string $img 资源，可以是一个图片文件，或是文件流
     * @param int $size 要生成的尺寸
     * @param null $icon_file
     * @return bool|null|string
     */
    public static function create($img, $size = 32, $icon_file = null)
    {
        if (!in_array($size, [16, 32, 48, 64, 128])) $size = 32;
        $is_file = stripos('$img', 'data:image') !== 0;

        if ($is_file) {
            $info = getimagesize($img);
            if (!$info) return false;
            $icon_file = $icon_file ?: "{$img}_{$size}.ico";
            $file_image = Gd::createIM($img, $info[2]);

            if (!is_resource($file_image)) return false;
            $gd_image = imagecreatetruecolor($size, $size);
            imagecopyresampled($gd_image, $file_image, 0, 0, 0, 0, $size, $size, $info[0], $info[1]);
            $im = self::im_data($gd_image, $size);
            file_put_contents($icon_file, $im);

        } else {
            if (!$icon_file) throw new EspError('文件流格式生成ICON时须指定要保存的文件名');
            $file_image = Gd::createIM($img, false);
            $im = self::im_data($file_image);
            file_put_contents($icon_file, $im);
        }
        return $icon_file;
    }

    private static function im_data($gd_image, $size = 0)
    {
        $icAndMask = Array();
        $icXOR = $icAND = '';

        $le2s = function ($number, $byte = 1) {
            $intString = '';
            while ($number > 0) {
                $intString .= chr($number & 255);
                $number >>= 8;
            }
            return str_pad($intString, $byte, "\x00", STR_PAD_RIGHT);
        };
        $gpc = function (&$img, $x, $y) {
            if (!is_resource($img)) {
                return false;
            }
            return @imagecolorsforindex($img, @imagecolorat($img, $x, $y));
        };

        if ($size === 0) $size = imagesx($gd_image);
        $bpp = imageistruecolor($gd_image) ? 32 : 24;
        $totalColors = imagecolorstotal($gd_image);
        for ($y = $size - 1; $y >= 0; $y--) {
            $icAndMask[$y] = '';
            for ($x = 0; $x < $size; $x++) {
                $argb = $gpc($gd_image, $x, $y);
                $a = round(255 * ((127 - $argb['alpha']) / 127));
                $r = $argb['red'];
                $g = $argb['green'];
                $b = $argb['blue'];
                if ($bpp == 32) {
                    $icXOR .= chr($b) . chr($g) . chr($r) . chr($a);
                } elseif ($bpp == 24) {
                    $icXOR .= chr($b) . chr($g) . chr($r);
                }
                if ($a < 128) {
                    $icAndMask[$y] .= '1';
                } else {
                    $icAndMask[$y] .= '0';
                }
            }
            while (strlen($icAndMask[$y]) % 32) {
                $icAndMask[$y] .= '0';
            }
        }
        foreach ($icAndMask as $y => &$scanLineMaskBits) {
            for ($i = 0; $i < strlen($scanLineMaskBits); $i += 8) {
                $icAND .= chr(bindec(str_pad(substr($scanLineMaskBits, $i, 8), 8, '0', STR_PAD_LEFT)));
            }
        }
        $dwBytesInRes = 40 + strlen($icXOR) + strlen($icAND);
        $biSizeImage = $size * $size * ($bpp / 8);

        $bfh = '';
        $bfh .= "\x28\x00\x00\x00";
        $bfh .= $le2s($size, 4);
        $bfh .= $le2s($size * 2, 4);
        $bfh .= "\x01\x00";
        $bfh .= chr($bpp);
        $bfh .= str_repeat("\x00", 5);
        $bfh .= $le2s($biSizeImage, 4);
        $bfh .= str_repeat("\x00", 16);
        $iconData = "\x00\x00";
        $iconData .= "\x01\x00";
        $iconData .= $le2s(1, 2);
        $iconData .= chr($size);
        $iconData .= chr($size);
        $iconData .= chr($totalColors);
        $iconData .= "\x00";
        $iconData .= "\x01\x00";
        $iconData .= chr($bpp) . "\x00";
        $iconData .= $le2s($dwBytesInRes, 4);
        $iconData .= $le2s(6 + 16, 4);
        $iconData .= $bfh;
        $iconData .= $icXOR;
        $iconData .= $icAND;
        return $iconData;
    }

}