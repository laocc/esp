<?php

namespace esp\library\ext;


/**
 * 解析md代码：Markdown::html(mdCode);
 * Class Markdown
 * @package tools
 */
class Markdown
{
    /**
     * _whiteList
     *
     * @var string
     */
    private static $_commonWhiteList = 'kbd|b|i|strong|em|sup|sub|br|code|del|a|hr|small';

    /**
     * _specialWhiteList
     *
     * @var mixed
     * @access private
     */
    private static $_specialWhiteList = ['table' => 'table|tbody|thead|tfoot|tr|td|th', 'div' => ''];

    /**
     * _footnotes
     *
     * @var array
     */
    private static $_footnotes;

    /**
     * _blocks
     *
     * @var array
     */
    private static $_blocks;

    /**
     * _current
     *
     * @var string
     */
    private static $_current;

    /**
     * _pos
     *
     * @var int
     */
    private static $_pos;

    /**
     * _definitions
     *
     * @var array
     */
    private static $_definitions;

    /**
     * @var array
     */
    private static $_hooks = Array();

    /**
     * @var array
     */
    private static $_holders;

    /**
     * @var string
     */
    private static $_uniqid;

    /**
     * @var int
     */
    private static $_id;


    /**
     * 所有锚点
     * @var array
     * self::$href[] = ['lv' => $num, 'name' => $name, 'title' => $line];
     */
    private static $href = Array();

    private static $addNav = false;

    /**
     * makeHtml
     * @param string $text
     * @param bool $addNav
     * @param bool $addBoth
     * @return string
     */
    public static function html(string $text, bool $addNav = false, bool $addBoth = true)
    {
        self::$_footnotes = Array();
        self::$_definitions = Array();
        self::$_holders = Array();
        self::$_html = Array();
        self::$_uniqid = md5(uniqid());
        self::$_id = 0;
        self::$addNav = $addNav;
        self::replaceHtml($text);//优先处理<<<html>>>
        $text = str_replace(["\t", "\r"], ['    ', ''], $text);
        $html = self::parse($text);
        $html = self::makeFootnotes($html);
        self::joinHtml($html);
        return ($addNav ? self::makeNav() : '') .
            "<article class='markdown'>{$html}</article>" .
            ($addBoth ? '<div style="display: block;width:100%;height:100px;clear: both;"></div>' : '');
    }

    private static $_html = Array();

    /**
     * 处理<<<HTML代码>>>
     * @param $text
     */
    private static function replaceHtml(string &$text)
    {
        $text = preg_replace_callback("/\<{3}(?<pre>\w*)\s+(?<html>.+?)\>{3}/is", function ($matches) {
            $id = mt_rand();
            self::$_html[$id] = $matches['html'];
            return "[html:{$id}]" . (!!$matches['pre'] ? ("相关源码如下：\n```{$matches['pre']}\n{$matches['html']}\n```\n") : '');
        }, $text);
    }

    /**
     * 将HTML直接显示部分加入进来。
     * @param $html
     */
    private static function joinHtml(&$html)
    {
        $html = preg_replace_callback("/\((br|hr){1}\)/i", function ($matches) {
            return "<{$matches[1]}>";
        }, $html);

        $html = preg_replace_callback("/\[html\:(\d+)\]/i", function ($matches) {
            return self::$_html[$matches[1]] . '<br>';
        }, $html);
    }


    private static function makeNav(): string
    {
        if (!self::$href) return '';
        $nav = count(self::$href) > 35 ? 'nav navMore' : 'nav';
        $html = Array();
        $html[] = "<ol class='{$nav}'>";
        $html[] = "<li><a href='#' target='_self'>Top</a></li>";

        foreach (self::$href as $i => &$li) {
            $html[] = "<li><a href='#{$li["name"]}' target='_self'>{$li['title']}</a></li>";
        }
        $html[] = "</ol>";
        return implode($html);
    }

    /**
     * @param $type
     * @param $callback
     */
    private static function hook($type, $callback)
    {
        self::$_hooks[$type][] = $callback;
    }

    /**
     * @param $str
     * @return string
     */
    private static function makeHolder($str)
    {
        $key = "|\r" . self::$_uniqid . self::$_id . "\r|";
        self::$_id++;
        self::$_holders[$key] = $str;

        return $key;
    }

    /**
     * @param $html
     * @return string
     */
    private static function makeFootnotes($html)
    {
        if (count(self::$_footnotes) > 0) {
            $html .= '<div class="footnotes"><hr><ol class="foot">';
            $index = 1;

            while ($val = array_shift(self::$_footnotes)) {
                if (is_string($val)) {
                    $val .= " <a href=\"#fnref-{$index}\" class=\"footnote-backref\">&#8617;</a>";
                } else {
                    $val[count($val) - 1] .= " <a href=\"#fnref-{$index}\" class=\"footnote-backref\">&#8617;</a>";
                    $val = count($val) > 1 ? self::parse(implode("\n", $val)) : self::parseInline($val[0]);
                }

                $html .= "<li id=\"fn-{$index}\">{$val}</li>";
                $index++;
            }

            $html .= '</ol></div>';
        }

        return $html;
    }

