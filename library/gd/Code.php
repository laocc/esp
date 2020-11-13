<?php

namespace esp\library\gd;

use esp\library\gd\ext\Gd;

/**
 * 验证码类：
 * 可生成中文或英文，但是相互不能混在一起，因为编码方式不同
 * 如果用中文验证码，建议不要超过4个字。
 * 在PHP页面中任意地方调用即可，会清空其他已有的所有内容
 * \io\Code::create(array [$option]);
 * $option可以是本程序类中$option的全部或部分定义
 *
 * 关于验证码字典：
 * 1，中文字典可以任意重置，但是切不可夹入符号、英文或空格，即只可以是中文；
 * 2，至于要不要放笔画多的汉字可任意，但是汉字一和偏旁部首不要放入，还有如：已&己&巳，日&曰，天&夭等难以区分的汉字；
 * 3，同一个汉字多次出现并不会增加其出现的几率；
 * 4，验证码字体大小=验证码图片高度/2 ± 1；
 *
 * 关于验证：
 * 1，本程序采用的是Cookies方式保存验证码，当然可以改用其它方式；
 * 2，Cookies里保存的并不是验证码本身，而是【self::$cook['attach'] . date('HdYm') . CODE】的hash结果；
 * 3，验证时也采用上述方法得到用户输入的验证码hash结果进行对比，而不是传统的对比两个码是否相同；
 * 4，如果想采用其他介质保存验证码，比如redis，则将下面cookies部分替换一下即可，当然也可能需要cookies保存redis产生的键值；
 * 5，如果彻底不用cookies，则一般的方法是先在程序里生成好验证码并保存在redis中，然后将验证码送入本程序显示；
 *
 * 直接指定code：
 * 1，\io\Code::create(['code' => 'CODE']);
 * 2，option中的charset/length不再有用，程序自动判断；
 * 3，本程序不再保存cookies，须自行在业务程序中进行验证；
 * 4，务必送入的是纯中文或纯英文数字；
 * 5，这种情况下如果想实现【看不清点一下】的效果须注意数据的统一性；
 *
 * 关于验证码有效期：
 * 1，提前是用本程序所用的Cookies方式；
 * 2，$option['cookies']['date']参数用于hash时的data()函数；
 * 3，建议用【YmdH】，这表示精确到小时，也就是有效期为1小时，如果带上【i】则表示为1分钟，如果是这样，就须知道下面的情况；
 * 4，假设精确到分钟，则在时间跨分钟时，验证码也就失效，也就是说有可能有效期只有几秒，所以这种情况不能频繁出现；
 * 5，在精确到小时的情况下，也就是一小时才会有一次这样的情况，这应该是可以接受的；
 * 6，如果不需要这种方式，可以设为空，或只设Y，可能的值，见date()函数有关介绍；
 *
 * 关于其他：
 * 1，如果不需要干扰，则将线条数和雪花点设为0就可以了；
 * 2，如果想干扰色不那么明显，就把相关值设大一点，字体色设小一点；
 *
 * 关于字体：
 * 1，如果不想自己传字体文件，可以用Linux系统自带的字体，但仅限英文，若用中文，可能必须要自己上传中文字体文件；
 * 2，如果用系统字体，可以在【/usr/share/fonts/】找，字体有很多，但不是每一个都可以用，多试几个；
 * 3，要确保有上述目录的访问权限，最简单的就是在PHP.ini中修改open_basedir，或在fastCGI.conf中添加：
 *    fastcgi_param PHP_VALUE "open_basedir=/home/:/tmp/:/proc/:/usr/share/fonts/";
 *    这只是我自己的设置，用的时候根据实际情况而定。
 *    或者干脆把字体文件复制到自己的程序目录中来。
 *
 * Class Code
 * @package io
 */
