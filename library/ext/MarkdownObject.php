<?php

namespace esp\library\ext;


/**
 * 解析md代码：Markdown::html(mdCode);
 * Class Markdown
 * @package tools
 */
class MarkdownObject
{
    /**
     * _whiteList
     *
     * @var string
     */
    private $_commonWhiteList = 'kbd|b|i|strong|em|sup|sub|br|code|del|a|hr|small';

    /**
     * _specialWhiteList
     *
     * @var mixed
     * @access private
     */
    private $_specialWhiteList = ['table' => 'table|tbody|thead|tfoot|tr|td|th', 'div' => ''];

    /**
     * _footnotes
     *
     * @var array
     */
    private $_footnotes;

    /**
     * _blocks
     *
     * @var array
     */
    private $_blocks;

    /**
     * _current
     *
     * @var string
     */
    private $_current;

    /**
     * _pos
     *
     * @var int
     */
    private $_pos;

    /**
     * _definitions
     *
     * @var array
     */
    private $_definitions;

    /**
     * @var array
     */
    private $_hooks = Array();

    /**
     * @var array
     */
    private $_holders;

    /**
     * @var string
     */
    private $_uniqid;

    /**
     * @var int
     */
    private $_id;


    /**
     * 所有锚点
     * @var array
     * $this->href[] = ['lv' => $num, 'name' => $name, 'title' => $line];
     */
    private $href = Array();

    private $addNav = false;

    private $conf;

    public function __construct(array $conf = [])
    {
        $this->conf = $conf;
    }

    /**
     * makeHtml
     * @param string $text
     * @param bool $addNav
     * @param bool $addBoth
     * @return string
     */
    public function render(string $text, bool $addNav = false, bool $addBoth = true)
    {
        $this->_footnotes = Array();
        $this->_definitions = Array();
        $this->_holders = Array();
        $this->_html = Array();
        $this->_uniqid = md5(uniqid());
        $this->_id = 0;
        $this->addNav = $addNav;
        $this->replaceHtml($text);//优先处理<<<html>>>
        $text = str_replace(["\t", "\r"], ['    ', ''], $text);
        $html = $this->parse($text);
        $html = $this->makeFootnotes($html);
        $this->joinHtml($html);
        return ($addNav ? $this->makeNav() : '') .
            "<article class='markdown'>{$html}</article>" .
            ($addBoth ? '<div style="display: block;width:100%;height:100px;clear: both;"></div>' : '');
    }

    private $_html = Array();

    /**
     * 处理<<<HTML代码>>>
     * @param $text
     */
    private function replaceHtml(string &$text)
    {
        $text = preg_replace_callback("/\<{3}(?<pre>\w*)\s+(?<html>.+?)\>{3}/is", function ($matches) {
            $id = mt_rand();
            $this->_html[$id] = $matches['html'];
            return "[html:{$id}]" . (!!$matches['pre'] ? ("相关源码如下：\n```{$matches['pre']}\n{$matches['html']}\n```\n") : '');
        }, $text);
    }

    /**
     * 将HTML直接显示部分加入进来。
     * @param $html
     */
    private function joinHtml(&$html)
    {
        $html = preg_replace_callback("/\((br|hr){1}\)/i", function ($matches) {
            return "<{$matches[1]}>";
        }, $html);

        $html = preg_replace_callback("/\[html\:(\d+)\]/i", function ($matches) {
            return $this->_html[$matches[1]] . '<br>';
        }, $html);
    }


    private function makeNav(): string
    {
        if (!$this->href) return '';
        $nav = count($this->href) > 35 ? 'nav navMore' : 'nav';
        $html = Array();
        $html[] = "<ol class='{$nav}'>";
        $html[] = "<li><a href='#' target='_self'>Top</a></li>";

        foreach ($this->href as $i => &$li) {
            $html[] = "<li><a href='#{$li["name"]}' target='_self'>{$li['title']}</a></li>";
        }
        $html[] = "</ol>";
        return implode($html);
    }

    /**
     * @param $type
     * @param $callback
     */
    private function hook($type, $callback)
    {
        $this->_hooks[$type][] = $callback;
    }

    /**
     * 暂存需要返回HTML原型的内容
     * @param $str
     * @return string
     */
    private function makeHolder($str)
    {
        $key = "|\r" . $this->_uniqid . $this->_id . "\r|";
        $this->_id++;
        $this->_holders[$key] = $str;
        return $key;
    }