    /**
     * parse
     *
     * @param string $text
     * @return string
     */
    private static function parse($text)
    {
        $blocks = self::parseBlock($text, $lines);
        $html = Array();
        foreach ($blocks as &$block) {
            list ($type, $start, $end, $value) = $block;
            $extract = array_slice($lines, $start, $end - $start + 1);
            $method = 'parse' . ucfirst($type);

            $extract = self::call('before' . ucfirst($method), $extract, $value);
            $result = self::$method($extract, $value);
            $result = self::call('after' . ucfirst($method), $result, $value);

            $html[] = $result;
        }

        return implode($html);
    }

    /**
     * @param $type
     * @param $value
     * @return mixed
     */
    private static function call($type, $value)
    {
        if (empty(self::$_hooks[$type])) {
            return $value;
        }

        $args = func_get_args();
        $args = array_slice($args, 1);

        foreach (self::$_hooks[$type] as &$callback) {
            $value = call_user_func_array($callback, $args);
            $args[0] = $value;
        }

        return $value;
    }

    /**
     * @param $text
     * @param $clearHolders
     * @return string
     */
    private static function releaseHolder($text, $clearHolders = true)
    {
        $deep = 0;
        while (strpos($text, "|\r") !== false && $deep < 10) {
            $text = str_replace(array_keys(self::$_holders), array_values(self::$_holders), $text);
            $deep++;
        }

        if ($clearHolders) {
            self::$_holders = Array();
        }

        return $text;
    }

