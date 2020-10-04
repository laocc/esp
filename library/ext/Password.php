<?php

namespace esp\library\ext;

/**
 * 密码操作类
 * 单向加密：
 * 1：md3        对MD5结果重排；固定结果                   校验：md3_check       用md3_disc_rand生成密码转换因子
 * 2：md4        在MD5两头加随机8位组成新串；结果不固定       校验：md4_check
 * 3：hash       先MD5再hash；结果不固定                   校验：hash_check
 *
 * TODO:MD3的加密排列因子在一套系统中，一旦开始使用，就不可以再次编辑
 *
 * 双向对称加密：
 * 4：base_en    对base64结果重排，支持中文；    反解：base_de         用string_disc_rand生成转换因子
 * 5：str_en     用进制换算加密重排，支持中文；   反解：str_de          转换因子在str_conversion中手工添加
 * 6：int_en     对整数加密                    反解：int_de          转换因子在int_conversion中手工添加
 *
 * 双向不对称加密：
 *
 */
class Password
{
    //*************************************MD3***********************************************************************

    /**
     * MD5，但结果是重排列的
     * @param string $str 要加密的串
     * @param bool|false $double 双重加密，第二重为原生MD5
     * @return string
     */
    public static function md3($str, $double = false)
    {
        //这个排列因子是用md3_disc_rand生成的
        $arr = [
            [6, 5, 28, 10, 16, 14, 20, 24, 0, 21, 29, 11, 2, 19, 31, 15, 18, 13, 9, 22, 1, 23, 27, 30, 7, 17, 8, 25, 3, 12, 4, 26],
            [30, 6, 31, 12, 1, 14, 9, 8, 19, 11, 29, 4, 0, 22, 21, 28, 5, 17, 16, 15, 24, 7, 20, 26, 3, 2, 25, 23, 13, 18, 27, 10],
            [24, 0, 22, 23, 20, 9, 14, 25, 17, 10, 18, 19, 28, 31, 12, 3, 16, 30, 7, 13, 8, 11, 21, 5, 29, 27, 2, 1, 26, 4, 6, 15],
            [29, 26, 6, 27, 9, 1, 15, 13, 16, 14, 21, 3, 20, 22, 28, 2, 8, 4, 17, 10, 18, 12, 19, 11, 0, 31, 7, 23, 25, 5, 24, 30],
            [10, 9, 11, 26, 25, 16, 20, 14, 4, 22, 7, 5, 29, 3, 15, 2, 8, 28, 30, 0, 18, 1, 24, 23, 12, 17, 31, 13, 21, 6, 19, 27],
        ];
        $md = md5($str);  //MD5
        $re = Array();         //重排容器
        $len = (strlen($str) % 10) ?: 0;//字串长度取模10
        $i = ord($md[$len]) % count($arr);    //取第$len个字符的asc码，取模10，得到用哪个排列
//        echo "原生MD5={$md}[{$len}]='" . $md[$len] . "'的asc(" . $md[$len] . ")=" . ord($md[$len]) . "，取5模={$i}字典】";
        //用原字串长度取10模=x，将原串原生md5，取md5下标为x的字符asc码，再取5模=n，得到密码本下标n，最后根据密码规则交换加密串内容的位置
        $arr = $arr[$i];
        foreach ($arr as &$n) $re[] = $md[$n];//按排列因子重排
        unset($arr);
        return !!$double ? md5(implode($re)) : implode($re);
    }

    public static function md3_check($string, $password)
    {
        if (strlen(trim($string)) === 32 and strlen(trim($password)) !== 32) {
            list($string, $password) = [$password, $string];
        }
        if (strlen(trim($password)) !== 32) return false;
        return hash_equals(self::md3($string), $password);
    }

    /**
     * 成生随机排列因子，在PHP页面中执行password::md3_disc_rand(n)得到n组数，替换re_md5里的$arr即可
     * @param int $num 要生成的组数
     */
    public static function md3_disc_rand($num = 10)
    {
        for ($z = 0; $z < $num; $z++) {
            $arr = Array();
            for ($i = 0; $i < 32; $i++) $arr[] = $i;
            shuffle($arr);//随机打乱数组，得到排序因子
            echo '[', implode(',', $arr), '],<br>';
        }
    }


