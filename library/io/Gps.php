<?php
namespace io;

class Gps
{
    /**
     * 天安门广场国旗位置
     */
    const POINT = [
        'lng' => 39.905598,//纬度
        'lat' => 116.391328,//经度
    ];

    /**
     * 计算两个坐标之间的距离
     * @param float $lat1 目标经度
     * @param float $lng1 目标纬度
     * @param float $_lat 相对经度
     * @param float $_lng 相对纬度
     * @return float|int    单位：米
     */
    public static function span(float $lat1, float $lng1, float $_lat = 0, float $_lng = 0)
    {
        if (!$lng1 or !$lat1) return 0;
        $_lat = $_lat ?: self::POINT['lat'];
        $_lng = $_lng ?: self::POINT['lng'];

        $earthRadius = 6367000;            //地球半径
        $lat1 = ($lat1 * pi()) / 180;
        $lng1 = ($lng1 * pi()) / 180;
        $_lng = ($_lng * pi()) / 180;
        $_lat = ($_lat * pi()) / 180;
        $stepOne = pow(sin(($_lng - $lat1) / 2), 2) + cos($lat1) * cos($_lng) * pow(sin(($_lat - $lng1) / 2), 2);
        $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
        $calculatedDistance = $earthRadius * $stepTwo;
        return round($calculatedDistance);
    }

    /**
     * 读取Google地址
     * @param string $lat 经度，国内指东经，相对于国际子午线的位置，国内在70-130之间，江苏在120左右
     * @param string $lng 纬度，经内指北纬，相对于赤道的位置，国内在10-50之间，江苏在30左右
     * @return string
     */
    public static function address(float $lat, float $lng, $from = 'baidu')
    {
        return ($from === 'baidu') ? self::fromBaidu($lat, $lng) : self::fromGoogle($lat, $lng);
    }


    private static function fromGoogle($lat, $lng)
    {
        $url = "http://maps.google.cn/maps/api/geocode/json?latlng={$lat},{$lng}&sensor=true&language=zh-CN";
        $address = \io\Output::get($url);
        if (is_array($address)) {
            return isset($address["results"][0]["formatted_address"]) ? $address["results"][0]["formatted_address"] : '';
        }
        return 'not found';
    }


    private static $baiduAK = 'rC7I02ZpuECbN5H5D4b1Tzh8';
    private static $err = [
        '1' => '服务器内部错误',
        '2' => '请求参数非法',
        '3' => '权限校验失败',
        '4' => '配额校验失败',
        '5' => 'ak不存在或者非法',
        '101' => '服务禁用',
        '102' => '不通过白名单或者安全码不对',
        '2xx' => '无权限',
        '3xx' => '配额错误',
    ];


    /**
     * 从百度读取地址，参数同上
     * @param $lat
     * @param $lng
     * @return string
     */
    private static function fromBaidu($lat, $lng)
    {
        $info = [
            'ak' => self::$baiduAK,
            'output' => 'json',
            'location' => "{$lat},{$lng}",
            'coordtype' => 'wgs84ll',//可选：bd09ll（百度经纬度坐标）、bd09mc（百度米制坐标）、gcj02ll（国测局经纬度坐标）、wgs84ll（ GPS经纬度）
        ];
        $url = 'http://api.map.baidu.com/geocoder/v2/' . http_build_query($info);
        $address = \io\Output::get($url);
        if (!is_array($address)) return '';
        if ($address['status'] != 0) {
            return isset(self::$err[$address['status']]) ? self::$err[$address['status']] : 'error';
        }
        return $address['result']['formatted_address'];
    }


    /**
     * 根据地址查GPS座标，如：江苏省苏州市东平街299号，江苏苏州东平街299号
     * @param $address
     * @return string
     */
    public static function getGPS($address)
    {
        $info = [
            'ak' => self::$baiduAK,
            'output' => 'json',
            'address' => ($address),
            'coordtype' => 'wgs84ll',//可选：bd09ll（百度经纬度坐标）、bd09mc（百度米制坐标）、gcj02ll（国测局经纬度坐标）、wgs84ll（ GPS经纬度）
        ];
        $url = 'http://api.map.baidu.com/geocoder/v2/' . http_build_query($info);
        $address = \io\Output::get($url);
        if (!is_array($address)) return '';
        if ($address['status'] != 0) {
            return isset(self::$err[$address['status']]) ? self::$err[$address['status']] : 'error';
        }
        return $address['result'];
    }

}