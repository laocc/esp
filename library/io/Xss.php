<?php
namespace io;
/**
 *
 * XSS过滤
 *
 */
final class Xss
{

    /**
     * 过滤转换
     * @var array
     */
    private static $_never_allowed = [
        'document.cookie' => '[removed-1]',
        'document.write' => '[removed-2]',
        '.parentNode' => '[removed-3]',
        '.innerHTML' => '[removed-4]',
        '-moz-binding' => '[removed-5]',
        '<!--' => '&lt;!--',
        '-->' => '--&gt;',
        '<![CDATA[' => '&lt;![CDATA[',
        '<comment>' => '&lt;comment&gt;'
    ];

    /**
     * 完全过滤
     * @var array
     */
    private static $_never_allowed_regex = array(
        'javascript\s*:',
        '(document|(document\.)?window)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)', // CSS and IE
        'vbscript\s*:', // IE, surprise!
        'wscript\s*:', // IE
        'jscript\s*:', // IE
        'vbs\s*:', // IE
        'Redirect\s+30\d',
        "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
    );


    /**
     * 对传入的内容执行 XSS 过滤
     * 仅适用于过滤传入的内容，不适用于运行时检验
     * @param    string|string[] $str 待检查的内容
     * @param    bool $is_image 图像
     * @return    string
     */
    public static function clear($str, $is_image = FALSE)
    {
        // 判断传入变量是否是数组，数组会针对每个元素执行一次过滤
        if (is_array($str)) {
            return array_map('self::clear', $str);
        }

        // 清除掉字符串中无效的字符
        self::remove_invisible_characters($str);

        /*
         * URL 解码
         * 循环执行以防止多次URL编码的内容
         */
        do {
            $str = rawurldecode($str);
        } while (preg_match('/%[0-9a-f]{2,}/i', $str));

        /*
         * 转换HTML实体
         */
        $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'|\"]).*?\\1/is", function ($match) {
            return str_replace(['>', '<', '\\'], ['&gt;', '&lt;', '\\\\'], $match[0]);
        }, $str);


        /*
         * 所有Tab转换成空格
         */
        $str = str_replace("\t", ' ', $str);

        // 暂存当前过滤结果
        $converted_string = $str;

        /**
         * 删除禁用的字符串
         */
        $str = str_replace(array_keys(self::$_never_allowed), self::$_never_allowed, $str);

        foreach (self::$_never_allowed_regex as &$regex) {
            $str = preg_replace('#' . $regex . '#is', '[removed1]', $str);
        }


        /*
         * 过滤内容以及图片中的PHP标签
         */
        if ($is_image === TRUE) {
            $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
        } else {
            $str = str_replace(array('<?', '?' . '>'), array('&lt;?', '?&gt;'), $str);
        }

        /*
         * 转换全角字符为半角
         */
        $words = ['javascript', 'expression', 'vbscript', 'jscript', 'wscript', 'vbs', 'script', 'base64', 'applet', 'alert', 'document', 'write', 'cookie', 'window', 'confirm', 'prompt'];

        foreach ($words as &$word) {
            $word = implode('\s*', str_split($word)) . '\s*';
            $str = preg_replace_callback('#(' . substr($word, 0, -3) . ')(\W)#is', function ($matches) {
                return preg_replace('/\s+/s', '', $matches[1]) . $matches[2];
            }, $str);
        }

        do {
            $original = $str;
            if (preg_match('/<a/i', $str)) {
                $str = preg_replace_callback('#<a[^a-z0-9>]+([^>]*?)(?:>|$)#si', function ($match) {
                    return str_replace($match[1],
                        preg_replace('#href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|data\s*:)#si',
                            '',
                            self::_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
                        ),
                        $match[0]);
                }, $str);
            }

            if (preg_match('/<img/i', $str)) {
                $str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', function ($match) {
                    return str_replace($match[1],
                        preg_replace('#src=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
                            '',
                            self::_filter_attributes(str_replace(array('<', '>'), '', $match[1]))
                        ),
                        $match[0]);
                }, $str);
            }

            if (preg_match('/script|xss/i', $str)) {
                $str = preg_replace('#</*(?:script|xss).*?>#si', '<pre>', $str);
            }
        } while ($original !== $str);

        unset($original);

        // 移除 evil 的内容
        $str = self::_remove_evil_attributes($str, $is_image);

        /*
         * 过滤HTML
         */
        $naughty = 'alert|prompt|confirm|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|button|select|isindex|layer|link|meta|keygen|object|plaintext|style|script|textarea|title|math|video|svg|xml|xss';
        $str = preg_replace_callback("/<(\/*\s*)({$naughty})([^><]*)([><]*)/is", function ($matches) {
            return '&lt;' . $matches[1] . $matches[2] . $matches[3] . str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[4]);
        }, $str);

        $str = preg_replace('/(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)/is', '\\1\\2&#40;\\3&#41;', $str);

        $str = str_replace(array_keys(self::$_never_allowed), self::$_never_allowed, $str);
        foreach (self::$_never_allowed_regex as &$regex) {
            $str = preg_replace("/{$regex}/is", '[removed3]', $str);
        }
        if ($is_image === TRUE) {
            return ($str === $converted_string);
        }

        return $str;
    }


    /**
     * Filter Attributes
     *
     * Filters tag attributes for consistency and safety.
     *
     * @used-by    CI_Security::_js_img_removal()
     * @used-by    CI_Security::_js_link_removal()
     * @param    string $str
     * @return    string
     */
    private static function _filter_attributes($str)
    {
        $out = '';
        if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)) {
            foreach ($matches[0] as &$match) {
                $out .= preg_replace('#/\*.*?\*/#s', '', $match);
            }
        }
        return $out;
    }

    /**
     * @param    string $str The string to check
     * @param    bool $is_image Whether the input is an image
     * @return    string    The string with the evil attributes removed
     */
    private static function _remove_evil_attributes($str, $is_image)
    {
        $evil_attributes = array('on\w*', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime');

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

        return $str;
    }

    /**
     * 删除传入内容中无效的字符
     *
     * @param    string
     * @param    bool
     */
    private static function remove_invisible_characters(&$str, $url_encoded = TRUE)
    {
        $kill = array();
        if ($url_encoded) {
            $kill[] = '/%0[0-8bcef]/';    // url encoded 00-08, 11, 12, 14, 15
            $kill[] = '/%1[0-9a-f]/';    // url encoded 16-31
        }
        $kill[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/s';    // 00-08, 11, 12, 14-31, 127
        do {
            $str = preg_replace($kill, '', $str, -1, $count);
        } while ($count);
    }


}