    //****************************************MD4********************************************************************


    /**
     * 在MD5两头各加8位随机字符，总长度48，每次结果都不一样
     * @param $password
     * @return null|string
     */
    public static function md4($password)
    {
        if (empty($password)) return null;
        $salt = '0a1b2c3d4e5f6789';
        $L = $R = '';
        for ($i = 1; $i <= 8; $i++) $L .= $salt[rand(0, 15)];
        for ($i = 1; $i <= 8; $i++) $R .= $salt[rand(0, 15)];
        return $L . md5($R . trim($password) . $L) . $R;
    }

    /**
     * @param $string
     * @param $password
     * @return bool
     * 原理：将密码48位分成6节，md5(第1节+字符串+第6节)=中间4节
     */
    public static function md4_check($string, $password)
    {
        if (strlen(trim($string)) === 48 and strlen(trim($password)) !== 48) {
            list($string, $password) = [$password, $string];
        }

        if (!$password) return false;

        //兼容老版本加密
        if (strlen(trim($password)) === 32) return hash_equals(md5($string), $password);
        if (strlen(trim($password)) === 40) return hash_equals(sha1($string), $password);

        if (strlen($password) !== 48) return false;

        $pwd = str_split($password, 8);
        return hash_equals(md5($pwd[5] . trim($string) . $pwd[0]), $pwd[1] . $pwd[2] . $pwd[3] . $pwd[4]);
    }

    //****************************************HASH********************************************************************

    /**
     * MD3+HASH双重加密
     * @param string $string
     * @param bool $isMd5
     * @param int $type 加密类型：PASSWORD_BCRYPT，PASSWORD_DEFAULT
     * @return bool|string
     */
    public static function hash($string, $type = PASSWORD_DEFAULT)
    {
        $type = ($type === PASSWORD_DEFAULT) ? PASSWORD_DEFAULT : PASSWORD_BCRYPT;
        return password_hash(md5($string), $type);
    }

    /**
     * HASH+MD5，验证
     * @param $string
     * @param $password
     * @return bool
     */
    public static function hash_check($string, $hash)
    {
        if (preg_match('/^\$\d[a-z]\$\d+\$.+/', $string) and strlen($string) === 60 and strlen($hash) !== 60)
            list($string, $hash) = [$hash, $string];
        return password_verify(md5($string), $hash);
    }


    //**************************************** 字符串 加密+解密 ********************************************************************

    //此处正反加解密的结果，原则上不是用于存入数据库的，因为有可能字典会变

    private static $RANDOM = '{#ED#}';
    private static $FACTORLEN = 5;//密码因子组数

    /**
     * 正向加密，可以加密任意字符串，包括中文
     * @param string $String 待加密串
     * @param int $expiration 有效期，天数，=0不限制
     * @return string
     */
    public static function base_en($String, $expiration = 0)
    {
        //创建时间，内容，有效时间
        $str = base64_encode(time() . self::$RANDOM . $String . self::$RANDOM . $expiration . self::$RANDOM . self::s_rand());
        $RS = str_split($str);
        $RS = array_reverse($RS);//倒排
        $index = count($RS) % self::$FACTORLEN;//使用密码库因子
        $arr = self::base_disc($index);
        $new = Array();
        foreach ($RS as &$rs) $new[] = isset($arr[$rs]) ? $arr[$rs] : $rs;
        unset($str, $String, $expiration, $RS, $rs, $arr);
        return implode($new);
    }

    /**
     * 反向解密
     * @param string $String 待解密串
     * @return null
     */
    public static function base_de($String)
    {
        $RS = str_split($String);
        $arr = self::base_disc(count($RS) % self::$FACTORLEN);
        $arr = array_flip($arr);//数组键值对换
        $new = Array();
        foreach ($RS as &$rs) $new[] = isset($arr[$rs]) ? $arr[$rs] : $rs;
        $RS2 = array_reverse($new);//倒排
        $decode = base64_decode(implode($RS2));
        $str = explode(self::$RANDOM, $decode . self::$RANDOM . self::$RANDOM);
        unset($RS, $new, $rs, $decode, $arr);
        if ((int)$str[0] > 0 and (0 === (int)$str[2] or ((int)$str[0] + 86400 * (int)$str[2]) > time())) return $str[1];
        return null;
    }

