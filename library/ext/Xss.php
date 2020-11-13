<?php

namespace esp\library\ext;


/**
 *
 * XSS过滤
 *
 */
final class Xss
{

    /**
     * 无条件过滤转换
     * @var array
     */
    private static $_never_allowed = [
        'document.cookie' => '[removed-1]',
        'document.write' => '[removed-2]',
        '.parentNode' => '[kill-3]',
        '.innerHTML' => '[removed-4]',
        '-moz-binding' => '[removed-5]',
        '<!--' => '&lt;!--',
        '-->' => '--&gt;',
        '<![CDATA[' => '&lt;![CDATA[',
        '<comment>' => '&lt;comment&gt;'
    ];

    /**
     * 完全过滤，正则法匹配
     * @var array
     */
    private static $_never_allowed_regex = [
        '/javascript\s*\:/is' => '',
        '/(document|(document\.)?window)\.(location|on\w*)/is' => '',
        '/expression\s*(\(|&\#40;)/is' => '', // CSS and IE
        '/vbscript\s*\:/is' => '', // IE, surprise!
        '/wscript\s*\:/is' => '', // IE
        '/jscript\s*\:/is' => '', // IE
        '/vbs\s*\:/is' => '', // IE
        '/Redirect\s+30\d/is' => '',
        '/([\"\'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?/is' => '',
    ];


    /**
     * 对传入的内容执行 XSS 过滤
     * 仅适用于过滤传入的内容，不适用于运行时检验
     * @param string $str
     * @param bool $is_image
     * @return bool =true仅表示str被改动过
     */
    public static function clear(string &$str, bool $is_image = false): bool
    {
        if (is_array($str)) return array_map('self::clear', $str);
        $str = trim($str);
        if (empty($str)) return false;
        if (is_numeric($str)) return false;
        if (preg_match('/^\s*[\w\-\.\%]+\s*$/', $str)) return false;

        //暂存当前过滤结果，用于后面对比是否被修改过
        $converted_string = $str;

        //清除掉字符串中无效的字符
        $kill = Array();
        $kill[] = '/%0[0-8bcef]/i';    // url encoded 00-08, 11, 12, 14, 15
        $kill[] = '/%1[0-9a-f]/i';    // url encoded 16-31
        $kill[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/s';    // 00-08, 11, 12, 14-31, 127
        do {
            $str = preg_replace($kill, '', $str, -1, $count);
        } while ($count);

        //URL 解码,循环执行以防止多次URL编码的内容
        do {
            $str = rawurldecode($str);
        } while (preg_match('/%[0-9a-f]{2,}/i', $str));

        //转换HTML实体
        $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'|\"]).*?\\1/is", function ($match) {
            return str_replace(['>', '<', '\\'], ['&gt;', '&lt;', '\\\\'], $match[0]);
        }, $str);

        //所有Tab转换成空格
        $str = str_replace("\t", ' ', $str);

        //删除禁用的字符串
        $str = str_replace(array_keys(self::$_never_allowed), self::$_never_allowed, $str);
        foreach (self::$_never_allowed_regex as $regex => $to) {
            $str = preg_replace($regex, $to, $str);
        }

        /*
         * 过滤内容以及图片中的PHP标签
         */
        if ($is_image === true) {
            $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
        } else {
            $str = str_replace(['<?', '?' . '>'], ['&lt;?', '?&gt;'], $str);
        }

        /*
         * 转换全角字符为半角
         */
        $words = ['javascript', 'expression', 'vbscript', 'jscript', 'wscript', 'vbs', 'script', 'base64', 'applet', 'alert', 'document', 'write', 'cookie', 'window', 'confirm', 'prompt'];
        foreach ($words as &$word) {
            $word = implode('\s*', str_split($word)) . '\s*';
            $str = preg_replace_callback('/(' . substr($word, 0, -3) . ')(\W)/is', function ($matches) {
                return preg_replace('/\s+/s', '', $matches[1]) . $matches[2];
            }, $str);
        }

        do {
            $original = $str;
            if (preg_match('/<a/i', $str)) {
                $str = preg_replace_callback('/<a[^a-z0-9>]+([^>]*?)(?:>|$)/is', function ($match) {
                    return str_replace($match[1],
                        preg_replace('/href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|data\s*:)/is',
                            '',
                            self::_filter_attributes(str_replace(['<', '>'], '', $match[1]))
                        ),
                        $match[0]);
                }, $str);
            }

            if (preg_match('/<img/i', $str)) {
                $str = preg_replace_callback('/\<img[^a-z0-9]+([^>]*?)(?:\s\?\/\?\>\|\$)/is', function ($match) {
                    return str_replace($match[1],
                        preg_replace('/src=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)/is',
                            '',
                            self::_filter_attributes(str_replace(['<', '>'], '', $match[1]))
                        ),
                        $match[0]);
                }, $str);
            }

            $str = preg_replace('/\<.*?(script|xss).*?\>/is', '<pre>', $str);
        } while ($original !== $str);
        unset($original);

        // 移除 evil 的内容
        self::_remove_evil_attributes($str, $is_image);

        return ($str === $converted_string);
    }

    private static function _filter_attributes(string $str)
    {
        $out = '';
        if (preg_match_all('/\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1/is', $str, $matches)) {
            foreach ($matches[0] as &$match) {
                $out .= preg_replace('#/\*.*?\*/#s', '', $match);
            }
        }
        return $out;
    }


    private static function _remove_evil_attributes(string &$str, $is_image)
    {
        $evil_attributes = ['on\w*', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime'];
        if ($is_image === TRUE) {
            unset($evil_attributes[array_search('xmlns', $evil_attributes)]);
        }
        do {
            $count = $temp_count = 0;

            // replace occurrences of illegal attribute strings with quotes (042 and 047 are octal quotes)
            $str = preg_replace('/(<[^>]+)(?<!\w)(' . implode('|', $evil_attributes) . ')\s*=\s*(\042|\047)([^\\2]*?)(\\2)/is', '$1[removed4]', $str, -1, $temp_count);
            $count += $temp_count;

            // find occurrences of illegal attribute strings without quotes
            $str = preg_replace('/(<[^>]+)(?<!\w)(' . implode('|', $evil_attributes) . ')\s*=\s*([^\s>]*)/is', '$1[removed5]', $str, -1, $temp_count);
            $count += $temp_count;
        } while ($count);
    }


}