    /**
     * parseInline
     *
     * @param string $text
     * @param string $whiteList
     * @param bool $clearHolders
     * @param bool $enableAutoLink
     * @return string
     */
    private static function parseInline($text, $whiteList = '', $clearHolders = true, $enableAutoLink = true)
    {
        $text = self::call('beforeParseInline', ($text));
//        $text = str_replace('\\', '\\\\', $text);


        //单行重点备注
//        $text = preg_replace_callback("/(^|[^\\\])(\!{1})(.+?)\\2/", function ($matches) {
//            return $matches[1] . self::makeHolder('<span class="important">' . htmlspecialchars($matches[3]) . '</span>');
//        }, $text);

        // 单行`#000;#fff;value`注释,颜色分别为字体色、背景色，背景色可直接省略，但若省略字体色，必须有分号。
        $cp = '\#(?:[a-f0-9]{3}|[a-f0-9]{6})';//颜色的正则表达式
        $text = preg_replace_callback("/(?<hd>^|[^\\\])(`)(?<color>{$cp})?(?<fh>\;?)(?<bg>{$cp})?(?:\;?)(?<imp>\!?)(?<val>.+?)\\2/i", function ($matches) {
            $color = !!$matches['color'] ? "color:{$matches['color']};" : null;
            $bgcolor = !!$matches['bg'] ? "background:{$matches['bg']};" : null;
            $style = (!!$color or !!$bgcolor) ?
                "style='{$color}{$bgcolor}'" :
                (!!$matches['fh'] ?
                    '' :
                    (!!$matches['imp'] ? "class='important'" : "class='code'")
                );

            //htmlspecialchars
            $span = htmlspecialchars($matches['val']);
//            if (\esp\helper\is_url($span)) $span = "<a href='{$span}' target='_blank'>{$span}</a>";
//            else if (\esp\helper\is_domain($span)) $span = "<a href='http://{$span}' target='_blank'>{$span}</a>";

            return $matches['hd'] . self::makeHolder("<span {$style} data-line='314'>{$span}</span>");
        }, $text);

//        // 单行``注释
        $text = preg_replace_callback("/(^|[^\\\])\<(?<style>[\w\;\-\:\#\.\% ]+?)?\>(?<val>.+?)\<\/\>/", function ($matches) {
            return $matches[1] . self::makeHolder("<span style='{$matches['style']}' data-line='324'>" . htmlspecialchars($matches['val']) . '</span>');
        }, $text);

        //@@@
        $text = preg_replace_callback("/(^|[^\\\])(\@{3})\s*(.+?)\s*\\2/", function ($matches) {
            return $matches[1] . self::makeHolder('<div class="notes"><h2>Notes:</h2><div>' . htmlspecialchars($matches[3]) . '</div></div>');
        }, $text);

        //<name>锚链，链接到锚链：[title](#name)
        $text = preg_replace_callback("/(^|[^\\\])\<:([\w]+?)\>/", function ($matches) {
            return $matches[1] . self::makeHolder("<a name='{$matches[2]}' target='_self'></a>");
        }, $text);

        // 加载文件
        $text = preg_replace_callback("/<(?:file|include|load)\:(.+?)>/i", function ($matches) {
            $file = _ROOT . '/' . trim($matches[1], '/"\'');
            if (!is_file($file)) return $file;
            return self::makeHolder(file_get_contents($file));
        }, $text);

        // [tag] => <tag>
        $text = preg_replace_callback("/\[([a-z]+)\]/i", function ($matches) {
            return "<{$matches[1]}>";
        }, $text);

        // link
        $text = preg_replace_callback("/<(?:href)\:(.+?)>/i", function ($matches) {
            $url = str_replace(['_HTTP', '_DOMAIN'], [_HTTP_, _DOMAIN], $matches[1]);
//            return ("<a href=\"{$url}\" data-typ='349' target='_blank'>{$url}</a>");
            return self::makeHolder("<a href=\"{$url}\" data-typ='349' target='_blank'>{$url}</a>");
        }, $text);

        // link
        $text = preg_replace_callback("/<(https?:\/\/.+)>/i", function ($matches) {
            return self::makeHolder("<a href=\"{$matches[1]}\" data-typ='349' target='_blank'>{$matches[1]}</a>");
        }, $text);

        // encode unsafe tags
        $text = preg_replace_callback("/<(\/?)([a-z0-9-]+)(\s+[^>]*)?>/i", function ($matches) use ($whiteList) {
            if (stripos('|' . self::$_commonWhiteList . '|' . $whiteList . '|', '|' . $matches[2] . '|') !== false) {
                return self::makeHolder($matches[0]);
            } else {
                $cod = str_replace('\\\\', '\\', $matches[0]);
                return htmlspecialchars($matches[0]);
            }
        }, $text);

        // 加载文件
        $text = preg_replace_callback("/<([a-z]{2,10})\:(.+)>/i", function ($matches) {
            $file = _ROOT . '/' . trim($matches[2], '/"\'');
            if (!is_file($file)) return $file;
            return self::makeHolder("<p>{$file}:</p><pre class='{$matches[1]}'>" . file_get_contents($file) . "</pre>");
        }, $text);

        $text = str_replace(['<', '>'], ['&lt;', '&gt;'], $text);

        // footnote
        $text = preg_replace_callback("/\[\^((?:[^\]]|\\]|\\[)+?)\]/", function ($matches) {
            $id = array_search($matches[1], self::$_footnotes);

            if (false === $id) {
                $id = count(self::$_footnotes) + 1;
                self::$_footnotes[$id] = self::parseInline($matches[1], '', false);
            }
            return self::makeHolder("<sup id=\"fnref-{$id}\"><a href=\"#fn-{$id}\" target=\"_self\" class=\"footnote-ref\">[{$id}]</a></sup>");
        }, $text);

        // image
        $text = preg_replace_callback("/!\[((?:[^\]]|\\]|\\[)*?)\]\(((?:[^\)]|\\)|\\()+?)\)/", function ($matches) {
            $escaped = self::escapeBracket($matches[1]);
            $url = self::escapeBracket($matches[2]);
            return self::makeHolder("<img src=\"{$url}\" alt=\"{$escaped}\" title=\"{$escaped}\">");
        }, $text);

        $text = preg_replace_callback("/!\[((?:[^\]]|\\]|\\[)*?)\]\[((?:[^\]]|\\]|\\[)+?)\]/", function ($matches) {
            $escaped = self::escapeBracket($matches[1]);

            $result = isset(self::$_definitions[$matches[2]]) ?
                ("<img src=\"" . self::$_definitions[$matches[2]] . "\" alt=\"{$escaped}\" title=\"{$escaped}\">")
                : $escaped;

            return self::makeHolder($result);
        }, $text);

        // link
        $text = preg_replace_callback("/\[((?:[^\]]|\\]|\\[)+?)\]\(((?:[^\)]|\\)|\\()+?)\)/", function ($matches) {
            $escaped = self::parseInline(self::escapeBracket($matches[1]), '', false, false);
            $url = self::escapeBracket($matches[2]);
            $target = '';
            if ($url[0] === '#') {
                $target = ' target="_self"';
                $url = substr($url, 1);
            } else if ($url[0] === '&') {
                $target = ' target="parent"';
                $url = substr($url, 1);
            }
            return self::makeHolder("<a href=\"{$url}\" {$target} data-typ='397'>{$escaped}</a>");
        }, $text);

        $text = preg_replace_callback("/\[((?:[^\]]|\\]|\\[)+?)\]\[((?:[^\]]|\\]|\\[)+?)\]/", function ($matches) {
            $escaped = self::parseInline(self::escapeBracket($matches[1]), '', false, false);
            $result = isset(self::$_definitions[$matches[2]]) ?
                ("<a href=\"" . self::$_definitions[$matches[2]] . "\" data-typ='403'>{$escaped}</a>") : $escaped;

            return self::makeHolder($result);
        }, $text);

        // escape
        $text = preg_replace_callback("/\\\(x80-xff|.)/", function ($matches) {
            return self::makeHolder(htmlspecialchars($matches[1]));
        }, $text);

        // 连续---
        $text = preg_replace_callback("#(-{3,20})#", function ($matches) {
            $l = strlen($matches[1]);
            return $l === 3 ? '<hr>' : "<hr style='border-width:{$l}px'>";
        }, $text);

        // 连续//为换行
        $text = preg_replace_callback("#(///)#", function ($matches) {
            return '<br>';
        }, $text);

        // strong and em and some fuck
        $text = self::parseInlineCallback($text);
        $text = preg_replace("/<([_a-z0-9-\.\+]+@[^@]+\.[a-z]{2,})>/i", "<a href=\"mailto:\\1\">\\1</a>", $text);

        // autolink url
        if ($enableAutoLink) {
            $text = preg_replace("/(^|[^\"])((http|https|ftp|mailto):[x80-xff_a-z0-9-\.\/%#@\?\+=~\|\,&\(\)]+)($|[^\"])/i",
                "\\1<a href=\"\\2\" data-typ='420' target='_blank'>\\2</a>\\4", $text);
        }

        $text = self::call('afterParseInlineBeforeRelease', $text);
        $text = self::releaseHolder($text, $clearHolders);

        $text = self::call('afterParseInline', $text);
        return ($text);
    }