    /**
     * 加密字典，密码对换因子
     * @param $i
     * @return array|mixed
     * 这个字典是由string_disc_rand生成
     */
    private static function base_disc($i)
    {
        $arr = Array();
        $arr[0] = ['a' => '4', 'b' => 'b', 'c' => 'r', 'd' => '!', 'e' => '@', 'f' => 'W', 'g' => '0', 'h' => 'n', 'i' => 'V', 'j' => 'L', 'k' => '5', 'l' => 'd', 'm' => 'E', 'n' => 'h', 'o' => 'T', 'p' => '$', 'q' => 'S', 'r' => 'j', 's' => 'a', 't' => 'B', 'u' => 's', 'v' => 'G', 'w' => 'I', 'x' => '/', 'y' => 'o', 'z' => 'Q', 'A' => 'g', 'B' => '3', 'C' => 'A', 'D' => 'Y', 'E' => '-', 'F' => 'O', 'G' => 'l', 'H' => 'p', 'I' => 'k', 'J' => 'v', 'K' => 'c', 'L' => '8', 'M' => '_', 'O' => 'i', 'P' => 'e', 'Q' => 'J', 'R' => 'P', 'S' => 'w', 'T' => 'R', 'U' => 'K', 'V' => 'q', 'W' => '2', 'X' => 'F', 'Y' => 'z', 'Z' => '6', '0' => 'm', '1' => 'Z', '2' => 'H', '3' => 'u', '4' => '*', '5' => '.', '6' => 'x', '7' => '%', '8' => 'M', '9' => '+', '+' => 'D', '-' => '9', '*' => 'X', '/' => 'y', '.' => '1', '!' => 'f', '@' => '7', '$' => 'C', '%' => 'U', '_' => 't'];
        $arr[1] = ['a' => 'B', 'b' => 'x', 'c' => '1', 'd' => '2', 'e' => '6', 'f' => 'c', 'g' => 'D', 'h' => 'R', 'i' => 'g', 'j' => '-', 'k' => 'S', 'l' => 'a', 'm' => 'm', 'n' => 'f', 'o' => 'J', 'p' => 'u', 'q' => 'r', 'r' => 'F', 's' => '.', 't' => '3', 'u' => '7', 'v' => 'I', 'w' => 'P', 'x' => '9', 'y' => 'M', 'z' => 'w', 'A' => 'd', 'B' => 'W', 'C' => 'E', 'D' => 'i', 'E' => 'K', 'F' => 'n', 'G' => 'p', 'H' => '+', 'I' => '$', 'J' => '_', 'K' => 'Q', 'L' => 'G', 'M' => 'v', 'O' => '%', 'P' => 'e', 'Q' => 'z', 'R' => 'y', 'S' => 'k', 'T' => 'A', 'U' => 'V', 'V' => 'H', 'W' => '@', 'X' => '0', 'Y' => 'O', 'Z' => '8', '0' => 'j', '1' => 't', '2' => '*', '3' => 'o', '4' => 'U', '5' => '5', '6' => 'Y', '7' => 'l', '8' => 'C', '9' => 'X', '+' => 's', '-' => 'L', '*' => 'q', '/' => '4', '.' => '/', '!' => 'T', '@' => 'b', '$' => 'Z', '%' => '!', '_' => 'h'];
        $arr[2] = ['a' => 'S', 'b' => 't', 'c' => 'q', 'd' => 'x', 'e' => 'T', 'f' => '4', 'g' => 'G', 'h' => '8', 'i' => 'M', 'j' => 'E', 'k' => '/', 'l' => '6', 'm' => '+', 'n' => 'n', 'o' => 'o', 'p' => 'c', 'q' => '$', 'r' => 'V', 's' => 'm', 't' => 'C', 'u' => '!', 'v' => '0', 'w' => '_', 'x' => 'L', 'y' => 'r', 'z' => 'p', 'A' => 'y', 'B' => 'd', 'C' => '.', 'D' => '-', 'E' => 'i', 'F' => 'Z', 'G' => 'f', 'H' => 'h', 'I' => 'v', 'J' => 'P', 'K' => '*', 'L' => 'K', 'M' => 'B', 'O' => 'l', 'P' => 's', 'Q' => 'O', 'R' => 'g', 'S' => 'k', 'T' => 'a', 'U' => '5', 'V' => 'w', 'W' => 'D', 'X' => 'A', 'Y' => 'W', 'Z' => '%', '0' => 'z', '1' => '9', '2' => 'U', '3' => 'F', '4' => 'H', '5' => 'u', '6' => 'Y', '7' => 'X', '8' => 'J', '9' => '1', '+' => 'e', '-' => '@', '*' => 'Q', '/' => '7', '.' => 'R', '!' => '3', '@' => 'b', '$' => 'I', '%' => '2', '_' => 'j'];
        $arr[3] = ['a' => 'K', 'b' => 'G', 'c' => 'J', 'd' => 'A', 'e' => '1', 'f' => 'Y', 'g' => 'k', 'h' => 'o', 'i' => '-', 'j' => '9', 'k' => 'z', 'l' => 'f', 'm' => 'P', 'n' => 'l', 'o' => '8', 'p' => 'b', 'q' => '3', 'r' => 's', 's' => '*', 't' => 'R', 'u' => '5', 'v' => '0', 'w' => '$', 'x' => 'n', 'y' => 'h', 'z' => 'Q', 'A' => '_', 'B' => 'v', 'C' => 'i', 'D' => 't', 'E' => '/', 'F' => 'M', 'G' => '4', 'H' => 'W', 'I' => 'H', 'J' => 'E', 'K' => 'e', 'L' => 'B', 'M' => 'u', 'O' => 'X', 'P' => '.', 'Q' => 'Z', 'R' => 'L', 'S' => 'd', 'T' => '+', 'U' => '6', 'V' => 'T', 'W' => 'F', 'X' => '7', 'Y' => 'r', 'Z' => 'm', '0' => 'V', '1' => 'y', '2' => 'g', '3' => 'C', '4' => 'D', '5' => 'j', '6' => '!', '7' => 'I', '8' => 'O', '9' => 'U', '+' => '@', '-' => 'S', '*' => 'c', '/' => '2', '.' => '%', '!' => 'w', '@' => 'q', '$' => 'x', '%' => 'p', '_' => 'a'];
        $arr[4] = ['a' => '%', 'b' => 'd', 'c' => 'M', 'd' => '3', 'e' => 'n', 'f' => '_', 'g' => '5', 'h' => 't', 'i' => 'E', 'j' => '@', 'k' => 'S', 'l' => 'Y', 'm' => 'o', 'n' => 'c', 'o' => 'I', 'p' => '1', 'q' => 'a', 'r' => '0', 's' => 'r', 't' => 'L', 'u' => 'K', 'v' => '2', 'w' => '/', 'x' => 'w', 'y' => '7', 'z' => '-', 'A' => 'W', 'B' => 'p', 'C' => '4', 'D' => 'v', 'E' => 'X', 'F' => 'D', 'G' => 'V', 'H' => 'l', 'I' => 'k', 'J' => 'u', 'K' => 'B', 'L' => 'h', 'M' => '*', 'O' => 'y', 'P' => '.', 'Q' => 'x', 'R' => '6', 'S' => 'F', 'T' => 'g', 'U' => '!', 'V' => 'i', 'W' => 'G', 'X' => 'Z', 'Y' => 'z', 'Z' => 'q', '0' => 'Q', '1' => 's', '2' => 'U', '3' => 'J', '4' => '9', '5' => '+', '6' => 'H', '7' => 'b', '8' => '$', '9' => 'C', '+' => 'm', '-' => 'P', '*' => 'T', '/' => 'O', '.' => 'f', '!' => 'A', '@' => 'j', '$' => 'e', '%' => 'R', '_' => '8'];
        return isset($arr[$i]) ? $arr[$i] : [];
    }

