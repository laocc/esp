<?php
namespace io\ext;

use \Yaf\Config\Ini;

class Log
{

    //存日志
    public static function save(array $dat, Ini $mysql)
    {
        $D = array();
        $D['user'] = $dat['user'];
        $D['root'] = $dat['root'];
        $D['folder'] = $dat['folder'];
        $D['filename'] = $dat['filename'];
        $D['ext'] = $dat['ext'];
        $D['path'] = $dat['path'];
        $D['domain'] = $dat['domain'];
        $D['https'] = $dat['https'] ? 1 : 0;
        $D['url'] = ($dat['https'] ? 'https://' : 'http://') . "{$dat['domain']}/{$dat['folder']}{$dat['filename']}.{$dat['ext']}";
        $D['time'] = time();
        $D['width'] = $dat['width'];
        $D['height'] = $dat['height'];
        $D['size'] = $dat['size'];
        $D['title'] = $dat['title'];
        return (new \db\Mysql($mysql))->table("tabUploadImg")->insert($D);
    }


    //删除一条记录，$fileName可以是文件名，也可以是ID，$delete=false时只是标记已删除，不物理删除
    public static function kill($fileName = '', $delete = false)
    {
        if (!$fileName) return true;
        $rs = (new \db\Mysql())->table('upload.tabUploadImg');
        if (is_string($fileName)) {
            $rs->where('upFileName', $fileName);
        } elseif (is_int($fileName)) {
            $rs->where('upID', $fileName);
        }
        if ($delete === false) {
            return $rs->update(['upDelete' => time()]);
        } else {
            $db = $rs->get();
            $i = self::delete($db);//删除该记录
            $rs->delete();
            return $i;
        }
    }

    //删除一条记录
    protected static function delete($RS)
    {
        $file = [];
        $count = 0;
        foreach ($RS as &$rs) {
            if ($rs['upFileExt'] and substr($rs['upFileExt'], 0, 1) != '.')
                $rs['upFileExt'] = '.' . $rs['upFileExt'];

            $path = _ROOT . $rs['upPath'] . $rs['upFolder'];
            $file[] = $path . $rs['upFileName'];
            $file[] = $path . $rs['upFileName'] . '.bak';
            $file[] = $path . $rs['upFileName'] . $rs['upFileExt'];

            $upThumbs = explode(',', $rs['upThumbs']);
            foreach ($upThumbs as &$v) {
                $file[] = $path . $rs['upFileName'] . '_' . $v . $rs['upFileExt'];
            }
        }
        foreach ($file as &$f) {
            $count += (@unlink($f) ? 1 : 0);
        }
        return $count;
    }

    public static function baseName($url)
    {
        $file = pathinfo($url);
        return $file['basename'];
    }


}