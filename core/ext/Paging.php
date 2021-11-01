<?php
declare(strict_types=1);

namespace esp\core\ext;

/**
 * Model中用的分页
 *
 * Class Paging
 */
final class Paging
{
    public $index_key = 'page';       //分页，页码键名，可以任意命名，只要不和常用的别的键冲突就可以
    public $size_key = 'size';       //分页，每页数量
    public $index = 1;//当前页码
    public $size = 0;//每页数量
    public $recode = 0;//当前批次记录数
    public $total = 0;//总页数
    public $last = 0;//最后一页剩余的记录数
    public $sum = [];

    private $autoSize = 10;
    private $isTake = false;

    public function __construct(int $sizeDefault = 0, int $index = 0, int $recode = null)
    {
        $this->index = intval($_GET[$this->index_key] ?? $index);
        if ($this->index < 1) $this->index = $index ?: 1;

        $this->size = intval($_GET[$this->size_key] ?? $sizeDefault);
        if ($this->size < 2) $this->size = $sizeDefault ?: $this->autoSize;

        if (!is_null($recode)) $this->recode = $recode;
    }

    public function index(int $index): Paging
    {
        $this->index = $index;
        return $this;
    }

    /**
     * Model中调用，送入求和数据
     *
     * @param array $sum
     * @return $this
     */
    public function sum(array $sum): Paging
    {
        $this->sum = $sum;
        return $this;
    }

    /**
     * 控制器中调用，改写求和值，
     * 在callable中采用按值引用，可改写sum值，如：function(&sum){…………}
     *
     * @param callable $fun
     * @return $this
     */
    public function callSum(callable $fun): Paging
    {
        $fun($this->sum);
        return $this;
    }


    /**
     * 在Model->list()中调用，设置当前总数，并计算页数和最后一页数
     *
     * @param int $count
     * @param bool $isTake 当前总数是估计数
     */
    public function calculate(int $count, bool $isTake = false): void
    {
        if ($this->size === 0) return;
        if ($count > 0) $this->recode = $count;
        $this->last = intval($this->recode % $this->size);//最后一页数
        $this->total = ceil($this->recode / $this->size);
        $this->isTake = $isTake;
    }

    public function value(): array
    {
        return [
            'recode' => $this->recode . ($this->isTake ? '+' : ''),//记录数
            'size' => $this->size,//每页数量
            'index' => $this->index,//当前页码
            'total' => $this->total,
            'last' => $this->last,
            'key' => $this->index_key,
            'sum' => $this->sum,
        ];
    }


}