class Code
{
    private static $option = [
        'charset' => 'en',      //使用中文或英文验证码，cn=中文，en=英文，num=数字，若create()指定了，则以指定的为准
        'length' => [3, 5],     //验证码长度范围
        'size' => [150, 50],    //宽度，高度
        'span' => [-15, -10],    //字间距随机范围
        'angle' => [-30, 30],   //角度范围，建议不要超过±45
        'line' => [4, 6],       //产生的线条数
        'point' => [80, 100],   //产生的雪花点数
        'source' => '',         //站点内位置标识符，同一个站可能多个地方需要验证码，加此标识加以区分

        //分别是中英文字体，要确保这些文件真实存在，相对于根目录
        'en_font' => ['/common/fonts/arial.ttf', '/common/fonts/ariblk.ttf'],
        'cn_font' => ['/common/fonts/simkai.ttf', '/common/fonts/ygyxsziti2.0.ttf'],

        //下面四种颜色均是指范围，0-255之间，值越大颜色越浅。
        'b_color' => [157, 255],     //背景色范围
        'p_color' => [200, 255],     //雪花点颜色范围
        'l_color' => [50, 200],      //干扰线颜色范围
        'c_color' => [10, 156],      //验证码字颜色范围

        'cookies' => [  //Cookies相关定义
            'key' => '__C__',   //Cookies键
            'attach' => 'P@w#c$888',    //附加固定字符串
            'date' => 'YmdH',   //附加时间标识用于date()函数，同时也是有效期
        ],
        'type' => 1,//式样1
    ];

    //中英文验证码字库，不要含有容易混淆的字符，如0和o。
    private static $disc = [
        'en' => 'AaBbCcDdEeFfGgHhJKkMmNnPpRqRSsTtUuVvWwXxYyZz23456789',//不含[01IijLlOor]容易混淆的字符
        'cn' => '我国首次火星探测任务经中央批准立项火星探测是我国深空探测继探月工程之后的重要创举是又项国家重大标志性工程为扩大工程的社会影响树立工程的文化形象激发全国人民和海外同胞的爱国热情探月与航工程中心会同有关部门和单位组织中国火星探测工程名称和图形标识全球征集活动',
        'num' => '0123456789'
    ];


    /**
     * 生成验证码
     * @param array $option
     * @return bool|null
     */
    public static function create(array $option = [])
    {
        if (php_sapi_name() === 'cli') return null;
        $option += self::$option;
        $enFont = ['en' => [], 'cn' => []];
        foreach ($option['en_font'] as $i => $f) {
            $enFont['en'][] = _ESP_ROOT . $f;
        }
        foreach ($option['cn_font'] as $i => $f) {
            $enFont['cn'][] = _ESP_ROOT . $f;
        }
        $option['en_font'] = array_flip($enFont['en']);
        $option['cn_font'] = array_flip($enFont['cn']);

        self::createCode1($img, $code, $option);

        //没指定code，则保存到cookies中，反之则只显示，不保存
        if (!($option['code'] ?? 0)) {
            $opt = $option['cookies'];
            $opt['attach'] .= date($opt['date']);
            $addContent = strtoupper($opt['attach'] . implode($code));//附加串，有效期最长1小时
            $enCode = password_hash($addContent, PASSWORD_DEFAULT);
            //输出之前先保存Cookies

            if (version_compare(PHP_VERSION, '7.3', '>')) {
                $cok = [];
                $cok['domain'] = _DOMAIN;
                $cok['expires'] = 0;
                $cok['path'] = '/';
                $cok['secure'] = _HTTPS;//仅https
                $cok['httponly'] = true;
                $cok['samesite'] = 'Lax';
                setcookie(strtolower($opt['key'] . $option['source']), $enCode, $cok);
            } else {
                setcookie(strtolower($opt['key'] . $option['source']), $enCode, 0, '/', _DOMAIN, _HTTPS, true);
            }

        }
        return Gd::draw($img, []);
    }

