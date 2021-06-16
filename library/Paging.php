<?php

declare(strict_types=1);

namespace esp\library;

/**
 * Model中用的分页
 *
 * Class Paging
 * @package esp\library
 */
final class Paging
{
    public $key = 'page';       //分页，页码键名，可以任意命名，只要不和常用的别的键冲突就可以
    public $size_key = 'size';       //分页，每页数量
    public $index = 1;//当前页码
    public $size = 0;//每页数量
    public $recode = 0;//当前批次记录数
    public $total = 0;//总页数
    public $last = 0;//最后一页剩余的记录数

    private $autoSize = 10;

    public function __construct(int $size = 0, int $index = 0)
    {
        $this->index = $index ?: intval($_GET[$this->key] ?? 1);
        if ($this->index < 1) $this->index = 1;
        if (!$size) $size = intval($_GET[$this->size_key] ?? 0);
        if (!$size) $size = $this->autoSize;
        $this->size = max(2, $size);
    }

    public function index(int $index)
    {
        $this->index = $index;
        return $this;
    }

    public function calculate(int $count)
    {
        if ($this->size === 0) return;
        $this->recode = $count;
        $this->last = intval($this->recode % $this->size);//最后一页数
        $this->total = ceil($this->recode / $this->size);
    }

    public function value(): array
    {
        return [
            'recode' => $this->recode,//记录数
            'size' => $this->size,//每页数量
            'index' => $this->index,//当前页码
            'total' => $this->total,
            'last' => $this->last,
            'key' => $this->key,
        ];
    }


}