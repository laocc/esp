<?php

namespace esp\core;

use esp\core\face\Adapter;
use esp\library\ext\Markdown;

final class View
{

    private static $_adapter;//标签解析器对象
    private static $_adapter_use;

    /**
     * 读取解析器
     */
    public static function getAdapter()
    {
        return self::$_adapter;
    }

    /**
     * 注册解析器
     */
    public static function setAdapter($object)
    {
        self::$_adapter = $object;
        return self::$_adapter_use = true;
    }

    /**
     * 注册解析器
     */
    public static function registerAdapter($object)
    {
        self::$_adapter = $object;
        return self::$_adapter_use = true;
    }

    /**
     * 读取视图目录
     * @return string
     */
    private static function path()
    {
        static $dir;
        if (!is_null($dir)) return $dir;

        $dir = Request::$directory . '/' . Request::$module . '/views/';
        if (Client::is_wap()) {
            if (is_dir($dir_wap = Request::$directory . '/' . Request::$module . '/views_wap/')) {
                $dir = $dir_wap;
            }
        }
        return $dir;
    }


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

    private static $_view_val = Array();
    private static $_view_set = [
        'view_use' => true,
        'view_file' => null,
        'layout_use' => true,
        'layout_file' => null,
    ];


    /**
     * 设置视图文件
     * @param $value
     */
    public static function setView($value)
    {
        if (is_bool($value)) {
            self::$_view_set['view_use'] = $value;
        } elseif (is_string($value)) {
            self::$_view_set['view_use'] = true;
            self::$_view_set['view_file'] = $value;
        }
    }

    /**
     * @param $value
     */
    public static function setLayout($value)
    {
        if (is_bool($value)) {
            self::$_view_set['layout_use'] = $value;
        } elseif (is_string($value)) {
            self::$_view_set['layout_use'] = true;
            self::$_view_set['layout_file'] = $value;
        }
    }


    /**
     * 解析视图结果并返回
     * @return string
     * @throws \Exception
     */
    public static function render()
    {
        self::cleared_layout_val();

        $path = root(self::path());//视图目录

        $viewFile = self::$_view_set['view_file'];
        if (substr($viewFile, 0, 1) === '/') {
            $viewFile = root($viewFile);
        } else if (empty($viewFile)) {
            $viewFile = $path . '/' . Request::$controller . '/' . Request::$action . '.php';
        } else {
            $viewFile = $path . '/' . ltrim($viewFile, '/');
        }
        if (!is_readable($viewFile) or !is_file($viewFile)) {
            throw new \Exception("视图文件({$viewFile})不存在", 400);
        }

        if (substr($viewFile, -3) === '.md') {
            $html = Markdown::html(file_get_contents($viewFile), 0);
            $html = "<article class='markdown' style='width:90%;margin:0 auto;'>{$html}</article>";

        } else if (!self::$_view_set['layout_use']) {
            $html = self::fetch($viewFile, self::$_view_val + self::$_layout_val);
            return $html;

        } else {
            $html = self::fetch($viewFile, self::$_view_val);
        }


        $layout_file = $path . '/layout.php';
        if (!is_readable($layout_file)) $layout_file = dirname($path) . '/layout.php';//上一级目录
        if (!is_readable($layout_file)) throw new \Exception("框架视图文件({$layout_file})不存在");

        return self::fetch($layout_file, self::$_layout_val + ['_view_html' => &$html]);
    }


    /**
     * 解析视图并返回
     * @param string $__file__
     * @param array $__value__
     * @return string
     */
    private static function fetch(string $__file__, array $__value__)
    {
        if (self::$_adapter_use and !is_null(self::$_adapter)) {
            self::$_adapter instanceof Adapter and 1;
            self::$_adapter->assign(self::$_view_val);
            return self::$_adapter->fetch($__file__, $__value__);
        }
        ob_start();
        extract($__value__);
        include($__file__);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * 向视图送变量
     * @param $name
     * @param $value
     */
    public static function assign(string $name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                self::$_view_val[$k] = $v;
            }
        } else {
            self::$_view_val[$name] = $value;
        }
    }

    public static function get(string $name)
    {
        return isset(self::$_view_val[$name]) ? self::$_view_val[$name] : null;
    }

    public static function set(string $name, $value)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                self::$_view_val[$k] = $v;
            }
        } else {
            self::$_view_val[$name] = $value;
        }
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
    private static function cleared_layout_val()
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
