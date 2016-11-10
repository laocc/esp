<?php
namespace esp\extend\tools;
/*
 *
    打包压缩：create新建，append追加
    public function zipAction()
    {
        $zip = new \tools\Zip();
        $c = $zip->file('code/tmp.zip')
            ->add_string('Tools00.php', '中华人民共和国')
            ->add_file(__FILE__)
            ->add_path('code', '/.+(png)$/')
            ->create();
        var_dump($c);
    }

    解压：unzip()
    public function unzipAction()
    {
        $zip = new \tools\Zip();
        $c = $zip->file('code/tmp.zip')
            ->path('code/temps')
            ->unzip();
        var_dump($c);
    }

 */

class Zip
{
    private $_resource = [];
    private $_zip_filename;
    private $_save_path;
    private $_extract_path;
    private $_error = [];
    private $_ignore = false;//忽略文件不存在的错误
    private $_kill = false;//解压后是否删除zip文件

    public function __construct()
    {
        if (!class_exists('\ZipArchive')) {
            exit('ZipArchive 没有安装，请重新编译PHP，并加上【--enable-zip】参数。');
        }
    }

    /**
     * ZIP文件名
     * @param $filename
     * @return $this
     */
    public function file($filename)
    {
        $this->_zip_filename = root($filename);
        $this->_save_path = dirname($this->_zip_filename);
        return $this;
    }

    /**
     * 忽略文件不存在的错误
     * @param bool $bool
     * @return $this
     */
    public function ignore($bool = true)
    {
        $this->_ignore = $bool;
        return $this;
    }

    /**
     * 完成后是否删除原文件
     * @param bool $bool
     * @return $this
     */
    public function kill($bool = true)
    {
        $this->_kill = $bool;
        return $this;
    }


    ////////////////////////////////////解压//////////////////////////////////////////

    /**
     * 解压至哪个目录
     * @param $path
     * @return $this
     */
    public function path($path)
    {
        $this->_extract_path = $path;
        return $this;
    }


    /**
     * 解压
     * @param null $file
     * @param null $path 若不指定目录，则以zip文件名作为文件夹名，解压至zip所在目录
     * @return bool|mixed|string
     */
    public function unzip($file = null, $path = null)
    {
        $file = $file ?: $this->_zip_filename;
        $path = $path ?: $this->_extract_path;
        list($file, $path) = root($file, $path);
        if (!is_file($file)) {
            return "源文件{$file}不存在";
        }
        if (!$path) {
            $path = dirname($file) . '/' . pathinfo($file, PATHINFO_FILENAME);
        }
        $zip = new \ZipArchive();
        $res = $zip->open($file);
        if ($res !== true) return $res;
        $zip->extractTo($path);
        $zip->close();
        if ($this->_kill) @unlink($file);
        return true;
    }


    ////////////////////////////////////压缩//////////////////////////////////////////


    /**
     * 添加一个文件
     * @param $source_filename
     * @param null $save_name
     * @return $this
     */
    public function add_file($source_filename, $save_name = null)
    {
        $source_filename = root($source_filename);
        if (!is_file($source_filename)) {
            $this->_error[] = "源文件{$source_filename}不存在";
            return $this;
        }
        if (!$save_name) $save_name = basename($source_filename);
        $this->_resource[] = ['save' => $save_name, 'file' => $source_filename];
        $this->_save_path = dirname($source_filename);
        return $this;
    }

    /**
     * 添加一个文本文件，内容由$string创建
     * @param $save_name
     * @param $string
     * @return $this
     */
    public function add_string($save_name, $string = null)
    {
        if (is_array($string)) {
            $string = print_r($string, true);
            $string = str_replace("\n", "\r\n", $string);
        }
        $this->_resource[] = ['save' => $save_name, 'string' => $string];
        return $this;
    }

    /**
     * 添加一个文件夹，用glob模式
     * @param string $pattern glob模式
     * @param int $flags glob标识
     * @param array $options 选项
     * @return $this
     */
    public function add_glob($mode, $flags = 0, $options = [])
    {
        $this->_resource[] = ['mode' => $mode, 'flags' => $flags, 'option' => $options];
        return $this;
    }

