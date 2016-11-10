<?php
namespace esp\extend\tools;

use \Yaf\Registry;

/**
 * Ip应用：
 * 1：数据导入：
 *      从纯真IP网（http://www.cz88.net/）下载最新版库文件；
 *      安装；
 *      执行安装目录下【ip.exe】，点最下面的【解压】，导出到一个txt文件。
 *      \tools\Ip::Import(txt文件绝对路径);
 *
 * 2：查询错误：\tools\Ip::get('192.168.1.11');
 *
 * 3：查询IP库版本：\tools\Ip::version();
 *
 */
final class Ip
{
    private static function db($table)
    {
        return new \db\Mongodb($table);
    }

    /**
     * 将txt数据导入到数据库
     * @param null $ipData
     */
    public static function Import($ipData = null)
    {
        if (!$ipData or !is_file($ipData)) exit('Data File not exists.');

        $conf = Registry::get('config')->ip;
        $DB = self::db($conf)->table($conf->table);
        $file = file($ipData);

        $replace = function ($str) {
            $str = str_replace('CZ88.NET', '', $str);
            return trim(@iconv('GB2312', 'UTF-8//IGNORE', $str));
        };

        foreach ($file as $i => &$ip) {
            preg_replace_callback('/^(\d+\.\d+\.\d+\.\d+)\s+(\d+\.\d+\.\d+\.\d+)\s+(.*)$/i', function ($matches) use ($DB, $replace) {
                $DB->insert([
                    'ip_a' => $matches[1], 'ip_b' => $matches[2],
                    'lng_a' => ip2long($matches[1]), 'lng_b' => ip2long($matches[2]),
                    'add' => $replace($matches[3]) ?: '未知地址',
                ]);

            }, $ip);

        }
    }


    /**
     * 查询IP
     * @param null $ip
     * @return array
     */
    public static function get($ip = null)
    {
        $conf = Registry::get('config')->ip;

        $lng = ip2long($ip);
        $where = ['lng_b' => ['$gte' => $lng], 'lng_a' => ['$lte' => $lng]];

        return self::db($conf)->table($conf->table)->where($where)->get(1);

//        self::init();
//        $query = new \MongoDB\Driver\Query($where, ['projection' => ['_id' => 0]]);
//        $RS = self::$_conn->executeQuery(self::$_mongo_data, $query)->toArray();
//        return (array)$RS[0];
    }

    /**
     * 查询IP库日期
     */
    public static function version()
    {
        $arr = self::get('255.255.255.255');
        return !$arr ? null : $arr['add'];
    }


}
