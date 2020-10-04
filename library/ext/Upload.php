<?php

namespace esp\library\ext;

/*
 *
 * php.ini中相关设置：
 *
 * file_uploads         上传开关
 * upload_tmp_dir       临时目录
 * upload_max_filesize  上传限制大小
 * max_file_uploads     一次最多传多少个文件
 *
 * max_execution_time   页面执行时间
 * max_input_time       接收数据最大时间
 * memory_limit         页面使用内存限制
 *
 *
    图片上传类


    1，处理表单上传，可单传，或多传，多传时用
        <input type="file" name="upload[]" size="100" multiple="multiple">
            要点：name="upload[]"          正常名称后加[]，表示为数组方式
                 multiple="multiple"      表单中允许一次选择多个文件
            当然也可以放多个input项，文件名用统一的，效果一样。

    2，下载远程图片



*/

use esp\library\gd\Image;

final class Upload
{
    private $option = null;

    public function __construct(array $option = [])
    {
        $this->option = $option;
    }

    private function logSave($img)
    {

    }


    /**
     * 直接保存
     * @param $base64
     * @return array
     */
    public function saveBaseCode($base64)
    {
        $save = $this->filename('jpg');
        $file = "{$save['root']}{$save['folder']}{$save['name']}.jpg";
        $img = base64_decode($base64);
        $length = @file_put_contents($file, $img);//返回的是字节数
        if (!$length) return [];
        return $save + ['length' => $length];
    }