    /**
     * 添加一个文件夹，用正则模式
     * @param string $path 目标路径
     * @param string $pattern 搜索文件的正则表达式
     * @param null $dir 保存到哪个位置，如果不指定，则以目标路径的最后一个文件夹名作为位置
     * @return $this
     */
    public function add_path($path, $pattern = '*', $dir = null)
    {
        $path = root($path);
        if (!is_dir($path)) {
            $this->_error[] = "源路径{$path}不存在";
            return $this;
        }

        if (preg_match('/^[\w\/]+$/', $pattern)) {
            list($pattern, $dir) = ['*', $pattern];
        }

        if ($pattern === '.' or $pattern === '*') {//全部
            $pattern = '/.+/';

        } else if (preg_match('/^\.[a-z0-9]{1,7}$/i', $pattern)) {//指定后缀
            $pattern = '/' . preg_quote($pattern) . '$/i';

        } else if (preg_match('/^(\*?)(\w+)(\*?)$/i', $pattern, $mac)) {
            $pattern = '/^' . ($mac[1] ? '.+' : '') . $mac[2] . ($mac[3] ? '.+' : '') . '$/i';

        } else if (!preg_match('/^(\/|\\|\#|\@|\|)(.+)\1[umIx]{0,4}$/i', $pattern)) {
            $this->_error[] = "正则表达式【{$pattern}】不合法";
            return $this;
        }
        var_dump($pattern);

        if (!$dir) $dir = strrchr($path, "/");//取最后一节
        $dir = trim(trim($dir, '/'), '\\');

        $options = array('add_path' => "{$dir}/", 'remove_path' => $path);
        $this->_resource[] = ['pattern' => $pattern, 'path' => $path, 'option' => $options];
        $this->_save_path = $path;
        return $this;
    }

    /**
     * 添加一个空文件夹
     * @param $dir
     * @return $this
     */
    public function add_dir($dir)
    {
        $this->_resource[] = ['dir' => $dir];
        return $this;
    }

    /**
     * 合成ZIP文件名
     * @return string
     */
    private function get_filename()
    {
        if (!$this->_zip_filename) {
            if ($this->_save_path) {
                $this->_zip_filename = rtrim($this->_save_path, '/') . '/' . microtime(true) . '.zip';
            } else {
                $this->_zip_filename = mt_rand() . '.zip';
            }
        }
        $ext = strtolower(pathinfo($this->_zip_filename, PATHINFO_EXTENSION));
        if ($ext !== 'zip') $this->_zip_filename .= '.zip';
        return root($this->_zip_filename);
    }

    /**
     * 新建模式，如果文件存在则先删除原来的文件
     * @param null $file
     * @return mixed|string
     */
    public function create($file = null)
    {
        if ($file) $this->file($file);
        if (!$this->_ignore and !empty($this->_error)) {
            return $this->_error;
        }
        $file = $this->get_filename();
        if (is_file($file)) @unlink($file);
        return $this->make_zip(\ZipArchive::CREATE, $file);
//        return $this->make_zip(\ZipArchive::OVERWRITE);
    }

    /**
     * 追加模式，若文件存在，则往里面追加
     * @param null $file
     * @return mixed|string
     */
    public function append($file = null)
    {
        if ($file) $this->file($file);
        if (!$this->_ignore and !empty($this->_error)) {
            return $this->_error;
        }
        return $this->make_zip(\ZipArchive::CREATE);
    }

    /**
     * 压缩打包
     * @param $type
     * @param null $file
     * @return mixed|string
     */
    private function make_zip($type, $file = null)
    {
        $filename = $file ?: $this->get_filename();
        $zip = new \ZipArchive();
        $res = $zip->open($filename, $type);
        if ($res !== true) {
            var_dump($filename, is_file($filename), $type, $res);
            return $res;
        }

        foreach ($this->_resource as &$fil) {
            if (isset($fil['file'])) {
                $zip->addFile($fil['file'], $fil['save']);

            } elseif (isset($fil['string'])) {
                $zip->addFromString($fil['save'], $fil['string']);

            } elseif (isset($fil['path'])) {
                $zip->addPattern($fil['pattern'], $fil['path'], $fil['option']);

            } elseif (isset($fil['dir'])) {
                $zip->addEmptyDir($fil['dir']);

            } elseif (isset($fil['mode'])) {
                $zip->addGlob($fil['mode'], $fil['flags'], $fil['option']);
            }
        }
        $zip->close();
        $this->kill_resource();
        return $filename;
    }

    /**
     * 删除压缩时的源文件
     */
    private function kill_resource()
    {
        if (!$this->_kill) return;

        foreach ($this->_resource as &$fil) {
            if (isset($fil['file'])) {
                unlink($fil['file']);

            } elseif (isset($fil['path'])) {
                $this->kill_dir($fil['path'], $fil['pattern']);
            }
        }
    }

    private function kill_dir($dir, $pattern)
    {
        $op = dir($dir);
        while ($of = $op->read()) {
            if ($of == '.' or $of == '..') continue;
            if (preg_match($pattern, $of)) {
                if (is_dir("{$op->path}/{$of}")) {
                    $this->kill_dir("{$op->path}/{$of}", $pattern);
                } else {
                    unlink("{$op->path}/{$of}");
                }
            }
        }

    }

}