    /**
     * @param $html
     * @return string
     */
    private function makeFootnotes($html)
    {
        if (count($this->_footnotes) > 0) {
            $html .= '<div class="footnotes"><hr><ol class="foot">';
            $index = 1;

            while ($val = array_shift($this->_footnotes)) {
                if (is_string($val)) {
                    $val .= " <a href=\"#fnref-{$index}\" class=\"footnote-backref\">&#8617;</a>";
                } else {
                    $val[count($val) - 1] .= " <a href=\"#fnref-{$index}\" class=\"footnote-backref\">&#8617;</a>";
                    $val = count($val) > 1 ? $this->parse(implode("\n", $val)) : $this->parseInline($val[0]);
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
    private function parse($text)
    {
        $blocks = $this->parseBlock($text, $lines);
        $html = Array();
        foreach ($blocks as &$block) {
            list ($type, $start, $end, $value) = $block;
            $extract = array_slice($lines, $start, $end - $start + 1);
            $method = 'parse' . ucfirst($type);

            $extract = $this->call('before' . ucfirst($method), $extract, $value);
            $result = $this->{$method}($extract, $value);
            $result = $this->call('after' . ucfirst($method), $result, $value);

            $html[] = $result;
        }

        return implode($html);
    }

    /**
     * @param $type
     * @param $value
     * @return mixed
     */
    private function call($type, $value)
    {
        if (empty($this->_hooks[$type])) {
            return $value;
        }

        $args = func_get_args();
        $args = array_slice($args, 1);

        foreach ($this->_hooks[$type] as &$callback) {
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
    private function releaseHolder($text, $clearHolders = true)
    {
        $deep = 0;
        while (strpos($text, "|\r") !== false && $deep < 10) {
            $text = str_replace(array_keys($this->_holders), array_values($this->_holders), $text);
            $deep++;
        }

        if ($clearHolders) {
            $this->_holders = Array();
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
    private function parseInline($text, $whiteList = '', $clearHolders = true, $enableAutoLink = true)
    {
        $text = $this->call('beforeParseInline', ($text));
//        $text = str_replace('\\', '\\\\', $text);


        //单行重点备注
//        $text = preg_replace_callback("/(^|[^\\\])(\!{1})(.+?)\\2/", function ($matches) {
//            return $matches[1] . $this->makeHolder('<span class="important">' . htmlspecialchars($matches[3]) . '</span>');
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
            $span = ($matches['val']);
            if ($this->conf['link'] ?? 1) {
                if (\esp\helper\is_url($span)) $span = "<a href='{$span}' target='_blank'>{$span}</a>";
                else if (\esp\helper\is_domain($span)) $span = "<a href='http://{$span}' target='_blank'>{$span}</a>";
            }

            return $matches['hd'] . $this->makeHolder("<span {$style} data-line='328'>{$span}</span>");
        }, $text);

//        // 单行``注释
        $text = preg_replace_callback("/(^|[^\\\])\<(?<style>[\w\;\-\:\#\.\% ]+?)?\>(?<val>.+?)\<\/\>/", function ($matches) {
            return $matches[1] . $this->makeHolder("<span style='{$matches['style']}' data-line='324'>" . htmlspecialchars($matches['val']) . '</span>');
        }, $text);


//        // 单行[#f00;内容]加色
        $text = preg_replace_callback("/\[({$cp});(.+)\]/i", function ($mc) {
            return $this->makeHolder("<span style='color:{$mc[1]}'>{$mc[2]}</span>");
        }, $text);


        //@@@
        $text = preg_replace_callback("/(^|[^\\\])(\@{3})\s*(.+?)\s*\\2/", function ($matches) {
            return $matches[1] . $this->makeHolder('<div class="notes"><h2>Notes:</h2><div>' . htmlspecialchars($matches[3]) . '</div></div>');
        }, $text);

        //<name>锚链，链接到锚链：[title](#name)
        $text = preg_replace_callback("/(^|[^\\\])\<:([\w]+?)\>/", function ($matches) {
            return $matches[1] . $this->makeHolder("<a name='{$matches[2]}' target='_self'></a>");
        }, $text);

        // 加载文件
        $text = preg_replace_callback("/<(?:file|include|load)\:(.+?)>/i", function ($matches) {
            $file = _ROOT . '/' . trim($matches[1], '/"\'');
            if (!is_file($file)) return $file;
            return $this->makeHolder(file_get_contents($file));
        }, $text);

        // [tag] => <tag>
        $text = preg_replace_callback("/\[([a-z]+)\]/i", function ($matches) {
            return "<{$matches[1]}>";
        }, $text);

        // link
        $text = preg_replace_callback("/<(?:href)\:(.+?)>/i", function ($matches) {
            $url = str_replace(['_HTTP', '_DOMAIN'], [_HTTP_, _DOMAIN], $matches[1]);
//            return ("<a href=\"{$url}\" data-typ='349' target='_blank'>{$url}</a>");
            return $this->makeHolder("<a href=\"{$url}\" data-typ='349' target='_blank'>{$url}</a>");
        }, $text);

        // link
        $text = preg_replace_callback("/<(https?:\/\/.+)>/i", function ($matches) {
            return $this->makeHolder("<a href=\"{$matches[1]}\" data-typ='349' target='_blank'>{$matches[1]}</a>");
        }, $text);

        // encode unsafe tags
        $text = preg_replace_callback("/<(\/?)([a-z0-9-]+)(\s+[^>]*)?>/i", function ($matches) use ($whiteList) {
            if (stripos('|' . $this->_commonWhiteList . '|' . $whiteList . '|', '|' . $matches[2] . '|') !== false) {
                return $this->makeHolder($matches[0]);
            } else {
                $cod = str_replace('\\\\', '\\', $matches[0]);
                return htmlspecialchars($matches[0]);
            }
        }, $text);

        // 加载文件
        $text = preg_replace_callback("/<([a-z]{2,10})\:(.+)>/i", function ($matches) {
            $file = _ROOT . '/' . trim($matches[2], '/"\'');
            if (!is_file($file)) return $file;
            return $this->makeHolder("<p>{$file}:</p><pre class='{$matches[1]}'>" . file_get_contents($file) . "</pre>");
        }, $text);

//        $text = str_replace(['<', '>'], ['&lt;', '&gt;'], $text);

        // footnote
        $text = preg_replace_callback("/\[\^((?:[^\]]|\\]|\\[)+?)\]/", function ($matches) {
            $id = array_search($matches[1], $this->_footnotes);

            if (false === $id) {
                $id = count($this->_footnotes) + 1;
                $this->_footnotes[$id] = $this->parseInline($matches[1], '', false);
            }
            return $this->makeHolder("<sup id=\"fnref-{$id}\"><a href=\"#fn-{$id}\" target=\"_self\" class=\"footnote-ref\">[{$id}]</a></sup>");
        }, $text);

        // image
        $text = preg_replace_callback("/!\[((?:[^\]]|\\]|\\[)*?)\]\(((?:[^\)]|\\)|\\()+?)\)/", function ($matches) {
            $escaped = $this->escapeBracket($matches[1]);
            $url = $this->escapeBracket($matches[2]);
            return $this->makeHolder("<img src=\"{$url}\" alt=\"{$escaped}\" title=\"{$escaped}\">");
        }, $text);

        $text = preg_replace_callback("/!\[((?:[^\]]|\\]|\\[)*?)\]\[((?:[^\]]|\\]|\\[)+?)\]/", function ($matches) {
            $escaped = $this->escapeBracket($matches[1]);

            $result = isset($this->_definitions[$matches[2]]) ?
                ("<img src=\"" . $this->_definitions[$matches[2]] . "\" alt=\"{$escaped}\" title=\"{$escaped}\">")
                : $escaped;

            return $this->makeHolder($result);
        }, $text);

        // link
        $text = preg_replace_callback("/\[((?:[^\]]|\\]|\\[)+?)\]\(((?:[^\)]|\\)|\\()+?)\)/", function ($matches) {
            $escaped = $this->parseInline($this->escapeBracket($matches[1]), '', false, false);
            $url = $this->escapeBracket($matches[2]);
            $target = '';
            if ($url[0] === '#') {
                $target = ' target="_self"';
                $url = substr($url, 1);
            } else if ($url[0] === '@') {
                $target = ' class="parent"';
                $url = substr($url, 1);
            }
            return $this->makeHolder("<a href=\"{$url}\" {$target} data-typ='436'>{$escaped}</a>");
        }, $text);

        $text = preg_replace_callback("/\[((?:[^\]]|\\]|\\[)+?)\]\[((?:[^\]]|\\]|\\[)+?)\]/", function ($matches) {
            $escaped = $this->parseInline($this->escapeBracket($matches[1]), '', false, false);
            $result = isset($this->_definitions[$matches[2]]) ?
                ("<a href=\"" . $this->_definitions[$matches[2]] . "\" data-typ='403'>{$escaped}</a>") : $escaped;

            return $this->makeHolder($result);
        }, $text);

        // escape
        $text = preg_replace_callback("/\\\(x80-xff|.)/", function ($matches) {
            return $this->makeHolder(htmlspecialchars($matches[1]));
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
        $text = $this->parseInlineCallback($text);
        $text = preg_replace("/<([_a-z0-9-\.\+]+@[^@]+\.[a-z]{2,})>/i", "<a href=\"mailto:\\1\">\\1</a>", $text);

        // autolink url
        if ($enableAutoLink) {
            $text = preg_replace("/(^|[^\"])((http|https|ftp|mailto):[x80-xff_a-z0-9-\.\/%#@\?\+=~\|\,&\(\)]+)($|[^\"])/i",
                "\\1<a href=\"\\2\" data-typ='420' target='_blank'>\\2</a>\\4", $text);
        }

        $text = $this->call('afterParseInlineBeforeRelease', $text);
        $text = $this->releaseHolder($text, $clearHolders);

        $text = $this->call('afterParseInline', $text);
        return ($text);
    }

    /**
     * @param $text
     * @return mixed
     */
    private function parseInlineCallback($text)
    {
        $text = preg_replace_callback("/(\*{3})(.+?)\\1/", function ($matches) {
            return '<strong><em>' . $this->parseInlineCallback($matches[2]) . '</em></strong>';
        }, $text);

        $text = preg_replace_callback("/(\*{2})(.+?)\\1/", function ($matches) {
            return '<strong>' . $this->parseInlineCallback($matches[2]) . '</strong>';
        }, $text);

        $text = preg_replace_callback("/(\*)(.+?)\\1/", function ($matches) {
            return '<em>' . $this->parseInlineCallback($matches[2]) . '</em>';
        }, $text);

        $text = preg_replace_callback("/(\s+|^)(_{3})(.+?)\\2(\s+|$)/", function ($matches) {
            return $matches[1] . '<strong><em>' . $this->parseInlineCallback($matches[3]) . '</em></strong>' . $matches[4];
        }, $text);

        $text = preg_replace_callback("/(\s+|^)(_{2})(.+?)\\2(\s+|$)/", function ($matches) {
            return $matches[1] . '<strong>' . $this->parseInlineCallback($matches[3]) . '</strong>' . $matches[4];
        }, $text);


        $text = preg_replace_callback("/(\s+|^)(_)(.+?)\\2(\s+|$)/", function ($matches) {
            return $matches[1] . '<em>' . $this->parseInlineCallback($matches[3]) . '</em>' . $matches[4];
        }, $text);

        //~~加删除线~~
        $text = preg_replace_callback("/(~{2})(.+?)\\1/", function ($matches) {
            return '<del>' . $this->parseInlineCallback($matches[2]) . '</del>';
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
    private function parseBlock($text, &$lines)
    {
        $lines = explode("\n", $text);
        $this->_blocks = Array();
        $this->_current = 'normal';
        $this->_pos = -1;
        $special = implode("|", array_keys($this->_specialWhiteList));
        $emptyCount = 0;

        // analyze by line
        foreach ($lines as $key => &$line) {
            $block = $this->getBlock();

            // 获取代码块开头：```或~~~
//            if (preg_match("/^(\s*)(~|`){3,}([^`~]*)$/i", $line, $matches)) {
            if (preg_match("/^(\s*)(`{3}|~{3})([^`]*)$/i", $line, $matches)) {
                if ($this->isBlock('code')) {
                    $isAfterList = $block[3][2];
                    if ($isAfterList) {
                        $this->combineBlock();
                        $this->setBlock($key);
                    } else {
                        $this->setBlock($key);
                        $this->endBlock();
                    }
                } else {
                    $isAfterList = false;
                    if ($this->isBlock('list')) {
                        $space = $block[3];
                        $isAfterList = ($space > 0 and strlen($matches[1]) >= $space) or strlen($matches[1]) > $space;
                    }
                    $this->startBlock('code', $key, [$matches[1], $matches[3], $isAfterList]);
                }
                continue;

            } else if ($this->isBlock('code')) {
                $this->setBlock($key);
                continue;
            }

            // 处理HTML表格 table|tbody|thead|tfoot|tr|td|th
            if (preg_match("/^\s*<({$special})(\s+[^>]*)?>/i", $line, $matches)) {
                $tag = strtolower($matches[1]);
                if (!$this->isBlock('html', $tag) && !$this->isBlock('pre')) {
                    $this->startBlock('html', $key, $tag);
                }
                continue;

            } else if (preg_match("/<\/({$special})>\s*$/i", $line, $matches)) {
                $tag = strtolower($matches[1]);

                if ($this->isBlock('html', $tag)) {
                    $this->setBlock($key);
                    $this->endBlock();
                }
                continue;

            } else if ($this->isBlock('html')) {
                $this->setBlock($key);
                continue;
            }

            switch (true) {
                // list，列表，以1.或a.开头
                case preg_match("/^(\s*)((?:[0-9a-z]+\.)|\-|\+|\*)\s+/", $line, $matches):
                    $space = strlen($matches[1]);
                    $emptyCount = 0;

                    // opened
                    if ($this->isBlock('list')) {
                        $this->setBlock($key, $space);
                    } else {
                        $this->startBlock('list', $key, $space);
                    }
                    break;

                // pre block，块，以tab开头
                case preg_match("/^\x20{4}/", $line):
                    $emptyCount = 0;

                    if ($this->isBlock('pre') || $this->isBlock('list')) {
                        $this->setBlock($key);
                    } else if ($this->isBlock('normal')) {
                        $this->startBlock('pre', $key);
                    }
                    break;

                // footnote，脚注，[notes]
                case preg_match("/^\[\^((?:[^\]]|\\]|\\[)+?)\]:/", $line, $matches):
                    $space = strlen($matches[0]) - 1;
                    $this->startBlock('footnote', $key, [$space, $matches[1]]);
                    break;

                // definition
                case preg_match("/^\s*\[((?:[^\]]|\\]|\\[)+?)\]:\s*(.+)$/", $line, $matches):
                    $this->_definitions[$matches[1]] = $matches[2];
                    $this->startBlock('definition', $key);
                    $this->endBlock();
                    break;

                // block quote，块，以>开头
                case preg_match("/^\s*>/", $line):
                    if ($this->isBlock('quote')) {
                        $this->setBlock($key);
                    } else {
                        $this->startBlock('quote', $key);
                    }
                    break;

                // table，表格，第二行|--|
                case preg_match("/^((?:(?:(?:[ :]*\-[ :]*)+(?:\||\+))|(?:(?:\||\+)(?:[ :]*\-[ :]*)+)|(?:(?:[ :]*\-[ :]*)+(?:\||\+)(?:[ :]*\-[ :]*)+))+)$/", $line, $matches):
                    if ($this->isBlock('normal')) {
                        $head = 0;
                        if (empty($block) || $block[0] != 'normal' || preg_match("/^\s*$/", $lines[$block[2]])) {
                            $this->startBlock('table', $key);
                        } else {
                            $head = 1;
                            $this->backBlock(1, 'table');
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
                             * |:--|左对齐，
                             * |:--:|居中，
                             * |--:|右对齐。
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

                        $this->setBlock($key, [[$head], $aligns, $head + 1]);
                    } else {
                        $block[3][0][2] = $block[3][2];
                        $block[3][2]++;
                        $this->setBlock($key, $block[3]);
                    }
                    break;

                // single heading，标题，#开头
                case preg_match("/^(#+)(.*)$/", $line, $matches):
                    $num = min(strlen($matches[1]), 6);
                    $this->startBlock('sh', $key, $num);
                    $this->endBlock();
                    break;

                // multi heading，标题，文字下以2个以上=或-号
                case preg_match("/^\s*((=|-){2,})\s*$/", $line, $matches)
                    && ($block && $block[0] == "normal" && !preg_match("/^\s*$/", $lines[$block[2]])):    // check if last line isn't empty
                    if ($this->isBlock('normal')) {
                        $this->backBlock(1, 'mh', $matches[1][0] == '=' ? 1 : 2);
                        $this->setBlock($key);
                        $this->endBlock();
                    } else {
                        $this->startBlock('normal', $key);
                    }
                    break;

                // hr，横线，---或***
                case preg_match("/^[-\*]{3,}\s*$/", $line):
                    $this->startBlock('hr', $key);
                    $this->endBlock();
                    break;

                // normal
                default:
                    if ($this->isBlock('list')) {
                        if (preg_match("/^(\s*)/", $line)) { // empty line
                            if ($emptyCount > 0) {
                                $this->startBlock('normal', $key);
                            } else {
                                $this->setBlock($key);
                            }

                            $emptyCount++;
                        } else if ($emptyCount == 0) {
                            $this->setBlock($key);
                        } else {
                            $this->startBlock('normal', $key);
                        }
                    } else if ($this->isBlock('footnote')) {
                        preg_match("/^(\s*)/", $line, $matches);
                        if (strlen($matches[1]) >= $block[3][0]) {
                            $this->setBlock($key);
                        } else {
                            $this->startBlock('normal', $key);
                        }
                    } else if ($this->isBlock('table')) {
                        if (false !== strpos($line, '|')) {
                            $block[3][2]++;
                            $this->setBlock($key, $block[3]);
                        } else {
                            $this->startBlock('normal', $key);
                        }
                    } else if ($this->isBlock('pre')) {
                        if (preg_match("/^\s*$/", $line)) {
                            if ($emptyCount > 0) {
                                $this->startBlock('normal', $key);
                            } else {
                                $this->setBlock($key);
                            }

                            $emptyCount++;
                        } else {
                            $this->startBlock('normal', $key);
                        }
                    } else if ($this->isBlock('quote')) {
                        if (preg_match("/^(\s*)/", $line)) { // empty line
                            if ($emptyCount > 0) {
                                $this->startBlock('normal', $key);
                            } else {
                                $this->setBlock($key);
                            }

                            $emptyCount++;
                        } else if ($emptyCount == 0) {
                            $this->setBlock($key);
                        } else {
                            $this->startBlock('normal', $key);
                        }
                    } else {
                        if (empty($block) || $block[0] != 'normal') {
                            $this->startBlock('normal', $key);
                        } else {
                            $this->setBlock($key);
                        }
                    }
                    break;
            }
        }

        return $this->optimizeBlocks($this->_blocks, $lines);
    }

    /**
     * @param array $blocks
     * @param array $lines
     * @return array
     */
    private function optimizeBlocks(array $blocks, array $lines)
    {
        $blocks = $this->call('beforeOptimizeBlocks', $blocks, $lines);

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

        return $this->call('afterOptimizeBlocks', $blocks, $lines);
    }

    /**
     * parseCode
     *
     * @param array $lines
     * @param array $parts
     * @return string
     */
    private function parseCode(array $lines, array $parts)
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
    private function parsePre(array $lines)
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
    private function parseSh(array $lines, $num)
    {
        $addMap = false;
        if ($lines[0][-1] === '#') {
            $lines[0] = substr($lines[0], 0, -1);
            $addMap = true;
        }
        $line = $this->parseInline(trim($lines[0], '# '));
        if (!$this->addNav && !$addMap) return "<h{$num} data-line='898'>{$line}</h{$num}>";
        $name = md5($line);
        $this->href[] = ['lv' => $num, 'name' => $name, 'title' => $line];
        return preg_match("/^\s*$/", $line) ? '' : "<a name='{$name}' data-line='901' href='#top'></a><h{$num}>{$line}</h{$num}>";
    }

    /**
     * @param array $lines
     * @param int $num
     * @return string
     */
    private function parseMh(array $lines, $num)
    {
        return $this->parseSh($lines, $num);
    }

    /**
     * parseQuote
     *
     * @param array $lines
     * @return string
     */
    private function parseQuote(array $lines)
    {
        foreach ($lines as &$line) {
            $line = preg_replace("/^\s*> ?/", '', $line);
        }
        $str = implode("\n", $lines);

        return preg_match("/^\s*$/", $str) ? '' : '<blockquote>' . $this->parse($str) . '</blockquote>';
    }

    /**
     * parseList
     *
     * @param array $lines
     * @return string
     */
    private function parseList(array $lines)
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
                        $html .= "<li>" . $this->parse(implode("\n", $leftLines)) . "</li>";
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
            $html .= "<li>" . $this->parse(implode("\n", $leftLines)) . "</li></{$lastType}>";
        }

        return $html;
    }

    /**
     * @param array $lines
     * @param array $value
     * @return string
     */
    private function parseTable(array $lines, array $value)
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
                if (preg_match("/^(?:({$color})|(?:(\d+);)|(?:(\d{1,3}(?:px|%);))){1,2}(.*)$/i", $text, $matches)) {
                    $bgcolor = $matches[1];
                    $num = intval($matches[2]);
                    $width = ($matches[3]);
                    $text = $matches[4];
                }

                $html .= "<{$tag}";
                if ($num > 1) $html .= " colspan=\"{$num}\"";
                $style = '';
                if ($width) $style = "width:{$width};l:1108;";
                if (!!$bgcolor) $style .= "background:{$bgcolor};";
                if (isset($aligns[$ky]) && $aligns[$ky] != 'none') {
                    $style .= "text-align:{$aligns[$ky]};";
                }

                if (!empty($style)) $html .= " style=\"{$style}\"";
                $html .= '>' . $this->parseInline(htmlspecialchars($text)) . "</{$tag}>";
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
    private function parseHr()
    {
        return '<hr>';
    }

    /**
     * parseNormal
     *
     * @param array $lines
     * @return string
     */
    private function parseNormal(array $lines)
    {
        foreach ($lines as &$line) {
            $line = $this->parseInline($line);
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
    private function parseFootnote(array $lines, array $value)
    {
        list($space, $note) = $value;
        $index = array_search($note, $this->_footnotes);

        if (false !== $index) {
            $lines[0] = preg_replace("/^\[\^((?:[^\]]|\\]|\\[)+?)\]:/", '', $lines[0]);
            $this->_footnotes[$index] = $lines;
        }

        return '';
    }

    /**
     * parseDefine
     *
     * @return string
     */
    private function parseDefinition()
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
    private function parseHtml(array $lines, $type)
    {
        foreach ($lines as &$line) {
            $line = $this->parseInline($line, isset($this->_specialWhiteList[$type]) ? $this->_specialWhiteList[$type] : '');
        }
        return implode("\n", $lines);
    }

    /**
     * @param $str
     * @return mixed
     */
    private function escapeBracket($str)
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
    private function startBlock($type, $start, $value = NULL)
    {
        $this->_pos++;
        $this->_current = $type;
        $this->_blocks[$this->_pos] = [$type, $start, $start, $value];
    }

    /**
     * endBlock
     *
     */
    private function endBlock()
    {
        $this->_current = 'normal';
    }

    /**
     * isBlock
     *
     * @param mixed $type
     * @param mixed $value
     * @return bool
     */
    private function isBlock($type, $value = NULL)
    {
        return $this->_current == $type and (NULL === $value ? true : $this->_blocks[$this->_pos][3] == $value);
    }

    /**
     * getBlock
     *
     * @return array
     */
    private function getBlock()
    {
        return isset($this->_blocks[$this->_pos]) ? $this->_blocks[$this->_pos] : NULL;
    }

    /**
     * setBlock
     *
     * @param mixed $to
     * @param mixed $value
     */
    private function setBlock($to = NULL, $value = NULL)
    {
        if (NULL !== $to) {
            $this->_blocks[$this->_pos][2] = $to;
        }

        if (NULL !== $value) {
            $this->_blocks[$this->_pos][3] = $value;
        }

    }

    /**
     * backBlock
     *
     * @param mixed $step
     * @param mixed $type
     * @param mixed $value
     */
    private function backBlock($step, $type, $value = NULL)
    {
        if ($this->_pos < 0) {
            $this->startBlock($type, 0, $value);
        }

        $last = $this->_blocks[$this->_pos][2];
        $this->_blocks[$this->_pos][2] = $last - $step;

        if ($this->_blocks[$this->_pos][1] <= $this->_blocks[$this->_pos][2]) {
            $this->_pos++;
        }

        $this->_current = $type;
        $this->_blocks[$this->_pos] = [$type, $last - $step + 1, $last, $value];

    }

    private function combineBlock()
    {
        if ($this->_pos < 1) {
            return;
        }

        $prev = $this->_blocks[$this->_pos - 1];
        $current = $this->_blocks[$this->_pos];

        $prev[2] = $current[2];
        $this->_blocks[$this->_pos - 1] = $prev;
        $this->_current = $prev[0];
        unset($this->_blocks[$this->_pos]);
        $this->_pos--;

    }
}