    /**
     * @param $text
     * @return mixed
     */
    private static function parseInlineCallback($text)
    {
        $text = preg_replace_callback("/(\*{3})(.+?)\\1/", function ($matches) {
            return '<strong><em>' . self::parseInlineCallback($matches[2]) . '</em></strong>';
        }, $text);

        $text = preg_replace_callback("/(\*{2})(.+?)\\1/", function ($matches) {
            return '<strong>' . self::parseInlineCallback($matches[2]) . '</strong>';
        }, $text);

        $text = preg_replace_callback("/(\*)(.+?)\\1/", function ($matches) {
            return '<em>' . self::parseInlineCallback($matches[2]) . '</em>';
        }, $text);

        $text = preg_replace_callback("/(\s+|^)(_{3})(.+?)\\2(\s+|$)/", function ($matches) {
            return $matches[1] . '<strong><em>' . self::parseInlineCallback($matches[3]) . '</em></strong>' . $matches[4];
        }, $text);

        $text = preg_replace_callback("/(\s+|^)(_{2})(.+?)\\2(\s+|$)/", function ($matches) {
            return $matches[1] . '<strong>' . self::parseInlineCallback($matches[3]) . '</strong>' . $matches[4];
        }, $text);


        $text = preg_replace_callback("/(\s+|^)(_)(.+?)\\2(\s+|$)/", function ($matches) {
            return $matches[1] . '<em>' . self::parseInlineCallback($matches[3]) . '</em>' . $matches[4];
        }, $text);

        //~~加删除线~~
        $text = preg_replace_callback("/(~{2})(.+?)\\1/", function ($matches) {
            return '<del>' . self::parseInlineCallback($matches[2]) . '</del>';
        }, $text);

        $text = preg_replace_callback("/(.+)\{(\d{1,3})\}/", function ($matches) {
//            $len = intval($matches[2]) - strlen($matches[1]);
//            $len <= 0 && $len = 0;
            return str_pad($matches[1], intval($matches[2]));// . str_repeat('&nbsp;', $len);
        }, $text);

        return $text;
    }

