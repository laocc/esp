<?php
namespace esp\extend\gd;
use esp\extend\gd\ext\Gd;

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
        self::create($code, $file, 32, false);
//        header('Content-type:text/x-icon', true);
//        echo $code;
        return null;
    }

    public static function create($file_img, $file_save = null, $size = 32, $is_file = true)
    {
        if ($is_file and !is_file($file_img)) return false;
        if (is_int($file_save)) list($file_save, $size) = [null, $file_save];
        if (!in_array($size, [16, 32, 48, 64, 128])) $size = 32;

        if ($is_file) {
            $info = getimagesize($file_img);
            if (!$info) return false;
            $file_save = $file_img . "_{$size}.ico";
            $file_image = Gd::createIM($file_img, $info[2]);

            if (!is_resource($file_image)) return false;
            $gd_image = imagecreatetruecolor($size, $size);
            imagecopyresampled($gd_image, $file_image, 0, 0, 0, 0, $size, $size, $info[0], $info[1]);
            $im = self::im_data($gd_image, $size);
            return file_put_contents($file_save, $im);

        } else {
            if (!$file_save) return false;
            $file_image = Gd::createIM($file_img, false);
            $im = self::im_data($file_image);
            return file_put_contents($file_save, $im);
        }
    }

    private static function im_data($gd_image, $size = 0)
    {
        $icAndMask = [];
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