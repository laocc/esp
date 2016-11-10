<?php
namespace esp\extend\gd;
use esp\extend\gd\ext\Gd;

/**
 * 图片处理类：
 * 1，limit：限制图片尺寸；
 * 1，size：修改图片尺寸；
 * 2，convert：转换图片类型；
 * 3，thumbs：访问中自动创建相关缩略图；
 * 4，thumbs_create：创建缩略图；
 * 5，text：生成文字图片；
 * 6，mark：给图片加水印
 *
 * Class Image
 * @package pic
 */
class Image
{
    const Quality = 80;    //JPG默认质量，对所有方法都有效


    static private $backup = [];

    /**
     * 检查文件是否超宽，并设置到指定尺寸
     * @param string $file
     * @param int $maxWidth
     * @return bool
     */
    public static function limit($file, array $size = [0, 0], $backUP = true)
    {
        if (!is_file($file)) return false;
        $info = getimagesize($file);
        $wid = ($size[0] > 0 and $info[0] > $size[0]);
        $hei = ($size[1] > 0 and $info[1] > $size[1]);
        if (!$wid and !$hei) {
            return null;
        } elseif ($hei) {//以高为准
            $height = $size[1];
            $width = $info[0] * ($height / $info[1]);
        } else {        //以宽为准
            $width = $size[0];
            $height = $info[1] * ($width / $info[0]);
        }

        //源文件备份
        if ($backUP) self::backup($file, true);

        //建立临时容器
        $IM = imagecreatetruecolor($width, $height);
        $PM = Gd::createIM($file, $info[2]);

        //原图写入临时容器，缩放
        imagecopyresampled($IM, $PM, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);

        $option = [
            'save' => 1,//0：只显示，1：只保存，2：即显示也保存
            'filename' => $file,
            'type' => $info[2],//文件类型
            'quality' => self::Quality,
        ];

        Gd::draw($IM, $option);
        imagedestroy($PM);
        clearstatcache();
        return true;
    }

    /**
     * 同一文件只备份一次
     * @param $file
     * @param bool $force
     */
    private static function backup($file, $force = false)
    {
        $mdKey = md5($file);
        if (!$force and isset(self::$backup[$mdKey])) return;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (is_file("{$file}.{$ext}")) return;
        copy($file, "{$file}.{$ext}");
        self::$backup[$mdKey] = 1;
    }

    /*
     * 重置图片尺寸
     * @param string $file
     * @param array $reSize
     * @return bool
     */
    public static function size($file, array $reSize = [0, 0], $backUP = true)
    {
        if (!is_file($file)) return false;
        $info = getimagesize($file);
        if (!$info) {
            @unlink($file);
            return false;
        }
        if ($info[0] === $reSize[0] and $info[1] === $reSize[1]) return true;
        list($reWidth, $reHeight) = $reSize;
        if (!$reWidth and !$reHeight) return false;

        //源文件备份
        if ($backUP) self::backup($file);

        if (!$reWidth) {
            $reWidth = $info[0] * ($reHeight / $info[1]);
        } elseif (!$reHeight) {
            $reHeight = $info[1] * ($reWidth / $info[0]);
        }

        //建立临时容器
        $IM = imagecreatetruecolor($reWidth, $reHeight);
        $PM = Gd::createIM($file, $info[2]);

        //原图写入临时容器，缩放
        imagecopyresampled($IM, $PM, 0, 0, 0, 0, $reWidth, $reHeight, $info[0], $info[1]);

        $option = [
            'save' => 1,//0：只显示，1：只保存，2：即显示也保存
            'filename' => $file,
            'type' => $info[2],//文件类型
            'quality' => self::Quality,
        ];

        Gd::draw($IM, $option);
        imagedestroy($PM);
        clearstatcache();
        return true;
    }


