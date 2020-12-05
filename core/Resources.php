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
    private $config;

    public function __construct(Configure $configure)
    {
        $this->config = $configure;
        $_config = $configure->get('resource');
        $this->conf = $_config['default'];
        if (isset($_config[_VIRTUAL])) {
            $this->conf = array_replace_recursive($this->conf, $_config[_VIRTUAL]);
        }

        if (isset($this->conf['host'])) {
            if (!($this->conf['host'][0] === '/' or substr($this->conf['host'], 0, 4) === 'http')) {
                $this->conf['host'] = _HTTP_ . $this->conf['host'];
            }
        } else {
            $this->conf['host'] = '';
        }
    }

    public function host(): string
    {
        return rtrim($this->conf['host'], '/');
    }

    public function path(): string
    {
        return $this->conf['path'] ?? '';
    }

    public function concat(bool $run = null): bool
    {
        if (is_null($run)) return boolval($this->conf['concat'] ?? false);
        $this->conf['concat'] = $run;
        return $run;
    }

    public function get(string $key)
    {
        return $this->conf[$key] ?? null;
    }

    public function rand(): string
    {
        $res_rand = $this->config->Redis()->get('resourceRand');
        if (!$res_rand) $res_rand = $this->conf['rand'] ?? '';
        return strval($res_rand);
    }

    public function title(string $title = null): string
    {
        if (is_null($title)) return $this->conf['title'] ?? '';
        $this->conf['title'] = $title;
        return $title;
    }

    public function keywords(string $keywords = null): string
    {
        if (is_null($keywords)) return $this->conf['keywords'] ?? '';
        $this->conf['keywords'] = $keywords;
        return $keywords;
    }

    public function description(string $description = null): string
    {
        if (is_null($description)) return $this->conf['description'] ?? '';
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
        $nc = $this->conf['interchange'] ?? [];
        $path = $this->path();//resource文件路径
        $host = $this->host() ?: $path;
        $face = substr(getenv('DOCUMENT_ROOT'), strlen(_ROOT));//站点入口位置
        if (!empty($nc)) {
            $html = str_replace(array_keys($nc), array_values($nc), $html);
        }

        return str_replace([$path, '__RAND__', $face], [$host, $this->rand(), ''], $html);
    }

}