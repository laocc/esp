<?php

namespace esp\core\ext;


use esp\core\Input;

class Page
{
    private $Key = '_P';       //分页，页码键名，可以任意命名，只要不和常用的别的键冲突就可以
    private $Count = 0;
    private $Size = 0;
    private $Index = 1;
    private $Skip = 0;
    private $PrevNext = 5;//页码显示为当前页前后页数


    public function __construct(int $size = 10, int $index = 0, string $key = null)
    {
        $this->Size = $size ?: 10;
        if (!is_null($key)) $this->Key = $key;

        $this->Index = $index ?: Input::get($this->Key, 1);
        if ($this->Index < 1) $this->Index = 1;
        $this->Skip = ($this->Index - 1) * $this->Size;
        if ($this->Skip < 0) $this->Skip = 0;
    }

    /**
     * 总数
     * @return int
     */
    final public function count(int $count = null): int
    {
        if ($count) $this->Count = $count;
        return $this->Count;
    }

    final public function size(int $size = null): int
    {
        if ($size) $this->Size = $size;
        return $this->Size;
    }

    final public function index(int $index = null): int
    {
        if ($index) $this->Index = $index;
        return $this->Index;
    }

    final public function skip(int $skip = null): int
    {
        if ($skip) $this->Skip = $skip;
        return $this->Skip;
    }

    final public function key(string $key)
    {
        $this->Key = $key;
        return $this;
    }


    /**
     * 组合分页连接
     * @param string $classForm
     * @return string
     */
    final public function html($class = ''): string
    {

        $classLayui = [
            'form' => 'layui-btn-group',
            'first' => 'layui-btn layui-btn-primary layui-btn-sm',
            'last' => 'layui-btn layui-btn-primary layui-btn-sm',
            'prev' => 'layui-btn layui-btn-primary layui-btn-sm',
            'next' => 'layui-btn layui-btn-primary layui-btn-sm',
            'omit' => 'layui-btn layui-btn-sm layui-btn-disabled',
            'active' => 'layui-btn layui-btn-sm layui-btn-danger',
            'link' => 'layui-btn layui-btn-primary layui-btn-sm',
            'total' => 'layui-btn layui-btn-primary layui-btn-sm',
            'quick' => 'layui-input',
            'input' => 'input',
            'submit' => 'layui-btn layui-btn-primary layui-btn-sm',
        ];

        $classAuto = [
            'form' => 'pageForm',
            'first' => 'first',
            'last' => 'last',
            'prev' => 'prev',
            'next' => 'next',
            'omit' => 'omit',
            'active' => 'active',
            'link' => 'link',
            'total' => 'total',
            'quick' => 'quick',
            'input' => 'input',
            'submit' => 'submit',
        ];

        if (is_array($class)) {
            $class += $classAuto;
        } else if ($class === 'layui') {
            $class = $classLayui;
        } else if ($class === '') {
            $class = $classAuto;
        } else {
            $class = ['form' => $class] + $classAuto;
        }


        $info = [
            'recode' => $this->Count,//记录数
            'size' => max(2, $this->Size),//每页数量
            'index' => $this->Index,//当前页码
        ];


        $info['index'] = $info['index'] ?: Input::get($this->Key, 1);//当前页码

        $info['last'] = (int)($info['recode'] % $info['size']);//最后一页数
        $info['page'] = (int)($info['recode'] / $info['size']);
        $info['page'] += !!$info['last'] ? 1 : 0;//总页数

        $info['prev'] = $info['index'] - 1;//上一页
        $info['next'] = $info['index'] + 1;//下一页
        $info['prev'] < 1 and $info['prev'] = 1;
        if ($info['next'] > $info['page']) $info['next'] = $info['page'];

        $link = Array();
        $link[] = "<form method='get' action='?' autocomplete='off' class='{$class['form']}'><ul>";
        $link[] = "<li class='{$class['first']}'><a href='?{$this->Key}=1&[QueryString]' >&lt;&lt;</a></li>";
        $link[] = "<li class='{$class['prev']}'><a href='?{$this->Key}={$info['prev']}&[QueryString]'>&lt;</a></li>";

        $get = $_GET;
        unset($get[$this->Key]);
        foreach ($get as $_k => $_v) {
            $link[] = "<input type='hidden' name='{$_k}' value='{$_v}'>";
        }

        $page = Array();

        //页面导航的起止点
        $star = $info['index'] - $this->PrevNext;
        $star < 1 and $star = 1;
        $stop = $info['index'] + $this->PrevNext;
        $stop > $info['page'] and $stop = $info['page'];

        if ($star >= $this->PrevNext) {
            $page[] = "<li class='{$class['omit']}'><a>...</a></li>";
        }

        for ($i = $star; $i <= $stop; $i++) {
            if ($i == $info['index'])
                $page[] = "<li class='{$class['active']}'><a>{$i}</a></li>";
            else
                $page[] = "<li class='{$class['link']}'><a style='display: inline-block;width:100%;height:100%;' href='?{$this->Key}={$i}&[QueryString]'>{$i}</a></li>";
        }

        if ($stop <= ($info['page'] - $this->PrevNext)) {
            $page[] = "<li class='{$class['omit']}'><a>...</a></li>";
        }

        $link[] = implode($page);
        $link[] = "<li class='{$class['next']}'><a href='?{$this->Key}={$info['next']}&[QueryString]'>&gt;</a></li>";
        $link[] = "<li class='{$class['last']}'><a href='?{$this->Key}={$info['page']}&[QueryString]'>&gt;&gt;</a></li>";
        $link[] = "<li class='{$class['total']}'>第{$info['index']}/{$info['page']}页&nbsp;每页{$info['size']}条/共{$info['recode']}条</li>";
        $link[] = "<li class='{$class['quick']}'>
                    <input type='tel' class='{$class['input']}' onclick='this.select();' name='{$this->Key}' id='pageIndex' value='{$info['index']}'>
                    <input id='pageGo' class='{$class['submit']}' type='submit' value='Go'></li>";

        $link[] = "</ul></form>";
        $get['_'] = mt_rand();
        return str_replace(['[QueryString]'], [http_build_query($get)], implode("", $link));
    }

}