    /*
     * 图片类型转换
     *
     * 原类型即目标类型，不需要转换
     * 若文件实际类型与指定的原类型不一致，也不转换
     * 若不指定原类型，则无论原类型是什么，都将强制转换
     * @param string $file
     * @param string $toExt 转换目标类型
     * @param array|null $fromExt 源类型，如果不给值，则无论什么类型都转换为$toExt
     * @return bool
     */
    public static function convert($file, $toExt, $fromExt = null)
    {
        if (!is_file($file)) {
            return false;//'源文件不是有效文件';
        }
        $toExt = strtolower(trim($toExt, '.'));
        if ($toExt === $fromExt or !in_array($toExt, ['jpg', 'gif', 'png']) or (is_array($fromExt) and in_array($toExt, $fromExt))) {
            return false;
        }

        $path = pathinfo($file);//原文件名相关信息
        $info = getimagesize($file);//读取尺寸等信息
        $ext = strtolower($path['extension']);
        if (!$info or $toExt === $ext) {
            return false;
        }
        $ext = image_type_to_extension($info[2], false);//取得图像类型的真实后缀，不要点号
        if (is_string($fromExt) and $fromExt !== $ext) {
            return false;
        } elseif (is_array($fromExt) and !in_array($ext, $fromExt)) {
            return false;
        }

        $IM = Gd::createIM($file, $info[2]);
        if (!$IM) return false;//'原文件无法创建成一个资源';

        $option = [
            'save' => 1,//0：只显示，1：只保存，2：即显示也保存
            'filename' => "{$path['dirname']}/{$path['filename']}.{$toExt}",
            'type' => image_type($toExt),//$info[2],//文件类型
            'quality' => self::Quality,
        ];
        Gd::draw($IM, $option);
        @unlink($file); //删除原文件
        return true;
    }


    private static function thumbs_pnt()
    {
        return Registry::get('config')->upload->thumbs->pattern;
    }

    /**
     * 访问图片缩略图时，若不存在，则直接创建
     * 仅处理符合这种结构的文件，且原图与缩图后缀必须相同，否则返回不存在
     * @param string $path 缩图保存位置，若不给定，则取$_SERVER['DOCUMENT_ROOT']
     * @param string $Uri 访问进来的图片地址，不含域名
     * 如：图片地址：https://www.codefarmer.wang/goods/1449647697_867207_9125.jpg_150x200.jpg
     * 不含域名部分：/goods/1449647697_867207_9125.jpg_150x200.jpg
     * 1，其中的两个后缀必须相同；
     * 2，最后两个数字是缩略图的尺寸；
     * 3，数字中间为x|v|z，x=以最小边多余部分裁掉，v=最大边不够补白,z=拉伸
     */
    public static function thumbs($path = null, $save = 2)
    {
        if (is_int($path)) list($path, $save) = [null, $path];

        if (!!$path and !is_dir($path)) return false;
        $Url = $_SERVER["REQUEST_URI"];
        if (!$path) $path = $_SERVER['DOCUMENT_ROOT'];

        preg_match(self::thumbs_pnt(), $Url, $matches);
        if (!$matches) {   //不符合规则
            exit('file non-existent');
        }

        $file = realpath(rtrim($path, '/') . "/{$matches[1]}.{$matches[2]}");
        $Uri = rtrim($path, '/') . '/' . trim($Url, '/');

        //源文件不存在
        if (!is_file($file)) {
            return 'Source file does not exist.';
        }

        $ext = '.' . pathinfo($file, PATHINFO_EXTENSION);
        $sourceFile = $file;

        //若存在备份文件，以备份文件作为源文件
        if (is_file($file . $ext)) $sourceFile = $file . $ext;

        $option = [
            'save' => $save,//0：只显示，1：只保存，2：即显示也保存
            'source' => $sourceFile,
        ];
        if (strtolower($matches[4]) === 'x' and Registry::get('config')->upload->thumbs->tclip) {
            return self::thumbs_tclip($Uri, $option);
        } else {
            return self::thumbs_create($Uri, $option);
        }
    }

