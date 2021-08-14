<?php
declare(strict_types=1);

namespace esp\core;

use esp\error\EspError;
use esp\face\Adapter;
use esp\library\ext\MarkdownObject;
use function \esp\helper\root;

final class View
{
    private $_path = [
        'dir' => null,
        'file' => null,
        'ext' => '.php',
    ];
    private $_view_val = array();
    private $_layout;//框架对象
    /**
     * @var $_adapter Adapter
     */
    private $_adapter;//标签解析器对象
    private $_adapter_use;
    private $_display_type;

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
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
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
        return isset($this->_view_val[$name]) ? $this->_view_val[$name] : null;
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
        return $this->_adapter;
    }

    /**
     * @param $use
     * @return $this
     * @throws EspError
     */
    public function setAdapter($use): View
    {
        if ($use === false) {
            $this->_adapter_use = false;
        } elseif ($use === true) {
            if (is_null($this->_adapter)) {
                throw new EspError('标签解析器没有注册，请在已注册过的插中注册标签解析器', 1);
            }
            $this->_adapter_use = true;
        }
        return $this;
    }

    /**
     * @param $object
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

    private $md_conf = [];

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
     * @throws EspError
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
                    throw new EspError("视图文件({$fileV})或({$fileT})不存在", 1);
                } else {
                    $fileV = $fileT;
                }
            } else {
                throw new EspError("视图文件({$fileV})不存在", 1);
            }
        }

        if ($this->_layout instanceof View) {//先解析子视图
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
     * @throws EspError
     */
    public function builderViewFile(string $viewPath): string
    {
        $dir0 = rtrim($this->_path['dir'], '/');
        if (!empty($this->_path['file'])) {
            $file = $dir0 . '/' . ltrim($this->_path['file'], '/');
            if (!is_readable($file)) {
                throw new EspError("指定的框架视图文件({$file})不存在.", 1);
            }
            return $file;
        }

        $viewPath = dirname($viewPath);
        $dir1 = dirname($this->_path['dir']);

        if ($this->_path['ext'] !== '.php') {
            if (is_readable($layout_file = "{$viewPath}/layout{$this->_path['ext']}")) return $layout_file;
            if (is_readable($layout_file = "{$dir0}/layout{$this->_path['ext']}")) return $layout_file;
            if (is_readable($layout_file = "{$dir1}/layout{$this->_path['ext']}")) return $layout_file;
        }
        if (is_readable($layout_file = "{$viewPath}/layout.php")) return $layout_file;
        if (is_readable($layout_file = "{$dir0}/layout.php")) return $layout_file;
        if (is_readable($layout_file = "{$dir1}/layout.php")) return $layout_file;

        throw new EspError("自动框架视图文件({$layout_file})不存在", 1);
    }


    /**
     * 显示解析视图结果
     * @param $file
     * @param $value
     * @throws EspError
     */
    public function display($file, $value): void
    {
        echo $this->render($file, $value);
    }


    /**
     * 解析视图并返回
     * @param string $__file__
     * @param array $__value__
     * @return string
     */
    private function fetch(string $__file__, array $__value__): string
    {
        if ($this->_adapter_use and !is_null($this->_adapter)) {
            $this->_adapter->assign($this->_view_val);
            return $this->_adapter->fetch($__file__, $__value__);
        }
        ob_start();
        extract($__value__);
        include $__file__;
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
