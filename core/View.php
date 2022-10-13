<?php
declare(strict_types=1);

namespace esp\core;

use esp\error\Error;
use esp\face\Adapter;
use esp\helper\library\ext\MarkdownObject;
use function esp\helper\in_root;
use function \esp\helper\root;

final class View implements Adapter
{
    private array $_path = [
        'dir' => null,
        'file' => null,
        'ext' => '.php',
    ];
    private View $_layout;//框架对象
    private Adapter $_adapter;//标签解析器对象

    private array $_view_val = array();
    private bool $_adapter_use = false;
    private string $_display_type;
    private array $md_conf = [];

    public function __construct(string $dir, $file, $ext)
    {
        $this->_path['dir'] = $dir;
        $this->_path['file'] = $file;
        $this->_path['ext'] = $ext;
    }

    /**
     * 设置或获取视图路径
     * @param string|null $dir
     * @return mixed|string
     */
    public function dir(string $dir = null): string
    {
        if (is_null($dir)) {
            return $this->_path['dir'];
        } else {
            if ($dir[0] !== '/') {
                $dir = dirname($this->_path['dir']) . "/{$dir}/";
            }
            return $this->_path['dir'] = $dir;
        }
    }

    /**
     * 视图接收变量
     * @param $name
     * @param $value
     */
    public function assign($name, $value = null): void
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->_view_val[$k] = $v;
            }
        } else {
            $this->_view_val[$name] = $value;
        }
    }

    final public function __set(string $name, $value): void
    {
        $this->_view_val[$name] = $value;
    }

    final public function __get(string $name)
    {
        return $this->_view_val[$name] ?? null;
    }

    final public function set(string $name, $value = null): void
    {
        $this->assign($name, $value);
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    final public function get(string $name)
    {
        return $this->_view_val[$name] ?? null;
    }


    /**
     * 设置框架对象
     * @param View $object
     * @return View
     */
    public function layout(View $object): View
    {
        $this->_layout = $object;
        return $this;
    }

    /**
     * @return Adapter
     */
    public function getAdapter(): Adapter
    {
        if (!isset($this->_adapter)) {
            throw new Error('标签解析器没有注册', 1);
        }

        return $this->_adapter;
    }

    /**
     * @param bool $use
     * @return View
     */
    public function setAdapter(bool $use): View
    {
        if ($use === false) {
            $this->_adapter_use = false;
        } else {
            if (!isset($this->_adapter)) {
                throw new Error('标签解析器没有注册', 1);
            }
            $this->_adapter_use = true;
        }
        return $this;
    }

    /**
     * @param Adapter $object
     * @return $this
     */
    public function registerAdapter(Adapter $object): View
    {
        $this->_adapter = $object;
        $this->_adapter_use = true;
        return $this;
    }

    public function display_type(string $type): View
    {
        $this->_display_type = $type;
        return $this;
    }


    public function mdConf(array $conf): View
    {
        $this->md_conf = $conf;
        return $this;
    }

    /**
     * 解析视图结果并返回
     * @param string $file
     * @param array $value
     * @return string
     */
    public function render(string $file, array $value): string
    {
        $dir = root($this->dir());
        //以之前设置的优先，这里的$file是response中根据控制器推算出来的默认视图文件名
        $fileV = $this->file() ?: $file;
        if (strpos($fileV[0], '/') === 0) {
            $fileV = root($fileV);
        } else {
            $fileV = "{$dir}/{$fileV}";
        }

        if (!is_readable($fileV)) {
            if ($this->_path['ext'] === '.php') {
                if (!is_readable($fileT = str_replace($this->_path['ext'], '.php', $fileV))) {
                    throw new Error("视图文件({$fileV})或({$fileT})不存在", 1);
                } else {
                    $fileV = $fileT;
                }
            } else {
                throw new Error("视图文件({$fileV})不存在", 1);
            }
        }

        if (isset($this->_layout)) {//先解析子视图
            if ($this->_display_type === 'md' && substr($fileV, -3) === '.md') {
                $md = new MarkdownObject($this->md_conf);
                $html = $md->render(file_get_contents($fileV));
            } else {
                $html = $this->fetch($fileV, $value + $this->_view_val);
                if ($this->_display_type === 'md') {
                    $md = new MarkdownObject($this->md_conf);
                    $html = $md->render($html);
                }
            }

            $layout_file = $this->_layout->builderViewFile($fileV);

            return $this->_layout->render($layout_file, ['_view_html' => &$html]);
        }

        return $this->fetch($fileV, $value + $this->_view_val);
    }

    /**
     * 设置视图文件名
     * @param string|null $file
     * @return mixed|string
     */
    public function file(string $file = null): string
    {
        if (is_null($file)) {
            return $this->_path['file'] ?: '';
        } else {
            return $this->_path['file'] = $file;
        }
    }

    /**
     * 读取框架layout的文件名，因layout有可能存在不同位置，所以需要单独查询
     *
     * @param string $viewPath
     * @return string
     */
    public function builderViewFile(string $viewPath): string
    {
        if (!empty($this->_path['file'])) {

            if ($this->_path['file'][0] === '/') {
                if (in_root($this->_path['file']) and is_readable($this->_path['file'])) return $this->_path['file'];
                if (is_readable(_ROOT . $this->_path['file'])) return _ROOT . $this->_path['file'];
                if (is_readable($lyFile = (_ROOT . '/application/' . _VIRTUAL . '/views' . $this->_path['file']))) {
                    $this->_path['file'] = '/application/' . _VIRTUAL . '/views' . $this->_path['file'];
                    return $lyFile;
                }
            }

            $dir0 = rtrim($this->_path['dir'], '/');
            $file = $dir0 . '/' . ltrim($this->_path['file'], '/');
            if (!is_readable($file)) {
                throw new Error("指定的框架视图文件({$file})不存在.", 1);
            }
            return $file;
        }

        $viewPath = dirname($viewPath);
        $dir1 = dirname($this->_path['dir']);
        $dir0 = rtrim($this->_path['dir'], '/');

        if ($this->_path['ext'] !== '.php') {
            if (is_readable($layout_file = "{$viewPath}/layout{$this->_path['ext']}")) return $layout_file;
            if (is_readable($layout_file = "{$dir0}/layout{$this->_path['ext']}")) return $layout_file;
            if (is_readable($layout_file = "{$dir1}/layout{$this->_path['ext']}")) return $layout_file;
        }
        if (is_readable($layout_file = "{$viewPath}/layout.php")) return $layout_file;
        if (is_readable($layout_file = "{$dir0}/layout.php")) return $layout_file;
        if (is_readable($layout_file = "{$dir1}/layout.php")) return $layout_file;

        throw new Error("自动框架视图文件({$layout_file})不存在", 1);
    }


    /**
     * 显示解析视图结果
     *
     * @param string $__file__
     * @param array $__value__
     */
    public function display(string $__file__, array $__value__): void
    {
        if ($this->_adapter_use and isset($this->_adapter)) {
            $this->_adapter->assign($this->_view_val);
            $this->_adapter->display($__file__, $__value__);
            return;
        }
        ob_start();
        extract($__value__);
        include $__file__;
        echo ob_get_clean();
    }

    /**
     * 解析视图并返回
     * @param string $__file__
     * @param array $__value__
     * @return string
     */
    public function fetch(string $__file__, array $__value__): string
    {
        if ($this->_adapter_use and isset($this->_adapter)) {
            $this->_adapter->assign($this->_view_val);
            return $this->_adapter->fetch($__file__, $__value__);
        }
        ob_start();
        extract($__value__);
        include $__file__;
        return ob_get_clean();
    }


    /**
     * echo
     *
     * @return string
     */
    public function __toString()
    {
        return print_r($this, true);
    }

    /**
     * var_dump
     * @return array
     */
    public function __debugInfo()
    {
        return [__CLASS__];
    }


}