    /**
     * 生成字典，这个程序不调用，仅为以后更新字典而用，字典里特别注意不能有#&这两个符号
     * @param int $len
     */
    public static function string_disc_rand($len = 10)
    {
        $abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMOPQRSTUVWXYZ0123456789+-*/.!@$%_';
        for ($l = 0; $l < $len; $l++) {
            $aaa = str_shuffle($abc);
            $a = str_split($abc);
            $b = str_split($aaa);
            $str = Array();
            foreach ($a as $i => &$ab) {
                $str [] = "'{$ab}'=>'{$b[$i]}'";
            }
            echo "$" . "arr[{$l}]=[" . (implode(',', $str)) . "];\n";
        }
    }

    //**************************************** 字符串 加密+解密 ********************************************************************
    private static $str_disc_len = 2;//转换因子有几组

    /**
     * 正向加密
     * @param $str
     * @return string
     */
    public static function str_en(string $str): string
    {
        $Hex = 36;//采用多少进制加密，不得小于16进制
        $Len = 4;//加密结果每字节长度，这要根据进制度计算，进制越小，每节长度就越长，36位时4位长正好，16进制时要5位，
        $nS = base64_encode($str);
        $ordS = Array();//换取ASC值
        $tabI = strlen($nS) % self::$str_disc_len;
        for ($i = 0; $i < strlen($nS); $i++) $ordS[] = self::str_conversion(ord($nS[$i]), $tabI, true, $Hex, $Len);
        return implode('', $ordS);
    }