    /**
     * 将代码分行处理
     *
     * @param string $text
     * @param array $lines
     * @return array
     */
    private static function parseBlock($text, &$lines)
    {
        $lines = explode("\n", $text);
        self::$_blocks = Array();
        self::$_current = 'normal';
        self::$_pos = -1;
        $special = implode("|", array_keys(self::$_specialWhiteList));
        $emptyCount = 0;

        // analyze by line
        foreach ($lines as $key => &$line) {
            $block = self::getBlock();

            // 获取代码块开头：```或~~~
//            if (preg_match("/^(\s*)(~|`){3,}([^`~]*)$/i", $line, $matches)) {
            if (preg_match("/^(\s*)(`{3}|~{3})([^`]*)$/i", $line, $matches)) {
                if (self::isBlock('code')) {
                    $isAfterList = $block[3][2];
                    if ($isAfterList) {
                        self::combineBlock();
                        self::setBlock($key);
                    } else {
                        self::setBlock($key);
                        self::endBlock();
                    }
                } else {
                    $isAfterList = false;
                    if (self::isBlock('list')) {
                        $space = $block[3];
                        $isAfterList = ($space > 0 and strlen($matches[1]) >= $space) or strlen($matches[1]) > $space;
                    }
                    self::startBlock('code', $key, [$matches[1], $matches[3], $isAfterList]);
                }
                continue;

            } else if (self::isBlock('code')) {
                self::setBlock($key);
                continue;
            }

            // 处理HTML表格 table|tbody|thead|tfoot|tr|td|th
            if (preg_match("/^\s*<({$special})(\s+[^>]*)?>/i", $line, $matches)) {
                $tag = strtolower($matches[1]);
                if (!self::isBlock('html', $tag) && !self::isBlock('pre')) {
                    self::startBlock('html', $key, $tag);
                }
                continue;

            } else if (preg_match("/<\/({$special})>\s*$/i", $line, $matches)) {
                $tag = strtolower($matches[1]);

                if (self::isBlock('html', $tag)) {
                    self::setBlock($key);
                    self::endBlock();
                }
                continue;

            } else if (self::isBlock('html')) {
                self::setBlock($key);
                continue;
            }

            switch (true) {
                // list，列表，以1.或a.开头
                case preg_match("/^(\s*)((?:[0-9a-z]+\.)|\-|\+|\*)\s+/", $line, $matches):
                    $space = strlen($matches[1]);
                    $emptyCount = 0;

                    // opened
                    if (self::isBlock('list')) {
                        self::setBlock($key, $space);
                    } else {
                        self::startBlock('list', $key, $space);
                    }
                    break;

                // pre block，块，以tab开头
                case preg_match("/^\x20{4}/", $line):
                    $emptyCount = 0;

                    if (self::isBlock('pre') || self::isBlock('list')) {
                        self::setBlock($key);
                    } else if (self::isBlock('normal')) {
                        self::startBlock('pre', $key);
                    }
                    break;

                // footnote，脚注，[notes]
                case preg_match("/^\[\^((?:[^\]]|\\]|\\[)+?)\]:/", $line, $matches):
                    $space = strlen($matches[0]) - 1;
                    self::startBlock('footnote', $key, [$space, $matches[1]]);
                    break;

                // definition
                case preg_match("/^\s*\[((?:[^\]]|\\]|\\[)+?)\]:\s*(.+)$/", $line, $matches):
                    self::$_definitions[$matches[1]] = $matches[2];
                    self::startBlock('definition', $key);
                    self::endBlock();
                    break;

                // block quote，块，以>开头
                case preg_match("/^\s*>/", $line):
                    if (self::isBlock('quote')) {
                        self::setBlock($key);
                    } else {
                        self::startBlock('quote', $key);
                    }
                    break;

                // table，表格，第二行|--|
                case preg_match("/^((?:(?:(?:[ :]*\-[ :]*)+(?:\||\+))|(?:(?:\||\+)(?:[ :]*\-[ :]*)+)|(?:(?:[ :]*\-[ :]*)+(?:\||\+)(?:[ :]*\-[ :]*)+))+)$/", $line, $matches):
                    if (self::isBlock('normal')) {
                        $head = 0;
                        if (empty($block) || $block[0] != 'normal' || preg_match("/^\s*$/", $lines[$block[2]])) {
                            self::startBlock('table', $key);
                        } else {
                            $head = 1;
                            self::backBlock(1, 'table');
                        }

                        //去掉【|--|--|】两头的|
                        if ($matches[1][0] == '|') {
                            $matches[1] = substr($matches[1], 1);

                            if ($matches[1][strlen($matches[1]) - 1] == '|') {
                                $matches[1] = substr($matches[1], 0, -1);
                            }
                        }

                        $rows = preg_split("/(\+|\|)/", $matches[1]);
                        $aligns = Array();
                        foreach ($rows as &$row) {
                            $align = 'none';

                            /**
                             * |:--|左对齐，|:--:|居中，|--:|右对齐。
                             */
                            if (preg_match("/^\s*(:?)\-+(:?)\s*$/", $row, $match)) {
                                if (!empty($match[1]) && !empty($match[2])) {
                                    $align = 'center';
                                } else if (!empty($match[1])) {
                                    $align = 'left';
                                } else if (!empty($match[2])) {
                                    $align = 'right';
                                }
                            }
                            $aligns[] = $align;
                        }

                        self::setBlock($key, [[$head], $aligns, $head + 1]);
                    } else {
                        $block[3][0][2] = $block[3][2];
                        $block[3][2]++;
                        self::setBlock($key, $block[3]);
                    }
                    break;

                // single heading，标题，#开头
                case preg_match("/^(#+)(.*)$/", $line, $matches):
                    $num = min(strlen($matches[1]), 6);
                    self::startBlock('sh', $key, $num);
                    self::endBlock();
                    break;

                // multi heading，标题，文字下以2个以上=或-号
                case preg_match("/^\s*((=|-){2,})\s*$/", $line, $matches)
                    && ($block && $block[0] == "normal" && !preg_match("/^\s*$/", $lines[$block[2]])):    // check if last line isn't empty
                    if (self::isBlock('normal')) {
                        self::backBlock(1, 'mh', $matches[1][0] == '=' ? 1 : 2);
                        self::setBlock($key);
                        self::endBlock();
                    } else {
                        self::startBlock('normal', $key);
                    }
                    break;

                // hr，横线，---或***
                case preg_match("/^[-\*]{3,}\s*$/", $line):
                    self::startBlock('hr', $key);
                    self::endBlock();
                    break;

                // normal
                default:
                    if (self::isBlock('list')) {
                        if (preg_match("/^(\s*)/", $line)) { // empty line
                            if ($emptyCount > 0) {
                                self::startBlock('normal', $key);
                            } else {
                                self::setBlock($key);
                            }

                            $emptyCount++;
                        } else if ($emptyCount == 0) {
                            self::setBlock($key);
                        } else {
                            self::startBlock('normal', $key);
                        }
                    } else if (self::isBlock('footnote')) {
                        preg_match("/^(\s*)/", $line, $matches);
                        if (strlen($matches[1]) >= $block[3][0]) {
                            self::setBlock($key);
                        } else {
                            self::startBlock('normal', $key);
                        }
                    } else if (self::isBlock('table')) {
                        if (false !== strpos($line, '|')) {
                            $block[3][2]++;
                            self::setBlock($key, $block[3]);
                        } else {
                            self::startBlock('normal', $key);
                        }
                    } else if (self::isBlock('pre')) {
                        if (preg_match("/^\s*$/", $line)) {
                            if ($emptyCount > 0) {
                                self::startBlock('normal', $key);
                            } else {
                                self::setBlock($key);
                            }

                            $emptyCount++;
                        } else {
                            self::startBlock('normal', $key);
                        }
                    } else if (self::isBlock('quote')) {
                        if (preg_match("/^(\s*)/", $line)) { // empty line
                            if ($emptyCount > 0) {
                                self::startBlock('normal', $key);
                            } else {
                                self::setBlock($key);
                            }

                            $emptyCount++;
                        } else if ($emptyCount == 0) {
                            self::setBlock($key);
                        } else {
                            self::startBlock('normal', $key);
                        }
                    } else {
                        if (empty($block) || $block[0] != 'normal') {
                            self::startBlock('normal', $key);
                        } else {
                            self::setBlock($key);
                        }
                    }
                    break;
            }
        }

        return self::optimizeBlocks(self::$_blocks, $lines);
    }