    /**
     * 用tclip插件生成缩略图
     * 关于tclip：https://github.com/exinnet/tclip
     * @param string $file
     * @param array $option
     * @return bool
     */
    public static function thumbs_tclip($file, array $option = [])
    {
        preg_match(self::thumbs_pnt(), $file, $matches);
        if (!function_exists('tclip') or strtolower($matches[4]) !== 'v') {
            return self::thumbs_create($file, $option);
        }
        if (!$matches) return false;
        if (!isset($option['source']) or !$option['source']) {
            $option['source'] = realpath("/{$matches[1]}.{$matches[2]}");
        }

        $create = tclip($option['source'], $file, intval($matches[3]), intval($matches[5]));

        if ($create === true) {
            $type = \exif_imagetype($file);
            $im = Gd::createIM($file, $type);
            $option = [
                'save' => 0,//0：只显示，1：只保存，2：即显示也保存
                'cache' => true,//允许缓存
                'type' => $type,//文件类型
                'quality' => self::Quality,
            ];
            Gd::draw($im, $option);
            return true;
        } else {
            return self::thumbs_create($file, $option);
        }
    }

    /**
     * 根据$file路径信息，生成缩略图
     * @param string $file 如：/home/web/blog/pic/filename.jpg_100x100.jpg
     * @param array $option
     * 0:z=直接按尺寸
     * 1:v=以最大边不够补白
     * 2:x=以最小边多的裁掉
     */
    public static function thumbs_create($file, array $option = [])
    {
        preg_match(self::thumbs_pnt(), $file, $matches);
        if (!$matches) return false;
        $opt = Registry::get('config')->upload->thumbs;
        $dim = [
            'background' => $opt->background ?: '#ffffff',
            'type' => strtolower($matches[4]),
            'alpha' => $opt->alpha ?: false,
            'width' => intval($matches[3]),
            'height' => intval($matches[5]),
            'save' => 1,//0：只显示，1：只保存，2：即显示也保存
            'ext' => ".{$matches[2]}",
        ];
        $option = $option + $dim;
        if (!isset($option['source']) or !$option['source']) {
            $option['source'] = realpath("/{$matches[1]}.{$matches[2]}");
        }
        if (!is_file($option['source'])) return '源文件不存在';//源文件不存在
        if ($option['width'] === 0 and $option['height'] === 0) return '缩略图宽高不可都为0';//若宽高都为0则不生成

        $PicV = array();
        $PicV['info'] = getimagesize($option['source']);
        if (!$PicV['info']) return '源文件不是有效图片';

        $PicV['oldWidth'] = $PicV['info'][0];//源图宽
        $PicV['oldHeight'] = $PicV['info'][1];//源图高

        $oldIM = Gd::createIM($option['source'], $PicV['info'][2]);
        if (!$oldIM) return '源文件无法创建成资源';

        //若宽高任一值为0,则进行等比缩小
        if ($option['height'] === 0) {
            $option['height'] = $option['width'] * ($PicV['oldHeight'] / $PicV['oldWidth']);
        } else if ($option['width'] === 0) {
            $option['width'] = $option['height'] * ($PicV['oldWidth'] / $PicV['oldHeight']);
        }

        //建目标模式
        $newIM = imagecreatetruecolor($option['width'], $option['height']);

        //PNG写透明背景
        if ($PicV['info'][2] === 3 and $option['alpha']) {
            $alpha = Gd::createColor($newIM, '#000', 127);
            imagefill($newIM, 0, 0, $alpha);
            imagesavealpha($newIM, true);

        } else {
            //其他模式写设定的颜色背景
            $tColor = Gd::createColor($newIM, $option['background']);
            imagefilledrectangle($newIM, 0, 0, $option['width'], $option['height'], $tColor);
        }

        //计算各自宽高比,
        $PicV['nRatio'] = ($option['width'] / $option['height']);                    //新图宽高比
        $PicV['oRatio'] = ($PicV['oldWidth'] / $PicV['oldHeight']);  //老图宽高比

        //裁切形状:0正方形,1扁形,2竖形
        $PicV['cutShape'] = ($PicV['nRatio'] === $PicV['oRatio']) ? 0 : (($PicV['nRatio'] > $PicV['oRatio']) ? 2 : 1);

        //先默认值
        $oldWidth = $PicV['oldWidth'];
        $oldHeight = $PicV['oldHeight'];
        $x = $y = 0;//源图坐标
        $X = $Y = 0;//新图坐标


        //直接缩放
        if ($option['type'] === 'z') {


        } elseif ($option['type'] === 'x') {//以目标大小,最大化截取，裁切掉不等比部分
            switch ($PicV['cutShape']) {
                case 0://等比
                    break;
                case 1:    //从源图中间截取,删除左右多余
                    $percent = $PicV['oldHeight'] / $option['height'];
                    $oldWidth = $option['width'] * $percent;
                    $x = ($PicV['oldWidth'] - $oldWidth) / 2;
                    break;
                case 2://从源图中间截取,删除上下多余
                    $percent = $PicV['oldWidth'] / $option['width'];
                    $oldHeight = $option['height'] * $percent;
                    $y = ($PicV['oldHeight'] - $oldHeight) / 2;
                    break;
                default:
            }


        } elseif ($option['type'] === 'v') {//以原图大小，全部保留，不够部分留白
            switch ($PicV['cutShape']) {
                case 0://等比
                    break;
                case 1:    //最大化截取,上下留白
                    $percent = $option['width'] / $PicV['oldWidth'];
                    //$percent=($percent>1?1:$percent);
                    $Y = ($option['height'] - ($PicV['oldHeight'] * $percent)) / 2;
                    $option['height'] = $PicV['oldHeight'] * $percent;
                    break;
                case 2://最大化截取,左右留白
                    $percent = $option['height'] / $PicV['oldHeight'];
                    //$percent=($percent>1?1:$percent);
                    $X = ($option['width'] - ($PicV['oldWidth'] * $percent)) / 2;
                    $option['width'] = $PicV['oldWidth'] * $percent;
                    break;
                default:
            }
        }

        //输入并缩放
        imagecopyresampled(
            $newIM,//目标图
            $oldIM,//源图,即上面存入的图
            $X, $Y,//目标图的XY
            $x, $y,//源图XY
            $option['width'], $option['height'],//目标图宽高
            $oldWidth, $oldHeight//源图宽高
        );

        $option = [
            'save' => $option['save'],//0：只显示，1：只保存，2：即显示也保存
            'filename' => $file,
            'type' => $PicV['info'][2],//文件类型
            'cache' => true,//允许缓存
            'quality' => self::Quality,
        ];
        Gd::draw($newIM, $option);
        imagedestroy($oldIM);
        return true;
    }