    /**
     * 逆向解密
     * @param $str
     * @return string
     */
    public static function str_de(string $str): string
    {
        $Hex = 36;//采用多少进制加密，不得小于16进制
        $Len = 4;//加密结果每字节长度，这要根据进制度计算
        $eS = str_split($str, $Len);
        $ascS = Array();
        $tabI = count($eS) % self::$str_disc_len;
        foreach ($eS as $i => &$vS) $ascS[] = self::str_conversion($vS, $tabI, false, $Hex, $Len);
        return base64_decode(implode($ascS));
    }

    /**
     * 加密解密算法
     * @param string $od 字符ORD值或字符
     * @param int $ai 采用第几套加密字典
     * @param bool|true $en 加密还是解密
     * @param int $Hex 进制
     * @param int $pdLen 每节长度
     * @return string
     */
    private static function str_conversion($od, $ai = 0, $en = true, $Hex = 36, $pdLen = 4)
    {
        $arr = [[4, 7, 1, 9, 3, 5, 2, 6, 0, 8], [6, 3, 8, 1, 0, 9, 2, 7, 5, 4]];
        if ($en) {//加密
            $arr = $arr[$ai];//取一个库表
            $str = (string)pow(intval($od), 2);//加平方
            for ($i = 0; $i < strlen($str); $i++) $str[$i] = $arr[$str[$i]];
            $str = ((mt_rand() % 9) + 1) . $str;//防止前边出现0，要确保这儿产生的值为1-9之间
            $str = base_convert($str, 10, $Hex);//进制转换
            return substr('00' . $str, 0 - $pdLen);//转为16进制，并返回5位长，不够的前边补0
        } else {//解密
            $arr = array_flip($arr[$ai]);//转码库键值对换
            $str = base_convert($od, $Hex, 10);//进制转回10
            $str = substr($str, 1);//再去掉前面1个数字
            for ($i = 0; $i < strlen($str); $i++) $str[$i] = $arr[$str[$i]];
            return chr(sqrt($str));//先开方根再转为asc
        }
    }

    //**************************************** 整型 加密+解密 ********************************************************************
    private static $int_disc_len = 2;//整型转换因子有几组