    /**
     * @param array $blocks
     * @param array $lines
     * @return array
     */
    private static function optimizeBlocks(array $blocks, array $lines)
    {
        $blocks = self::call('beforeOptimizeBlocks', $blocks, $lines);

        foreach ($blocks as $key => &$block) {
            $prevBlock = isset($blocks[$key - 1]) ? $blocks[$key - 1] : NULL;
            $nextBlock = isset($blocks[$key + 1]) ? $blocks[$key + 1] : NULL;

            list ($type, $from, $to) = $block;

            if ('pre' == $type) {
                $isEmpty = array_reduce($lines, function ($result, $line) {
                    return preg_match("/^\s*$/", $line) && $result;
                }, true);

                if ($isEmpty) {
                    $block[0] = $type = 'normal';
                }
            }

            if ('normal' == $type) {
                // combine two blocks
                $types = ['list', 'quote'];

                if ($from == $to && preg_match("/^\s*$/", $lines[$from]) && !empty($prevBlock) && !empty($nextBlock)) {
                    if ($prevBlock[0] == $nextBlock[0] && in_array($prevBlock[0], $types)) {
                        // combine 3 blocks
                        $blocks[$key - 1] = [$prevBlock[0], $prevBlock[1], $nextBlock[2], NULL];
                        array_splice($blocks, $key, 2);
                    }
                }
            }
        }

        return self::call('afterOptimizeBlocks', $blocks, $lines);
    }

    /**
     * parseCode
     *
     * @param array $lines
     * @param array $parts
     * @return string
     */
    private static function parseCode(array $lines, array $parts)
    {
        list ($blank, $lang) = $parts;
        $lang = trim($lang);
        $count = strlen($blank);

        if (!preg_match('/^[\w\-\+\#\:\.]+$/', $lang)) {
            $lang = $rel = NULL;
        } else {
            $parts = explode(':', $lang);
            if (count($parts) > 1) {
                list ($lang, $rel) = $parts;
                $lang = trim(strtolower($lang));
                $rel = trim($rel);
            }
        }

        $lines = array_map(function ($line) use ($count) {
            return preg_replace("/^\x20{{$count}}/", '', $line);
        }, array_slice($lines, 1, -1));

        $str = implode("\n", $lines);
        if ($lang === 'php') {
            $str = highlight_string($str, true);
//            $f = ['0000BB', '007700', 'DD0000'];//蓝，绿，红
//            $f = ['8cc6ff', 'cfffb5', 'ffd2df'];//蓝，绿，红
            $str = str_replace(['#0000BB', '#007700', '#DD0000'], ['#8cc6ff', '#cfffb5', '#ffea00'], $str);

        } else if ($lang === 'c') {
            $str = highlight_string("<?php\n" . $str, true);
//            $f = ['0000BB', '007700', 'DD0000'];//蓝，绿，红
//            $f = ['8cc6ff', 'cfffb5', 'ffd2df'];//蓝，绿，红
            $str = str_replace(['#0000BB', '#007700', '#DD0000', '&lt;?php'], ['#8cc6ff', '#cfffb5', '#ffea00', '#C/C++ code'], $str);

        } else {
            $str = htmlspecialchars($str);
            $str = preg_replace_callback('/\[(\#?\w{3,10})\](.+?)\[\/\1\]/', function ($matches) {
                return "<span style='color:{$matches[1]}' data-line='862'>{$matches[2]}</span>";
            }, $str);
            $str = preg_replace_callback('/\[(\#?\w{3,10})\;(.+?)\]/', function ($matches) {
                return "<span style='color:{$matches[1]}' data-line='865'>{$matches[2]}</span>";
            }, $str);
            $str = preg_replace_callback('/\{(\#?\w{3,10})\;(.+?)\}/', function ($matches) {
                return "<span style='color:{$matches[1]}' data-line='868'>{$matches[2]}</span>";
            }, $str);
        }
        if (preg_match("/^\s*$/", $str)) return '';

//        return ('<pre' . (!empty($lang) ? " class=\"{$lang}\"" : '') . (!empty($rel) ? " rel=\"{$rel}\"" : '') . ">{$str}</pre>");

        return "\n<pre class='layui-code' lay-title='{$lang}' lay-height='' lay-skin='' lay-encode='false'>{$str}</pre>\n";

//        return preg_match("/^\s*$/", $str) ? '' :
//            ('<pre' . (!empty($lang) ? " class=\"{$lang}\"" : '') . (!empty($rel) ? " rel=\"{$rel}\"" : '') . ">{$str}</pre>");
    }

    /**
     * parsePre
     *
     * @param array $lines
     * @return string
     */
    private static function parsePre(array $lines)
    {
        foreach ($lines as &$line) {
            $line = htmlspecialchars(substr($line, 4));
        }
        $str = implode("\n", $lines);

        return preg_match("/^\s*$/", $str) ? '' : "<pre>{$str}</pre>";
    }

