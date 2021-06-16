<?php
declare(strict_types=1);

namespace esp\core;

use esp\error\EspError;
use esp\core\face\Adapter;
use esp\library\ext\MarkdownObject;
use function \esp\helper\root;

final class View
{
    private $_path = [
        'dir' => null,
        'file' => null,
    ];
    private $_view_val = array();
    private $_layout;//框架对象
    /**
     * @var $_adapter Adapter
     */
    private $_adapter;//标签解析器对象
    private $_adapter_use;
    private $_controller;
    private $_display_type;

    public function __construct(string $dir, string $controller, $file)
    {
        $this->_path['dir'] = $dir;
        $this->_path['file'] = $file;
        $this->_controller = $controller;
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
     * 设置视图文件名
     * @param string|null $file
     * @return mixed|string
     */
    public function file(string $file = null)
    {
        if (is_null($file)) {
            return $this->_path['file'];
        } else {
            return $this->_path['file'] = $file;
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
        $fileV = $this->file() ?: $file;//以之前设置的优先
        if (strpos($fileV[0], '/') === 0) {
            $fileV = root($fileV);
        } else {
            $fileV = "{$dir}/{$fileV}";
        }

        if (!is_readable($fileV)) {
            if (!is_readable($fileT = "{$dir}/view.php")) {
                throw new EspError("视图文件({$fileV})不存在", 1);
            } else {
                $fileV = $fileT;
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
            $layout = '/layout.php';
            $layout_file = $dir . $layout;
            if (!is_readable($layout_file)) $layout_file = dirname($dir) . $layout;//上一级目录
            if (!is_readable($layout_file)) throw new EspError("框架视图文件({$layout_file})不存在", 1);
            return $this->_layout->render($layout_file, ['_view_html' => &$html]);
        }
        return $this->fetch($fileV, $value + $this->_view_val);
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