    /**
     * 给图片加水印
     *
     * @param string $picFile
     * @param string $image
     * @param string $text
     * @return bool
     */
    public static function mark($picFile, array $config)
    {
        $picFile = root($picFile);
        $opt = ['img' => ['file' => null], 'txt' => ['text' => null],];

        $config = $config + $opt;
        if (!file_exists($picFile) or (!$config['img']['file'] and !$config['txt']['text'])) return false;

        $img = array();
        if (!!$config['img']['file']) {
            $_img_set = $config['img'] + [
                    'color' => '',//要抽取的水印背景色
                    'alpha' => 100,
                    'position' => 0,//位置，按九宫位
                    'shade' => [0, 0],
                    'shade_color' => '#555555',
                    'offset' => [0, 0],
                ];
            $_img_set['file'] = root($_img_set['file']);
            if (!is_array($_img_set['offset'])) $_img_set['offset'] = json_decode($_img_set['offset'], true);
            if (!is_array($_img_set['shade'])) $_img_set['shade'] = json_decode($_img_set['shade'], true);

            $img['file'] = $_img_set['file'];        //水印文件名
            $img['color'] = $_img_set['color'];        //'#000000';		//要抽除的颜色
            $img['alpha'] = $_img_set['alpha'];                //透明度,数越小,越透,最大100
            $img['position'] = $_img_set['position'];
            $img['border'] = 1;                        //消除边框像素
            $img['x'] = $_img_set['offset'][0];                            //水印偏移量,正负
            $img['y'] = $_img_set['offset'][1];
            $img['a'] = $_img_set['shade'][0];                    //阴影,正数为向右
            $img['b'] = $_img_set['shade'][1];                    //正数为向下
            $img['shade'] = $_img_set['shade_color'];        //阴影色

            if (isset($_img_set['fix'])) {
                $img['position'] = json_decode($img['fix'], true);
            } else {
                $img['position'] = json_decode($img['position'], true);
                if (is_array($img['position'])) $img['position'] = $img['position'][array_rand($img['position'])];
            }
        }

        $txt = array();
        if (!!$config['txt']['text']) {
            $_txt_set = $config['txt'] + [
                    'size' => 30,
                    'color' => '#eeeeee',
                    'alpha' => 75,
                    'position' => 0,//位置，按九宫位
                    'shade' => [0, 0],
                    'shade_color' => '#555555',
                    'offset' => [0, 0],
                ];
            $_txt_set['font'] = root($_txt_set['font']);
            if (!is_array($_txt_set['offset'])) $_txt_set['offset'] = json_decode($_txt_set['offset'], true);
            if (!is_array($_txt_set['shade'])) $_txt_set['shade'] = json_decode($_txt_set['shade'], true);

            $txt['text'] = $_txt_set['text'];
            $txt['utf8'] = 1;                            //源文字是否UTF8格式,若是从UTF8数据库取出的,填1,手工填的文字,就填0
            $txt['font'] = $_txt_set['font'];        //水印字体水印文字simkai.ttf
            $txt['size'] = $_txt_set['size'];        //字体大小,1-5
            $txt['color'] = $_txt_set['color'];        //字体颜色
            $txt['alpha'] = $_txt_set['alpha'];        //透明度,数超小,越透,最大100
            $txt['position'] = $_txt_set['position'];
            $txt['x'] = $_txt_set['offset'][0];                    //水印偏移量,正负
            $txt['y'] = $_txt_set['offset'][1];
            $txt['a'] = $_txt_set['shade'][0];                    //文字阴影,正数为向右
            $txt['b'] = $_txt_set['shade'][1];                    //正数为向下
            $txt['shade'] = $_txt_set['shade_color'];        //阴影色
            $txt['point'] = 2500;                //自动调整水印文字大小时,根据主图大小的比例,
            $txt['angle'] = 0;            //角度,暂时角度只能为0
            $txt['expand'] = 1;            //扩张像素,因中文显示时,比实际计算的尺寸要大,会造成有些边显示不了,建议为size的1/5左右,

            if (isset($txt['fix'])) {
                $txt['position'] = json_decode($txt['fix'], true);
            } else {
                $txt['position'] = json_decode($txt['position'], true);
                if (is_array($txt['position'])) $txt['position'] = $txt['position'][array_rand($txt['position'])];
            }
        }

        if ($config['backup']) self::backup($picFile);

        return self::Mark_Create($picFile, $img, $txt, $config['order']);
    }


