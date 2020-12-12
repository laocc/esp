<?php
declare(strict_types=1);

namespace esp\core\ext;


trait Page
{
    private $_page_key = 'page';       //分页，页码键名，可以任意命名，只要不和常用的别的键冲突就可以
    private $_size_key = 'size';       //分页，每页数量

    protected $dataCount = 0;
    protected $pageSize = 0;
    protected $pageIndex = 1;
    protected $pageSkip = 0;
    protected $lftRit = 5;

    final public function dataCount()
    {
        return $this->dataCount;
    }

    final public function pageKeyword(string $page, string $size)
    {
        $this->_page_key = $page;
        $this->_size_key = $size;
        return $this;
    }

    /**
     * @param int $size >=2
     * @param int $index
     * @param int $lftRit
     * @return $this
     */
    final public function pageSet(int $size = 0, int $index = 0, int $lftRit = 5)
    {
        $this->pageIndex = $index ?: intval($_GET[$this->_page_key] ?? 1);
        if ($this->pageIndex < 1) $this->pageIndex = 1;
        if (!$size) $size = intval($_GET[$this->_size_key] ?? 10);
        $this->pageSize = max(2, $size);
        $this->lftRit = max(2, $lftRit);
        $this->pageSkip = ($this->pageIndex - 1) * $this->pageSize;
        if ($this->pageSkip < 0) $this->pageSkip = 0;
        return $this;
    }

    final public function pageValue()
    {
        $info = [
            'recode' => $this->dataCount,//记录数
            'size' => $this->pageSize,//每页数量
            'index' => $this->pageIndex,//当前页码
            'key' => $this->_page_key,
        ];
        $info['index'] = $info['index'] ?: intval($_GET[$info['key']] ?? 1);//当前页码
        $info['last'] = (int)($info['recode'] % $info['size']);//最后一页数
        $info['page'] = (int)($info['recode'] / $info['size']);
        $info['page'] += !!$info['last'] ? 1 : 0;//总页数

        return $info;
    }


    /**
     * 组合分页连接
     * @param string $class
     * @return string
     */
    final public function pageGet(string $class = 'pageForm'): string
    {
        $info = [
            'recode' => $this->dataCount,//记录数
            'size' => $this->pageSize,//每页数量
            'index' => $this->pageIndex,//当前页码
        ];

        $key = $this->_page_key;  //URL中标识页码的键名，可以任意指定，但不要和网站其他可能的参数重名
        $_show = $this->lftRit;             //页码显示为当前页前后页数

        $info['index'] = $info['index'] ?: intval($_GET[$key] ?? 1);//当前页码

        $info['last'] = (int)($info['recode'] % $info['size']);//最后一页数
        $info['page'] = (int)($info['recode'] / $info['size']);
        $info['page'] += !!$info['last'] ? 1 : 0;//总页数

        $info['prev'] = $info['index'] - 1;//上一页
        $info['next'] = $info['index'] + 1;//下一页
        $info['prev'] < 1 and $info['prev'] = 1;
        if ($info['next'] > $info['page']) $info['next'] = $info['page'];
        if (empty($class)) $class = 'pageForm';
        if ($class[0] === '+') $class = "pageForm " . substr($class, 1);
        $link = array();
        $link[] = "<form method='get' action='?' autocomplete='off' class='{$class}'><ul>";
        $link[] = "<li><a href='?{$key}=1&[QueryString]' class='first'>&lt;&lt;</a></li>";
        $link[] = "<li><a href='?{$key}={$info['prev']}&[QueryString]' class='prev'>&lt;</a></li>";

        $get = $_GET;
        unset($get[$key]);
        foreach ($get as $_k => $_v) {
            if (is_array($_v)) $_v = implode(',', $_v);
            $link[] = "<input type='hidden' name='{$_k}' value='{$_v}'>";
        }

        $page = array();

        //页面导航的起止点
        $star = $info['index'] - $_show;
        $star < 1 and $star = 1;
        $stop = $info['index'] + $_show;
        $stop > $info['page'] and $stop = $info['page'];

        if ($star >= $_show) {
            $page[] = "<li class='omit'><a>...</a></li>";
        }

        for ($i = $star; $i <= $stop; $i++) {
            if ($i == $info['index'])
                $page[] = "<li class='active'><a>{$i}</a></li>";
            else
                $page[] = "<li class='link'><a href='?{$key}={$i}&[QueryString]'>{$i}</a></li>";
        }

        if ($stop <= ($info['page'] - $_show)) {
            $page[] = "<li class='omit'><a>...</a></li>";
        }

        $link[] = implode($page);
        $link[] = "<li><a href='?{$key}={$info['next']}&[QueryString]' class='next'>&gt;</a></li>";
        $link[] = "<li><a href='?{$key}={$info['page']}&[QueryString]' class='last'>&gt;&gt;</a></li>";
        $link[] = "<li class='total'>第{$info['index']}/{$info['page']}页 每页{$info['size']}条/共{$info['recode']}条</li>";
        $link[] = "<li class='submit'><input type='tel' onclick='this.select();' name='{$key}' id='pageIndex' value='{$info['index']}'><input id='pageGo' type='submit' value='Go'></li>";
        $link[] = "<li class='notice'></li>";
        $link[] = "</ul></form>";
        $get['_'] = mt_rand();
        return str_replace(['[QueryString]'], [http_build_query($get)], implode("", $link));
    }