    /**
     * 正向加密整型，只能接受整型
     * @param int $int
     * @return string
     */
    public static function int_en($int)
    {
        $ordS = Array();//换取ASC值
        $nS = base_convert($int, 10, 8);//进制转换
        $tabI = strlen($nS) % self::$int_disc_len;
        for ($i = 0; $i < strlen($nS); $i++) $ordS[] = self::int_conversion((string)ord($nS[$i]), $tabI, true);
        return (int)implode($ordS);
    }

    /**
     * en_int的解密
     * @param int $int
     * @return int
     */
    public static function int_de($int)
    {
        $eS = str_split($int, 4);
        $ascS = Array();
        $tabI = count($eS) % self::$int_disc_len;
        foreach ($eS as $i => &$vS) $ascS[] = self::int_conversion($vS, $tabI, false);
        return (int)base_convert(implode($ascS), 8, 10);
    }

    private static function int_conversion($str, $ai = 0, $en = true)
    {
        //转换因子，将0-9打乱即可
        $arr = [[6, 8, 4, 1, 9, 0, 5, 2, 7, 3], [2, 8, 7, 5, 9, 3, 1, 0, 4, 6]];
        if ($en) {//加密
            $arr = $arr[$ai];//取一个库表
            for ($i = 0; $i < strlen($str); $i++) $str[$i] = $arr[$str[$i]];
//            $str = ((mt_rand() % 9) + 1) . $str;
            $str = base_convert($str, 10, 9);//进制转换，转为9进制
            return substr('0000' . $str, 0 - 4);//返回4位长，不够的前边补0
        } else {//解密
            $arr = array_flip($arr[$ai]);//转码库键值对换
            $str = base_convert($str, 9, 10);//进制转回10,再去掉前面1个数字
            $str = substr($str, 1);
            for ($i = 0; $i < strlen($str); $i++) $str[$i] = $arr[$str[$i]];
            return chr($str);//转为asc
        }
    }

    //****************************************END********************************************************************

    /**
     * 生成随机字符串
     * @param int $min 生成的长度，或在有max时为最小长度
     * @param int $max 若给定此值，则生成$min-$max之间的随机长度
     * @return string
     */
    private static function s_rand($min = 0, $max = 0)
    {
        $factor = 'aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ1234567890';
        $rLen = $max === 0 ? ($min === 0 ? mt_rand(1, 10) : $min) : mt_rand($min, $max);//计算生成的长度
        $retStr = Array();
        for ($i = 0; $i < $rLen; $i++) $retStr[] = $factor[mt_rand(0, 51)];
        return implode($retStr);
    }

    /**
     * //****************************************RSA********************************************************************
     */

    public static function create_ssl($len = 1024)
    {
        if (!in_array($len, [512, 1024, 2048, 4096])) return null;

        //创建公钥和私钥   返回资源
        $res = openssl_pkey_new(array(
            //"digest_alg" => "sha512",
            "private_key_bits" => $len,                     //字节数    512 1024  2048   4096 等
            "private_key_type" => OPENSSL_KEYTYPE_RSA,     //加密类型
        ));

        //从得到的资源中获取私钥
        openssl_pkey_export($res, $prvKey);

        //从得到的资源中获取公钥
        $pubKey = openssl_pkey_get_details($res);
        return ['public' => $pubKey['key'], 'private' => $prvKey];
    }


    /**
     * 加密
     * @return string 密文
     */
    public static function ssl_encode($data, $pubKey = null)
    {
        $len = 117;
        if (is_array($data)) {
            ksort($data);
            $data = http_build_query($data);
        }

        $strArray = str_split($data, $len);
        if (is_null($pubKey)) $pubKey = self::ssl('public');
//        $pubKey = self::replaceKey('public', $pubKey);

        // 转换为openssl格式密钥
        $res = openssl_get_publickey($pubKey);
        $ret = '';
        foreach ($strArray as $cip) {
            if (openssl_public_encrypt($cip, $result, $res, OPENSSL_PKCS1_PADDING)) $ret .= $result;
        }
        return base64_encode($ret);
    }


