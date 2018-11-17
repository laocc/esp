<?php

namespace esp\core;


class Layout
{

    /**
     * Layout变量相对固定
     * @var array
     */
    private static $_layout_val = [
        '_js_foot' => [],
        '_js_head' => [],
        '_js_body' => [],
        '_js_defer' => [],
        '_css' => [],
        '_meta' => ['keywords' => null, 'description' => null],
        '_title' => null,
        '_title_default' => true,
    ];

    public static function getValue()
    {
        return self::$_layout_val;
    }

    public static function assign(string $name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                self::$_layout_val[$k] = $v;
            }
        } else {
            self::$_layout_val[$name] = $value;
        }
    }

    /**
     * @param $path
     * @return string
     * @throws \Exception
     */
    public static function getFile($path)
    {
        $layout_file = $path . '/layout.php';
        if (!is_readable($layout_file)) $layout_file = dirname($path) . '/layout.php';//上一级目录
        if (!is_readable($layout_file)) throw new \Exception("框架视图文件({$layout_file})不存在");
        return $layout_file;
    }

    public static function setJs($file, $pos = 'foot')
    {
        $pos = in_array($pos, ['foot', 'head', 'body', 'defer']) ? $pos : 'foot';
        if (is_array($file)) {
            array_push(self::$_layout_val["_js_{$pos}"], ...$file);
        } else {
            self::$_layout_val["_js_{$pos}"][] = $file;
        }
    }


    public static function setCss($file)
    {
        if (is_array($file)) {
            array_push(self::$_layout_val['_css'], ...$file);
        } else {
            self::$_layout_val['_css'][] = $file;
        }
    }


    public static function setMeta(string $name, $value)
    {
        self::$_layout_val['_meta'][$name] = $value;
    }


    public static function setTitle(string $title, bool $default = true)
    {
        self::$_layout_val['_title'] = $title;
        if (!$default) self::$_layout_val['_title_default'] = false;
    }


    public static function setKeywords(string $value)
    {
        self::$_layout_val['_meta']['keywords'] = $value;
    }


    public static function setDescription(string $value)
    {
        self::$_layout_val['_meta']['description'] = $value;
    }

    /**
     * 整理layout中的变量
     */
    public static function cleared_layout_val()
    {
        $resource = Config::get('resource.default');
        $module = Config::get('resource.' . _MODULE);
        if (is_array($module)) $resource = $module + $resource;

        $dom = rtrim($resource['domain'] ?? '', '/');

        $domain = function ($item) use ($dom, $resource) {
            if (substr($item, 0, 4) === 'http') return $item;
            if (substr($item, 0, 1) === '.') return $item;
            if (substr($item, 0, 2) === '//') return substr($item, 1);
            if ($item === 'jquery') $item = $resource['jquery'];
            return $dom . '/' . ltrim($item, '/');
        };

        if ($resource['concat'] ?? false) {
            foreach (['foot', 'head', 'body', 'defer'] as $pos) {
                if (!empty(self::$_layout_val["_js_{$pos}"])) {
                    $defer = ($pos === 'defer') ? ' defer="defer"' : null;
                    $concat = Array();
                    $http = Array();
                    foreach (self::$_layout_val["_js_{$pos}"] as &$js) {
                        if ($js === 'jquery') $js = $resource['jquery'] ?? '';
                        if (substr($js, 0, 4) === 'http') {
                            $http[] = "<script type=\"text/javascript\" src=\"{$js}\" charset=\"utf-8\" {$defer} ></script>";
                        } else {
                            $concat[] = $js;
                        }
                    }
                    $concat = empty($concat) ? null : ($dom . '??' . implode(',', $concat));
                    self::$_layout_val["_js_{$pos}"] = '';
                    if ($concat) self::$_layout_val["_js_{$pos}"] .= "<script type=\"text/javascript\" src=\"{$concat}\" charset=\"utf-8\" {$defer} ></script>\n";
                    if (!empty($http)) self::$_layout_val["_js_{$pos}"] .= implode("\n", $http) . "\n";
                } else {
                    self::$_layout_val["_js_{$pos}"] = null;
                }
            }
            if (!empty(self::$_layout_val['_css'])) {
                $concat = Array();
                $http = Array();
                foreach (self::$_layout_val['_css'] as &$css) {
                    if (substr($css, 0, 4) === 'http') {
                        $http[] = "<link rel=\"stylesheet\" href=\"{$css}\" charset=\"utf-8\" />";
                    } else {
                        $concat[] = $css;
                    }
                }
                $concat = empty($concat) ? null : ($dom . '??' . implode(',', self::$_layout_val['_css']));
                self::$_layout_val['_css'] = '';
                if ($concat) self::$_layout_val['_css'] .= "<link rel=\"stylesheet\" href=\"{$concat}\" charset=\"utf-8\" />\n";
                if (!empty($http)) self::$_layout_val['_css'] .= implode("\n", $http) . "\n";

            } else {
                self::$_layout_val['_css'] = null;
            }
        } else {
            foreach (['foot', 'head', 'body', 'defer'] as $pos) {
                $defer = ($pos === 'defer') ? ' defer="defer"' : null;
                foreach (self::$_layout_val["_js_{$pos}"] as $i => &$js) {
                    $js = "<script type=\"text/javascript\" src=\"{$domain($js)}\" charset=\"utf-8\" {$defer} ></script>";
                }
                self::$_layout_val["_js_{$pos}"] = implode("\n", self::$_layout_val["_js_{$pos}"]) . "\n";
            }
            foreach (self::$_layout_val['_css'] as $i => &$css) {
                $css = "<link rel=\"stylesheet\" href=\"{$domain($css)}\" charset=\"utf-8\" />";
            }
            self::$_layout_val['_css'] = implode("\n", self::$_layout_val['_css']) . "\n";
        }

        self::$_layout_val['_meta']['keywords'] = self::$_layout_val['_meta']['keywords'] ?: $resource['keywords'] ?? '';
        self::$_layout_val['_meta']['description'] = self::$_layout_val['_meta']['description'] ?: $resource['description'] ?? '';

        foreach (self::$_layout_val['_meta'] as $i => &$meta) {
            self::$_layout_val['_meta'][$i] = "<meta name=\"{$i}\" content=\"{$meta}\" />";
        }
        self::$_layout_val['_meta'] = implode("\n", self::$_layout_val['_meta']) . "\n";

        if (is_null(self::$_layout_val['_title'])) {
            self::$_layout_val['_title'] = $resource['title'] ?? '';
        } elseif (self::$_layout_val['_title_default']) {
            self::$_layout_val['_title'] .= ' - ' . $resource['title'] ?? '';
        }
        unset(self::$_layout_val['_title_default']);
    }

}