    final public function pageForm($callBack = true)
    {
        $call = function (string $class = 'pageForm', string $notice = null) {
            $info = [
                'recode' => $this->dataCount,//记录数
                'size' => $this->pageSize,//每页数量
                'index' => $this->pageIndex,//当前页码
            ];

            if (!$this->_count) {
                $info['recode'] = (($this->pageIndex + $this->lftRit) * $this->pageSize);
            }

            if ($class and stripos('+abcdefghijklmnopqrstuvwxyz', $class[0]) === false) {
                list($class, $notice) = ['pageForm', $class];
            }

            $key = $this->_page_key;  //URL中标识页码的键名，可以任意指定，但不要和网站其他可能的参数重名
            $_show = $this->lftRit;             //页码显示为当前页前后页数

            $info['index'] = $info['index'] ?: intval($_GET[$key] ?? 1);//当前页码

            $info['last'] = (int)($info['recode'] % $info['size']);//最后一页数
            $info['page'] = (int)($info['recode'] / $info['size']);
            $info['page'] += !!$info['last'] ? 1 : 0;//总页数

            $info['prev'] = $info['index'] - 1;//上一页
            $info['next'] = $info['index'] + 1;//下一页
            $info['prev'] < 1 and $info['prev'] = 1;
            if ($info['next'] > $info['page']) $info['next'] = $info['page'];
            if (empty($class)) $class = 'pageForm';
            if ($class[0] === '+') $class = "pageForm " . substr($class, 1);
            $link = array();
            $link[] = "<form method='get' action='?' autocomplete='off' class='{$class}'><ul>";
            if ($notice and $notice[0] === '<') $link[] = "<li style='padding-right: 3px;'>{$notice}</li>";
            $link[] = "<li><a href='?{$key}=1&[QueryString]' class='page first'>&lt;&lt;</a></li>";
            $link[] = "<li><a href='?{$key}={$info['prev']}&[QueryString]' class='page prev'>&lt;</a></li>";

            $get = $_GET;
            unset($get[$key]);
            foreach ($get as $_k => $_v) {
                if (is_array($_v)) $_v = implode(',', $_v);
                $link[] = "<input type='hidden' name='{$_k}' value='{$_v}'>";
            }

            $page = array();

            //页面导航的起止点
            $star = $info['index'] - $_show;
            $star < 1 and $star = 1;
            $stop = $info['index'] + $_show;
            $stop > $info['page'] and $stop = $info['page'];

            if ($star >= $_show) {
                $page[] = "<li class='omit'><a>...</a></li>";
            }

            for ($i = $star; $i <= $stop; $i++) {
                if ($i == $info['index'])
                    $page[] = "<li class='active link'><a class='page' href='?{$key}={$i}&[QueryString]'>{$i}</a></li>";
                else
                    $page[] = "<li class='link'><a class='page' href='?{$key}={$i}&[QueryString]'>{$i}</a></li>";
            }

            if ($stop <= ($info['page'] - $_show)) {
                $page[] = "<li class='omit'><a>...</a></li>";
            }

            $link[] = implode($page);
            $link[] = "<li><a href='?{$key}={$info['next']}&[QueryString]' class='page next'>&gt;</a></li>";
            $link[] = "<li><a href='?{$key}={$info['page']}&[QueryString]' class='page last'>&gt;&gt;</a></li>";
            if ($this->_count) {
                $link[] = "<li class='total'>第{$info['index']}/{$info['page']}页 每页{$info['size']}条/共{$info['recode']}条</li>";
            } else {
                $link[] = "<li class='total'>第{$info['index']}页 每页{$info['size']}条</li>";
            }

            $link[] = "<li class='submit'><input type='tel' onclick='this.select();' name='{$key}' id='pageIndex' value='{$info['index']}'><input id='pageGo' type='submit' value='Go'></li>";

            if ($notice and $notice[0] !== '<') $link[] = "<li class='notice'>{$notice}</li>";

            $link[] = "</ul></form>";
            $get['_'] = mt_rand();
            return str_replace(['[QueryString]'], [http_build_query($get)], implode("", $link));
        };

        return $callBack ? $call : $call();
    }

}