    /**
     * 保存二进制为文件
     */
    private function saveBase($base64)
    {
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
            return $this->saveBaseCode(str_replace($result[1], '', $base64));
        } else {
            return [];
        }
    }

    public function base64()
    {
        $upSave = Array();
        $val = file_get_contents("php://input");
        parse_str($val, $arr);
        if (empty($arr)) return [];

        foreach ($arr as $key => &$upLoad) {
            $save = $this->saveBase($upLoad);
            if (!empty($save)) $upSave[$key] = $save;
        }
        return $upSave;
    }

    public function upload()
    {
        if (empty($_FILES)) return [];
        $upSave = Array();

        foreach ($_FILES as $key => &$upLoad) {
            //同时传了多个文件
            if (is_array($upLoad['tmp_name'])) {
                for ($i = 0; $i < count($upLoad['tmp_name']); $i++) {
                    $upNow = array();
                    if ($upLoad['tmp_name'][$i]) {
                        $upNow['tmp_name'] = $upLoad['tmp_name'][$i];
                        $upNow['type'] = $upLoad['type'][$i];
                        $upNow['error'] = $upLoad['error'][$i];
                        $upNow['name'] = $upLoad['name'][$i];
                        $upNow['size'] = $upLoad['size'][$i];
                        $upSave["{$key}_{$i}"] = $this->Upload_Save($upNow);
                    }
                }
                //只有一个文件
            } elseif (is_string($upLoad['tmp_name']) and $upLoad['tmp_name']) {
                $upSave[$key] = $this->Upload_Save($upLoad);
            }
        }
        return $upSave;
    }

    public function download($url)
    {
        if (!$url) return false;
        if (!is_array($url)) $url = [$url];
        $allImg = array();

        foreach ($url as $i => &$u) {
            $allImg[] = $this->DownLoad_Save($u);
        }
        return $allImg;
    }

    private function DownLoad_Save($url)
    {
        if (!$url) return null;
        $return = Array();
        $name = strrchr($url, '/');
        $ext = trim(strrchr($name, '.'), '.');
        $save = $this->filename($ext);

        //下载
        $IM = file_get_contents($url);

        if (!$IM or empty($IM)) return null;
        $return['size'] = strlen($IM);

        //保存
        file_put_contents($save['path'], $IM);
        $return['md5'] = md5_file($save['path']);

        if (!$ext) {
            //若原文件没有后缀，则重新根据保存的文件类型再次查找后缀
            $picSize = getimagesize($save['path']);
            if ($picSize) {
                $ext = image_type_to_extension($picSize[2], false);
                rename($save['path'], "{$save['root']}{$save['folder']}{$save['name']}.{$ext}");
                $save = [
                        'ext' => $ext,
                        'filename' => "{$save['name']}.{$ext}",
                        'path' => "{$save['root']}{$save['folder']}{$save['name']}.{$ext}"
                    ] + $save;
            }
        }

        if (in_array($ext, ['gif', 'jpeg', 'jpg', 'png', 'tif', 'tiff', 'wbmp', 'ico', 'jng', 'bmp', 'svg', 'svgz', 'webp'])) {
            $picSize = (isset($picSize) and $picSize) ? $picSize : getimagesize($save['path']);
            if (!$picSize) return null;
            $return['width'] = $picSize[0];
            $return['height'] = $picSize[1];
            $this->image_operation($save, $ext, $picSize);
        }
        return $save + $return;
    }

    private function Upload_Save($FILES)
    {
        $return = Array();
        $return['error'] = null;

        //错误检索
        switch ($FILES['error']) {
            case 1 || 2:        //超出大小限制,超过了文件大小，在php.ini文件中设置,MAX_FILE_SIZE
                $return['error'] = '文件体积超过系统限制';
                return $return;
            case 3 || 4 || 5:    //错误,或未被上传
                $return['error'] = '未知错误';
                return $return;
        }

        $return['original'] = $FILES['name'];//原文件
        $picSize = getimagesize($FILES['tmp_name']);
        $ext = $picSize ? image_type_to_extension($picSize[2], false) : null;//取得图像类型的真实后缀，不要点号
        $ext = $ext ?: strtolower(pathinfo($FILES['name'], PATHINFO_EXTENSION));//获取后缀

        $return['size'] = $FILES['size'];
//
//        $size = $this->checkSize($FILES['size']);
//        if ($size !== true) {
//            $return['error'] = $size;
//            return $return;
//        }

        //用图片的方式去检查
        if (in_array($ext, ['gif', 'jpeg', 'jpg', 'png', 'tif', 'tiff', 'wbmp', 'ico', 'jng', 'bmp', 'svg', 'svgz', 'webp'])) {

            //检查图片有没有宽度,若没有,则为非法  'HACK:此处只能上传图片文件,若确认传的是图片,请联系管理员.';
            if (!$picSize or !$picSize[0]) {
                $return['error'] = '非法图片格式';
                return $return;
            }

            $return['width'] = $picSize[0];
            $return['height'] = $picSize[1];
            $return['ratio'] = $picSize[0] / $picSize[1];

            $ratio = $this->checkRatio($picSize[0] / $picSize[1]);
            if ($ratio !== true) {
                $return['error'] = $ratio;
                return $return;
            }

            //图片宽高限制检查
            $wh = $this->checkLimit($picSize[0], $picSize[1]);
            if ($wh !== true) {
                $return['error'] = $wh;
                return $return;
            }
        } else {
            $return['error'] = '非法文件类型' . $ext;
            return $return;
        }
        $save = $this->filename($ext);
        move_uploaded_file($FILES['tmp_name'], $save['path']);
        $return['md5'] = md5_file($save['path']);
        $this->image_operation($save, $ext, $picSize);

//        $this->logSave($img);

        return $save + $return;
    }

    /**
     * 针对图片的处理
     * @param $save
     * @param $ext
     */
    private function image_operation(&$save, &$ext, $size)
    {
        if (!in_array($ext, ['gif', 'jpeg', 'jpg', 'png', 'tif', 'tiff', 'wbmp', 'ico', 'jng', 'bmp', 'svg', 'svgz', 'webp'])) return;

        //检查文件类型，若是BMP，强制转换为PNG
        if (Image::convert($save['path'], 'png', 'bmp')) {
            $save = [
                    'ext' => 'png',
                    'filename' => $save['name'] . '.png',
                    'path' => $save['root'] . $save['folder'] . $save['name'] . '.png'
                ] + $save;
            $ext = 'png';
        }
        $conf = $this->option;
        $mark = $this->option['mark'] ?? [];

        //修正大小
        if ((!!$conf['limit']['width'] and $size[0] > $conf['limit']['width']) or
            (!!$conf['limit']['height'] and $size[1] > $conf['limit']['height'])
        ) {
            Image::size($save['path'],
                [$conf['limit']['width'], $conf['limit']['height']],
                $conf['limit']['backup']);
        }

        //强制缩放
        if (!!$conf['size']['width'] or !!$conf['size']['height']) {
            Image::size($save['path'],
                [$conf['size']['width'], $conf['size']['height']],
                $conf['size']['backup']);
        }

        //加水印
        if ($mark['used'] and stripos('.' . $mark['type'], $ext) and
            $size[0] >= $mark['mind'] and
            (!!$mark['img']['file'] or
                (!!$mark['txt']['text']) and !!$mark['txt']['font'])
        ) {
            Image::mark($save['path'], $mark);
        }
        clearstatcache();
    }

    /**
     * 创建文件名称相关数据
     * @param $ext
     * @return array
     */
    private function filename($ext)
    {
        $option = $this->option;
        $ext = trim(strtolower($ext), '.');

        $root = \esp\helper\root(rtrim($option['save']['root'], '/') . '/');

        $folder = trim($option['save']['folder'] ?: '%D', '/') . '/';
        $folder = \esp\helper\format($folder);
        $name = \esp\helper\format($option['save']['name'] ?: '%u');
        $name = strtolower($name);

        $path = $root . $folder . $name . ($ext ? ".{$ext}" : '');
        \esp\helper\mk_dir($path);

        if (is_file(realpath($path))) {
            return $this->filename($ext);
        }
        return [
            'root' => $root,
            'folder' => $folder,
            'name' => $name,
            'filename' => $name . ($ext ? ".{$ext}" : ''),
            'ext' => $ext,
            'path' => $path
        ];
    }


    /*
     * 宽高比检查
     * */
    private function checkRatio($picRatio)
    {
        $ratio = json_decode($this->option['allow']['ratio'], true);
        if (!$ratio or empty($ratio)) return true;
        $i = 10000;     //精确度，至少大于100
        $iniRatio = (int)($picRatio * $i);

        if (!is_array($ratio)) {
            return (int)($ratio * $i) === $iniRatio ? true : ('图片宽高比必须为：' . $ratio);
        }

        $pnt = function ($n, $p = 2) {//格式化百分比
            return sprintf("%01.{$p}f", $n * 100) . '%';
        };

        if (!$ratio[0]) {
            return (int)($ratio[1] * $i) >= $iniRatio ? true : ("图片宽不能大于高的：{$pnt($ratio[1])}");
        } elseif (!$ratio[1]) {
            return (int)($ratio[0] * $i) <= $iniRatio ? true : ("图片宽不能小于高的：{$pnt($ratio[0])}");
        } else {
            return ((int)($ratio[1] * $i) >= $iniRatio and (int)($ratio[0] * $i) <= $iniRatio) ? true :
                ("图片宽须介于高的：{$pnt($ratio[0])}至{$pnt($ratio[1])}之间");
        }
    }

    /*
     * 宽高限制检查
     * */
    private function checkLimit($width, $height)
    {
        $limitWidth = json_decode($this->option['allow']['width'], true);
        $limitHeight = json_decode($this->option['allow']['height'], true);

        if (is_array($limitWidth) and ($limitWidth[0] > $width or ($limitWidth[1] > 0 and $limitWidth[1] < $width))) {
            if ($limitWidth[0] == 0)
                return "图片宽度不可大于：{$limitWidth[1]}PX";
            elseif ($limitWidth[1] == 0)
                return "图片宽度不可小于：{$limitWidth[0]}PX";
            else
                return "图片宽度只可介于{$limitWidth[0]}PX至{$limitWidth[1]}PX之间";

        } elseif (is_int($limitWidth) and $limitWidth > 0 and $limitWidth != $width) {
            return "图片宽度只可等于{$limitWidth}PX";
        }

        if (is_array($limitHeight) and ($limitHeight[0] > $height or ($limitHeight[1] > 0 and $limitHeight[1] < $height))) {
            if ($limitHeight[0] == 0)
                return "图片高度不可大于{$limitHeight[1]}PX";
            elseif ($limitHeight[1] == 0)
                return "图片高度不可小于{$limitHeight[0]}PX";
            else
                return "图片高度只可介于{$limitHeight[0]}PX至{$limitHeight[1]}PX之间";

        } elseif (is_int($limitHeight) and $limitHeight > 0 and $limitHeight != $height) {
            return "图片高度只可等于{$limitHeight}PX";
        }
        return true;
    }

    /**
     * 尺寸检查
     */
    private function checkSize($size)
    {
        $conf = $this->option['allow']['size'];
        $limitSize = json_decode($conf, true);
        if (is_array($limitSize)) {
            $limitSize[0] = \esp\helper\re_size($limitSize[0]);
            $limitSize[1] = \esp\helper\re_size($limitSize[1]);
        } else {
            $limitSize = \esp\helper\re_size($conf);
        }
        if (is_array($limitSize) and ($limitSize[0] > $size or ($limitSize[1] > 0 and $limitSize[1] < $size))) {
            if ($limitSize[0] == 0)
                return "文件体积不可大于：{$limitSize[1]}";
            elseif ($limitSize[1] == 0)
                return "文件体积可小于：{$limitSize[0]}";
            else
                return "文件体积只可介于{$limitSize[0]}至{$limitSize[1]}之间";

        } elseif (is_int($limitSize) and $limitSize > 0 and $limitSize != $size) {
            return "文件体积只可等于{$limitSize}";
        }
        return true;
    }

    /*
     * 删除图片
     * 此函数可以放至其他类中
     * 返回实际删除的文件数量
     *
     * TODO 删除比较危险，是否允许自动删除，待议。
    */
    public function kill($filePath = "")
    {
        if (!$filePath) return 0;
        if (is_array($filePath)) {
            $i = 0;
            foreach ($filePath as $k => &$p) {
                $i += $this->kill($p);
            }
            return $i;
        }

        $info = pathinfo($filePath);
        $ext = $info['extension'];
        $file = $info['filename'];
        $dir = dirname($filePath);
        $unlinkCount = 0;

        // TODO 这里删除文件，具体方案有待进一步确定，暂时直接删除文件，也可以考虑移到另一个目录
        foreach (glob($dir . '/' . $file . '.*') as &$dirFile) {
            $unlinkCount += @unlink($dirFile) ? 1 : 0;
        }
        return $unlinkCount;
    }


}