    /**
     * 解密
     * @return string
     */
    public static function ssl_decode($data, $priKey = null)
    {
        $ret = '';
        $data = base64_decode($data);
        if ($data !== false) {
            $strArray = str_split($data, 128);
            if (is_null($priKey)) $priKey = self::ssl('private');
//            $priKey = self::replaceKey('private', $priKey);

            // 转换为openssl格式密钥
            $res = openssl_get_privatekey($priKey);

            foreach ($strArray as $cip) {
                if (openssl_private_decrypt($cip, $result, $res, OPENSSL_PKCS1_PADDING)) $ret .= $result;
            }
        }
        return $ret;
    }


    /**
     * 针对公钥和私钥进行修改
     * @param string $str 原字符串
     * @param string $type PRIVATE: 私钥，PUBLIC：公钥
     * @return string
     */
    private static function replaceKey($type, $strKey)
    {
        $rsa = trim(str_replace(['-----', ' ', 'BEGIN PUBLIC KEY', 'END PUBLIC KEY', 'BEGIN RSA PUBLIC KEY', 'END RSA PUBLIC KEY', 'BEGIN PRIVATE KEY', 'END PRIVATE KEY', 'BEGIN RSA PRIVATE KEY', 'END RSA PRIVATE KEY', "\n", "\r"], '', $strKey));
        $rsa = chunk_split($rsa, 64, "\n");
        $type = (strtoupper($type) === 'PRIVATE') ? "RSA PRIVATE" : 'PUBLIC';
        return "-----BEGIN {$type} KEY-----\n{$rsa}-----END {$type} KEY-----";
    }


    private static function ssl($type)
    {
        $key = Array();
        $key['public'] = <<<PUB
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC0bIp1HvBiYXqOOHFwQObud1VQ
XwobDwo7p1Dv9L8Vuw7lwTURfJGnZE8z22YQK68Q3UM16BHmLK17IMfgPWz1xMJx
Qd9kQBI7JMUhNxkynVKRyl9KBGj8+qrhbQeviGrnENktMlrF6P/Y6xC7rLsezXCo
dU8Br3wTauq1PJs+9wIDAQAB
-----END PUBLIC KEY-----
PUB;
        $key['private'] = <<<PRV
-----BEGIN PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBALRsinUe8GJheo44
cXBA5u53VVBfChsPCjunUO/0vxW7DuXBNRF8kadkTzPbZhArrxDdQzXoEeYsrXsg
x+A9bPXEwnFB32RAEjskxSE3GTKdUpHKX0oEaPz6quFtB6+IaucQ2S0yWsXo/9jr
ELusux7NcKh1TwGvfBNq6rU8mz73AgMBAAECgYA6xAes8jDVum5SmGvKZjlRfqjZ
DruZ6KIqn+qq3YrHpy/JAc8pLOu8G934RAPQTIou8gKMMlRyJiczLZAsp5UvQ6y3
qng1M1N5Ay3qw+eJNNJ/5/1BUrWcQ9GVtoqxxbGb102WGnOHGsi1KURg8a9hmzg3
ybStcFAFbfYXhTf+4QJBANodkMJsFmLqBuGcrMTHJq0uaqghVnXPq1m1ARCtXeXf
BhpZwYRVHFs6VB7UsAPfSh3HID3OwjDOOlcXQfKHer8CQQDTwwjylWRR9al/NQxN
c2XCqvGwCCjakxhJIFxdmF3EX6235tq1myWaueLk6+HylkfVqGSG42Q0hYfvD8G7
JuHJAkAVLiHMf2p9GHSqm+g7A4vB7Wv1/6bBsfTVtTnxQKSJmeqhzG16SDmEsllQ
1R5ygdBUq+15kwZQbvZDVsLUpktJAkEAlg8qQwTcLzwfXVZRHn08yiLq26wcjy8X
UZKOAYEcSTDn4F7F81U645cAEuqYlZgq1VvbyL5QXuuU9TI8PYwDCQJANEZka1Gl
aygu0PJyIU149bS/4doZeoAc2ZZ9Xhbj+YaDN3+p0w5UsDbRWzdTHQUKGx9UKJPu
Gk/hN7PYeEhd5A==
-----END PRIVATE KEY-----
PRV;
        return $key[strtolower($type)] ?? null;
    }

}