    /**
     * 验证 create 产生的验证码
     * @param array $option
     * @param string $input
     * @return bool
     */
    public static function check(array $option, string $input)
    {
        if (!$input) return false;
        $option += self::$option;
        $ck = $option['cookies'];
        $ck['attach'] .= date($ck['date']);
        $key = strtolower("{$ck['key']}{$option['source']}");
        if (!$cookies = $_COOKIE[$key] ?? null) return false;
        $addContent = strtoupper("{$ck['attach']}{$input}");
        if (version_compare(PHP_VERSION, '7.3', '>')) {
            $cok = [];
            $cok['domain'] = _DOMAIN;
            $cok['expires'] = -1;
            $cok['path'] = '/';
            $cok['secure'] = _HTTPS;//仅https
            $cok['httponly'] = true;
            $cok['samesite'] = 'Lax';
            setcookie($key, null, $cok);
        } else {
            setcookie($key, null, -1, '/', _DOMAIN, _HTTPS, true);
        }
        return password_verify($addContent, $cookies);
    }


    /**
     * @param $img
     * @param $code
     * @param $opt
     */
    private static function createCode1(&$img, &$code, $opt)
    {
        $cn = $opt['charset'] === 'cn';
        $opt['font_size'] = [($opt['size'][1] / 2) - 1, ($opt['size'][1] / 2) + 1];

        //生成画板并填色
        $img = imagecreatetruecolor(...$opt['size']);//容器
        imagefilledrectangle($img,
            0, 0, $opt['size'][0], $opt['size'][1],//座标
            Gd::createColor($img, $opt['b_color']));//画矩形并填充背景色

        //生成验证码
        if (!isset($opt['code']) or !$opt['code']) {
            $_code_len = is_int($opt['length']) ? $opt['length'] : mt_rand(...$opt['length']);
            $code = array_rand(array_flip(str_split(self::$disc[$opt['charset']], $cn ? 3 : 1)), $_code_len);
        } else {
            $cn = !preg_match('/^[a-z0-9]+$/', $opt['code']);
            $code = str_split($opt['code'], $cn ? 3 : 1);
            $_code_len = count($code);
        }

        //生成线条
        for ($i = 0; $i < (is_int($opt['line']) ? $opt['line'] : mt_rand(...$opt['line'])); $i++) {
            imageline($img,
                mt_rand(0, $opt['size'][0]),  //x1
                mt_rand(0, $opt['size'][1]), //y1
                mt_rand(0, $opt['size'][0]),  //x2
                mt_rand(0, $opt['size'][1]), //y2
                Gd::createColor($img, $opt['l_color']));   //颜色
        }

        //生成雪花
        for ($i = 0; $i < (is_int($opt['point']) ? $opt['point'] : mt_rand(...$opt['point'])); $i++) {
            imagettftext($img,
                mt_rand(...$opt['font_size']), 0,
                mt_rand(0, $opt['size'][0]),
                mt_rand(0, $opt['size'][1]),
                Gd::createColor($img, $opt['p_color']),
                array_rand($cn ? $opt['cn_font'] : $opt['en_font']), '*');
        }

        //将验证码画到画板上
        $_x = $opt['size'][0] / $_code_len;//每个字占宽
        for ($i = 0; $i < $_code_len; $i++) {
            $p = abs($_code_len - $_code_len / 2) / $_code_len;
            $x = $_x * ($i + $p) + mt_rand(...$opt['span']);
            imagettftext($img,
                is_int($opt['font_size']) ? $opt['font_size'] : mt_rand(...$opt['font_size']),//字体大小
                is_int($opt['angle']) ? $opt['angle'] : mt_rand(...$opt['angle']),//倾斜角度
                ($x < 5 ? $x + 5 : $x), //XY代表左下角
                $opt['size'][1] * 0.7,      //Y，由于字体可能倾斜，所以不能是绝对底边
                Gd::createColor($img, $opt['c_color']),//字体颜色
                array_rand($cn ? $opt['cn_font'] : $opt['en_font']),  //字体
                $code[$i]);
        }
    }

}

