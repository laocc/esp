<?php
declare(strict_types=1);

namespace esp\core;


/**
 * Class Resources
 * @package esp\core
 */
final class Resources
{
    private $conf;

    public function __construct(array $_config = null)
    {
        $this->conf = $_config ?: [];

        if (isset($this->conf['host'])) {
            if (!($this->conf['host'][0] === '/' or substr($this->conf['host'], 0, 4) === 'http')) {
                $this->conf['host'] = _HTTP_ . $this->conf['host'];
            }
        } else {
            $this->conf['host'] = '';
        }

        $this->conf += [
            'host' => '',
            'path' => '',
            'title' => '',
            'keywords' => '',
            'description' => '',
            'concat' => false,
            'interchange' => [],
        ];
    }

    public function host(): string
    {
        return rtrim($this->conf['host'], '/');
    }

    public function path(): string
    {
        return $this->conf['path'];
    }

    public function concat(bool $run = null): bool
    {
        if (is_null($run)) return boolval($this->conf['concat']);
        $this->conf['concat'] = $run;
        return $run;
    }

    public function get(string $key)
    {
        return $this->conf[strtolower($key)] ?? null;
    }

    public function rand(): string
    {
        return strval($this->conf['_rand']);
    }

    public function title(string $title = null): string
    {
        if (is_null($title)) return $this->conf['title'];
        $this->conf['title'] = $title;
        return $title;
    }

    public function keywords(string $keywords = null): string
    {
        if (is_null($keywords)) return $this->conf['keywords'];
        $this->conf['keywords'] = $keywords;
        return $keywords;
    }

    public function description(string $description = null): string
    {
        if (is_null($description)) return $this->conf['description'];
        $this->conf['description'] = $description;
        return $description;
    }

    public function replace(string $html): string
    {
        /**
         * interchange：目标替换设置
         * interchange[/public/resource] = //resource.domain.com
         * interchange[/public/res] = //res.domain.com
         */
        $path = $this->conf['path'];  //resource文件路径
        $host = $this->host() ?: $path;

        if (!empty($this->conf['interchange'])) {
            $html = str_replace(
                array_keys($this->conf['interchange']),
                array_values($this->conf['interchange']),
                $html);
        }
        $root = substr(getenv('DOCUMENT_ROOT'), strlen(_ROOT));//站点入口位置
        return str_replace(
            [$path, '__RAND__', $root],
            [$host, strval($this->conf['_rand']), ''],
            $html);
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