    /**
     * @param array $lines
     * @param int $num
     * @return string
     */
    private static function parseSh(array $lines, $num)
    {
        $addMap = false;
        if ($lines[0][-1] === '#') {
            $lines[0] = substr($lines[0], 0, -1);
            $addMap = true;
        }
        $line = self::parseInline(trim($lines[0], '# '));
        if (!self::$addNav && !$addMap) return "<h{$num} data-line='898'>{$line}</h{$num}>";
        $name = md5($line);
        self::$href[] = ['lv' => $num, 'name' => $name, 'title' => $line];
        return preg_match("/^\s*$/", $line) ? '' : "<a name='{$name}' data-line='901' href='#top'></a><h{$num}>{$line}</h{$num}>";
    }

    /**
     * @param array $lines
     * @param int $num
     * @return string
     */
    private static function parseMh(array $lines, $num)
    {
        return self::parseSh($lines, $num);
    }

    /**
     * parseQuote
     *
     * @param array $lines
     * @return string
     */
    private static function parseQuote(array $lines)
    {
        foreach ($lines as &$line) {
            $line = preg_replace("/^\s*> ?/", '', $line);
        }
        $str = implode("\n", $lines);

        return preg_match("/^\s*$/", $str) ? '' : '<blockquote>' . self::parse($str) . '</blockquote>';
    }

    /**
     * parseList
     *
     * @param array $lines
     * @return string
     */
    private static function parseList(array $lines)
    {
        $html = '';
        $minSpace = 99999;
        $rows = Array();

        // count levels
        foreach ($lines as $key => &$line) {
            if (preg_match("/^(\s*)((?:[0-9a-z]+\.?)|\-|\+|\*)(\s+)(.*)$/", $line, $matches)) {
                $space = strlen($matches[1]);
                $type = false !== strpos('+-*', $matches[2]) ? 'ul' : 'ol';
                $minSpace = min($space, $minSpace);

                $rows[] = [$space, $type, $line, $matches[4]];
            } else {
                $rows[] = $line;
            }
        }

        $found = false;
        $secondMinSpace = 99999;
        foreach ($rows as &$row) {
            if (is_array($row) && $row[0] != $minSpace) {
                $secondMinSpace = min($secondMinSpace, $row[0]);
                $found = true;
            }
        }
        $secondMinSpace = $found ?: $minSpace;

        $lastType = '';
        $leftLines = Array();

        foreach ($rows as &$row) {
            if (is_array($row)) {
                list ($space, $type, $line, $text) = $row;

                if ($space != $minSpace) {
                    $leftLines[] = preg_replace("/^\s{" . $secondMinSpace . "}/", '', $line);
                } else {
                    if (!empty($leftLines)) {
                        $html .= "<li>" . self::parse(implode("\n", $leftLines)) . "</li>";
                    }

                    if ($lastType != $type) {
                        if (!empty($lastType)) {
                            $html .= "</{$lastType}>";
                        }

                        $html .= "<{$type} class='list'>";
                    }

                    $leftLines = [$text];
                    $lastType = $type;
                }
            } else {
                $leftLines[] = preg_replace("/^\s{" . $secondMinSpace . "}/", '', $row);
            }
        }

        if (!empty($leftLines)) {
            $html .= "<li>" . self::parse(implode("\n", $leftLines)) . "</li></{$lastType}>";
        }

        return $html;
    }