    /**
     * 加水印
     *
     * @param string $fileName
     * @param array $img
     * @param array $txt
     * @return bool
     */
    private static function Mark_Create($fileName, array $img, array $txt, $order = 1)
    {
        //是否加水印
        $wTEXT = (!empty($txt) and isset($txt['text']) and !empty($txt['text']));
        $wIMG = (!empty($img) and isset($img['file']) and !empty($img['file']));

        if ($wIMG) {//水印文件是否存在
            $wIMG = is_file($img['file']);
        }

        if ($wTEXT) {//字体文件是否存在
            $wTEXT = is_file($txt['font']);
        }

        //不加文字也不加图片
        if (!$wTEXT and !$wIMG) return false;


        //初始化变量
        $picture = array();


        //水印设置-图片部分
        if ($wIMG) {
            $img['alpha'] = empty($img['alpha']) ? 100 : $img['alpha'];
            $img['color'] = empty($img['color']) ? '' : $img['color'];
            $img['position'] = empty($img['position']) ? 9 : $img['position'];
            $img['x'] = empty($img['x']) ? 0 : $img['x'];
            $img['y'] = empty($img['y']) ? 0 : $img['y'];
            $img['a'] = empty($img['a']) ? 0 : $img['a'];
            $img['b'] = empty($img['a']) ? 0 : $img['a'];
        }

        //水印设置-文字
        if ($wTEXT) {
            $txt['size'] = empty($txt['size']) ? 0 : $txt['size'];
            $txt['point'] = empty($txt['point']) ? 2500 : $txt['point'];
            $txt['color'] = empty($txt['color']) ? '#ffffff' : $txt['color'];
            $txt['position'] = empty($txt['position']) ? 9 : $txt['position'];
            $txt['x'] = empty($txt['x']) ? 0 : $txt['x'];
            $txt['y'] = empty($txt['y']) ? 0 : $txt['y'];

            $txt['a'] = empty($txt['a']) ? 0 : $txt['a'];
            $txt['b'] = empty($txt['a']) ? 0 : $txt['a'];

            $txt['alpha'] = empty($txt['alpha']) ? 100 : $txt['alpha'] * 1;
            $txt['expand'] = empty($txt['expand']) ? 2 : $txt['expand'];
            $txt['expand'] = ($txt['alpha'] === 100) ? 0 : $txt['expand'];
            if (!$txt['utf8']) $txt['text'] = mb_convert_encoding($txt['text'], 'UTF-8', 'GB2312');
        }

        clearstatcache();

        //组合两个图片文件地址
        //读取主图片  有关参数
        $picture['info'] = getimagesize($fileName);
        $picture['w'] = $picture['info'][0];//取得背景图片的宽
        $picture['h'] = $picture['info'][1];//取得背景图片的高

        //复制主图 到临时图片
        $IM = Gd::createIM($fileName, $picture['info'][2]);
        if (!$IM) return false;


        /*读取水印*/
        if ($wIMG) {
            $img['info'] = getimagesize($img['file']);
            $img['w'] = $img['info'][0];//取得水印图片的宽
            $img['h'] = $img['info'][1];//取得水印图片的高


            //取得水印图片的格式
            $_wIM = Gd::createIM($img['file'], $img['info'][2]);
            if (!$_wIM) return false;

            //PNG 写透明
            if ($img['info'][2] === IMAGETYPE_PNG) {
                $tmpImage = imagecreatetruecolor($img['w'], $img['h']);
                $alpha = Gd::createColor($tmpImage, '#000', 127);
                imagefill($tmpImage, 0, 0, $alpha);
                imagesavealpha($tmpImage, true);
                imagecopyresampled($tmpImage, $_wIM, 0, 0, 0, 0, $img['w'], $img['h'], $img['w'], $img['h']);
                $_wIM = $tmpImage;
//                $img['color'] = '#000000';//标记黑色，后面要扣除

                $tmpImage = imagecreatetruecolor($picture['w'], $picture['h']);
                $alpha = Gd::createColor($tmpImage, '#000000', 127);
                imagefill($tmpImage, 0, 0, $alpha);
                imagesavealpha($tmpImage, true);
                imagecopyresampled($tmpImage, $IM, 0, 0, 0, 0, $picture['w'], $picture['h'], $picture['w'], $picture['h']);
                $IM = $tmpImage;
            }

            //删除指定颜色
            if (!!$img['color']) {
                //建立中转图,并输入
                $tmpImage = imagecreatetruecolor($img['w'], $img['h']);
                imagecopy($tmpImage, $_wIM, 0, 0, 0, 0, $img['w'], $img['h']);
                $kColor = Gd::createColor($tmpImage, $img['color']);
                for ($x = 0; $x < $img['w']; $x++) {
                    for ($y = 0; $y < $img['h']; $y++) {
                        imagecolortransparent($tmpImage, $kColor);
                    }
                }
                $_wIM = $tmpImage;
            }
        } else {
            $img['w'] = 0;//取得水印图片的宽
            $img['h'] = 0;//取得水印图片的高
            $_wIM = null;
        }


        ///计算文字水印部分尺寸
        if ($wTEXT) {
            if ($txt['size'] === 0) {//自动调整字体大小,以最小边的平方,除字体大小
                $imgWH = ($picture['h'] > $picture['w']) ? $picture['w'] : $picture['h'];
                $txt['size'] = $imgWH / (strlen($txt['text']) / 3) * 0.8;
                $txt['size'] = ($txt['size'] > 80) ? 80 : $txt['size'];
                $txt['size'] = ($txt['size'] < 20) ? 20 : $txt['size'];
                $txt['expand'] = $txt['size'] * 0.3;
            }

            //取得使用 TrueType 字体的文本的范围
            $temp = imagettfbbox(ceil($txt['size']), $txt['angle'], $txt['font'], $txt['text']);
            $txt['w'] = (($temp[2] - $temp[0]) + $txt['expand'] + $txt['a']) * 1.1;
            $txt['h'] = (($temp[1] - $temp[7]) + $txt['expand'] + $txt['b']) * 1.1;
            unset($temp);
        } else {
            $txt['w'] = 0;
            $txt['h'] = 0;
        }


        //'需要加水印的图片的长度或宽度比水印'.$label.'还小，无法生成水印！'
        if ($wIMG and ($picture['w'] < $img['w'] + $txt['w']) || ($picture['h'] < $img['h'] + $txt['h'])) {
            return false;
        }

        //计算位置
        if ($wIMG) {
            if (is_array($img['position'])) {
                if ($img['position'][0] < 0) {
                    $img['x'] = $picture['w'] + $img['position'][0];
                } else {
                    $img['x'] = $img['position'][0];
                }
                if ($img['position'][1] < 0) {
                    $img['y'] = $picture['h'] + $img['position'][1];
                } else {
                    $img['y'] = $img['position'][1];
                }
            } else {
                switch ($img['position']) {
                    case 0://随机
                        $img['x'] += mt_rand(0, ($picture['w'] - $img['w']));
                        $img['y'] += mt_rand(0, ($picture['h'] - $img['h']));
                        break;
                    case 1://1为顶端居左
                        $img['x'] += 0;
                        $img['y'] += 0;
                        break;
                    case 2://2为顶端居中
                        $img['x'] += ($picture['w'] - $img['w']) / 2;
                        $img['y'] += 0;
                        break;
                    case 3://3为顶端居右
                        $img['x'] += $picture['w'] - $img['w'];
                        $img['y'] += 0;
                        break;
                    case 4://4为中部居左
                        $img['x'] += 0;
                        $img['y'] += ($picture['h'] - $img['h']) / 2;
                        break;
                    case 5://5为中部居中
                        $img['x'] += ($picture['w'] - $img['w']) / 2;
                        $img['y'] += ($picture['h'] - $img['h']) / 2;
                        break;
                    case 6://6为中部居右
                        $img['x'] += $picture['w'] - $img['w'];
                        $img['y'] += ($picture['h'] - $img['h']) / 2;
                        break;
                    case 7://7为底端居左
                        $img['x'] += 0;
                        $img['y'] += $picture['h'] - $img['h'];
                        break;
                    case 8://8为底端居中
                        $img['x'] += ($picture['w'] - $img['w']) / 2;
                        $img['y'] += $picture['h'] - $img['h'];
                        break;
                    case 9://9为底端居右
                        $img['x'] += $picture['w'] - $img['w'];
                        $img['y'] += $picture['h'] - $img['h'];
                        break;
                    default://随机
                        $img['x'] += mt_rand(0, ($picture['w'] - $img['w']));
                        $img['y'] += mt_rand(0, ($picture['h'] - $img['h']));
                        break;
                }
            }
        }

        //计算文字位置
        if ($wTEXT) {
            if (is_array($txt['position'])) {
                if ($txt['position'][0] < 0) {
                    $txt['x'] = $picture['w'] + $txt['position'][0];
                } else {
                    $txt['x'] = $txt['position'][0];
                }
                if ($txt['position'][1] < 0) {
                    $txt['y'] = $picture['h'] + $txt['position'][1];
                } else {
                    $txt['y'] = $txt['position'][1];
                }
            } else {
                switch ($txt['position']) {
                    case 0://随机
                        $txt['x'] += mt_rand(0, ($picture['w'] - $txt['w']));
                        $txt['y'] += mt_rand(0, ($picture['h'] - $txt['h']));
                        break;
                    case 1://1为顶端居左
                        $txt['x'] += 0;
                        $txt['y'] += 0;
                        break;
                    case 2://2为顶端居中
                        $txt['x'] += ($picture['w'] - $txt['w']) / 2;
                        $txt['y'] += 0;
                        break;
                    case 3://3为顶端居右
                        $txt['x'] += $picture['w'] - $txt['w'];
                        $txt['y'] += 0;
                        break;
                    case 4://4为中部居左
                        $txt['x'] += 0;
                        $txt['y'] += ($picture['h'] - $txt['h']) / 2;
                        break;
                    case 5://5为中部居中
                        $txt['x'] += ($picture['w'] - $txt['w']) / 2;
                        $txt['y'] += ($picture['h'] - $txt['h']) / 2;
                        break;
                    case 6://6为中部居右
                        $txt['x'] += $picture['w'] - $txt['w'];
                        $txt['y'] += ($picture['h'] - $txt['h']) / 2;
                        break;
                    case 7://7为底端居左
                        $txt['x'] += 0;
                        $txt['y'] += $picture['h'] - $txt['h'] * 1.2;
                        break;
                    case 8://8为底端居中
                        $txt['x'] += ($picture['w'] - $txt['w']) / 2;
                        $txt['y'] += $picture['h'] - $txt['h'] * 1.2;
                        break;
                    case 9://9为底端居右
                        $txt['x'] += $picture['w'] - $txt['w'];
                        $txt['y'] += $picture['h'] - $txt['h'] * 1.2;
                        break;
                    default://随机
                        $txt['x'] += mt_rand(0, ($picture['w'] - $txt['w']));
                        $txt['y'] += mt_rand(0, ($picture['h'] - $txt['h']));
                        break;
                }
            }
        }


        //重叠设置,图或文字,哪个在前,1图,2字,0不管
        if ($wIMG and $wTEXT and $txt['position'] === $img['position'] and $order > 0) {
            switch ($txt['position']) {
                case 1:
                case 4:
                case 7:
                    if ($order === 1) {
                        $txt['x'] += $img['w'] + $txt['expand'];
                    } else {
                        $img['x'] += $txt['w'] + $txt['expand'] * 2;
                    }
                    break;
                case 3:
                case 6:
                case 9:
                    if ($order === 1) {
                        $img['x'] -= ($txt['w'] + $txt['expand'] * 2);
                    } else {
                        $txt['x'] -= ($img['w'] + $txt['expand'] * 2);
                    }
                    break;
                case 2:
                case 5:
                case 8:
                    if ($order === 1) {
                        $img['x'] -= ($img['w'] / 2 + ($txt['w'] - $img['w'] + $txt['expand'] * 2) / 2);
                    } else {
                        $txt['x'] += (($txt['w'] + $txt['expand'] * 2) / 2 + ($txt['w'] - $img['w'] + $txt['expand'] * 2) / 2);
                    }
                    break;
            }
        }


        //设定图像的混色模式     ,并开始写入
        imagealphablending($IM, true);

        //写入图片水印
        if ($wIMG and $_wIM) {
            if ($img['a'] || $img['b']) {
                $shade_color = Gd::createColor($IM, $img['shade'], $img['alpha']);
                imagefilledrectangle($IM, $img['x'] + $img['a'] + $img['border'], $img['y'] + $img['b'] + $img['border'], $img['w'] - $img['border'] * 2, $img['h'] - $img['border'] * 2, $shade_color);
//                imagecopymerge($IM, $_wIM, $img['x'] + $img['a'], $img['y'] + $img['b'], $img['border'], $img['border'], $img['w'] - $img['border'] * 2, $img['h'] - $img['border'] * 2, $img['alpha']);
            }
            imagecopymerge($IM, $_wIM, $img['x'], $img['y'], $img['border'], $img['border'], $img['w'] - $img['border'] * 2, $img['h'] - $img['border'] * 2, $img['alpha']);
            imagedestroy($_wIM);
        }

        //写入文字
        if ($wTEXT) {
            if ($txt['a'] || $txt['b']) { //要求阴影,先把阴影文字写上
                $color = Gd::createColor($IM, $txt['shade'], 100 - $txt['alpha']);    ///$txt['expand']
                imagettftext($IM, $txt['size'], $txt['angle'], $txt['x'] + $txt['a'], $txt['y'] + $txt['h'] + $txt['b'], $color, $txt['font'], $txt['text']);
            }
            //写前景字
            $color = Gd::createColor($IM, $txt['color'], 100 - $txt['alpha']);    ///$txt['expand']
            imagettftext($IM, $txt['size'], $txt['angle'], $txt['x'], $txt['y'] + $txt['h'], $color, $txt['font'], $txt['text']);

        }

        $option = [
            'save' => 1,//0：只显示，1：只保存，2：即显示也保存
            'filename' => $fileName,
            'type' => $picture['info'][2],//文件类型
            'quality' => self::Quality,
        ];

        Gd::draw($IM, $option);

        return true;
    }


}