    /**
     * @param array $lines
     * @param array $value
     * @return string
     */
    private static function parseTable(array $lines, array $value)
    {
        list ($ignores, $aligns) = $value;
        $head = count($ignores) > 0;

        $html = '<table>';
        $body = NULL;
        $output = false;
        $cols = 0;
        $tab_cols = 0;

        foreach ($lines as $key => &$line) {
            if (in_array($key, $ignores)) {
                if ($head && $output) {
                    $head = false;
                    $body = true;
                }
                continue;
            }

            $line = trim($line);
            $output = true;

            if ($line[0] == '|') {
                $line = substr($line, 1);

                if ($line[strlen($line) - 1] == '|') {
                    $line = substr($line, 0, -1);
                }
            }

            // 分隔符|必须提前给换出来
            $line = str_replace('\|', '{////}', $line);

            $rows = array_map(function ($row) {
                if (preg_match("/^\s+$/", $row)) {
                    return ' ';
                } else {
                    $row = str_replace('{////}', '|', $row);
                    return trim($row);
                }
            }, explode('|', $line));

            $columns = Array();
            $last = -1;

            foreach ($rows as &$row) {
                $last++;
                if (strlen($row) > 0) {
                    $columns[$last] = [isset($columns[$last]) ? $columns[$last][0] + 1 : 1, $row];
                } else if (isset($columns[$last])) {
                    $columns[$last][0]++;
                } else {
                    $columns[0] = [1, $row];
                }
            }

            if ($head) {
                $html .= '<thead>';
            } else if ($body) {
                $html .= '<tbody>';
            }
            $html .= '<tr>';

            foreach ($columns as $ky => &$column) {
                list ($num, $text) = $column;
                $tag = $head ? 'th' : 'td';
                $bgcolor = $width = null;
                $color = '\#(?:[a-f0-9]{6}|[a-f0-9]{3});';
                if (preg_match("/^(?:({$color})|(?:(\d+);)|(?:(\d{1,3}px;))){1,2}(.*)$/i", $text, $matches)) {
                    $bgcolor = $matches[1];
                    $num = intval($matches[2]);
                    $width = intval($matches[3]);
                    $text = $matches[4];
                }

                $html .= "<{$tag}";
                if ($num > 1) $html .= " colspan=\"{$num}\"";
                $style = '';
                if (is_int($width) and $width > 0) $style = "width:{$width}px;";
                if (!!$bgcolor) $style .= "background:{$bgcolor};";
                if (isset($aligns[$ky]) && $aligns[$ky] != 'none') {
                    $style .= "text-align:{$aligns[$ky]};";
                }

                if (!empty($style)) $html .= " style=\"{$style}\"";
                $html .= '>' . self::parseInline(htmlspecialchars($text)) . "</{$tag}>";
                $cols = $ky + ($num - 1);
            }
            //补齐单元格
            if ($tab_cols > 0) {
                for ($ci = $cols; $ci < $tab_cols; $ci++) $html .= '<td></td>';
            } elseif ($cols > 0) $tab_cols = $cols;


            $html .= '</tr>';

            if ($head) {
                $html .= '</thead>';
            } else if ($body) {
                $body = false;
            }
        }

        if ($body !== NULL) {
            $html .= '</tbody>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * parseHr
     *
     * @return string
     */
    private static function parseHr()
    {
        return '<hr>';
    }

    /**
     * parseNormal
     *
     * @param array $lines
     * @return string
     */
    private static function parseNormal(array $lines)
    {
        foreach ($lines as &$line) {
            $line = self::parseInline($line);
        }

        $str = trim(implode("\n", $lines));
        $str = preg_replace("/(\n\s*){2,}/", "</p><p>", $str);
        $str = preg_replace("/\n/", "<br>", $str);

        return preg_match("/^\s*$/", $str) ? '' : "<p>{$str}</p>";
    }

    /**
     * parseFootnote
     *
     * @param array $lines
     * @param array $value
     * @return string
     */
    private static function parseFootnote(array $lines, array $value)
    {
        list($space, $note) = $value;
        $index = array_search($note, self::$_footnotes);

        if (false !== $index) {
            $lines[0] = preg_replace("/^\[\^((?:[^\]]|\\]|\\[)+?)\]:/", '', $lines[0]);
            self::$_footnotes[$index] = $lines;
        }

        return '';
    }

    /**
     * parseDefine
     *
     * @return string
     */
    private static function parseDefinition()
    {
        return '';
    }

    /**
     * parseHtml
     *
     * @param array $lines
     * @param string $type
     * @return string
     */
    private static function parseHtml(array $lines, $type)
    {
        foreach ($lines as &$line) {
            $line = self::parseInline($line, isset(self::$_specialWhiteList[$type]) ? self::$_specialWhiteList[$type] : '');
        }
        return implode("\n", $lines);
    }

    /**
     * @param $str
     * @return mixed
     */
    private static function escapeBracket($str)
    {
        return str_replace(['\[', '\]', '\(', '\)'], ['[', ']', '(', ')'], $str);
    }

    /**
     * startBlock
     *
     * @param mixed $type
     * @param mixed $start
     * @param mixed $value
     */
    private static function startBlock($type, $start, $value = NULL)
    {
        self::$_pos++;
        self::$_current = $type;
        self::$_blocks[self::$_pos] = [$type, $start, $start, $value];
    }

    /**
     * endBlock
     *
     */
    private static function endBlock()
    {
        self::$_current = 'normal';
    }

    /**
     * isBlock
     *
     * @param mixed $type
     * @param mixed $value
     * @return bool
     */
    private static function isBlock($type, $value = NULL)
    {
        return self::$_current == $type and (NULL === $value ? true : self::$_blocks[self::$_pos][3] == $value);
    }

    /**
     * getBlock
     *
     * @return array
     */
    private static function getBlock()
    {
        return isset(self::$_blocks[self::$_pos]) ? self::$_blocks[self::$_pos] : NULL;
    }

    /**
     * setBlock
     *
     * @param mixed $to
     * @param mixed $value
     */
    private static function setBlock($to = NULL, $value = NULL)
    {
        if (NULL !== $to) {
            self::$_blocks[self::$_pos][2] = $to;
        }

        if (NULL !== $value) {
            self::$_blocks[self::$_pos][3] = $value;
        }

    }

    /**
     * backBlock
     *
     * @param mixed $step
     * @param mixed $type
     * @param mixed $value
     */
    private static function backBlock($step, $type, $value = NULL)
    {
        if (self::$_pos < 0) {
            self::startBlock($type, 0, $value);
        }

        $last = self::$_blocks[self::$_pos][2];
        self::$_blocks[self::$_pos][2] = $last - $step;

        if (self::$_blocks[self::$_pos][1] <= self::$_blocks[self::$_pos][2]) {
            self::$_pos++;
        }

        self::$_current = $type;
        self::$_blocks[self::$_pos] = [$type, $last - $step + 1, $last, $value];

    }

    private static function combineBlock()
    {
        if (self::$_pos < 1) {
            return;
        }

        $prev = self::$_blocks[self::$_pos - 1];
        $current = self::$_blocks[self::$_pos];

        $prev[2] = $current[2];
        self::$_blocks[self::$_pos - 1] = $prev;
        self::$_current = $prev[0];
        unset(self::$_blocks[self::$_pos]);
        self::$_pos--;

    }
}
