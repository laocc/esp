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

    public function __construct()
    {
        $_config = Config::get('resource');
        $this->conf = $_config['default'];
        if (isset($_config[_MODULE])) {
            $this->conf = array_replace_recursive($this->conf, $_config[_MODULE]);
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

    public function rand()
    {
        $res_rand = Config::Redis()->get('resourceRand');
        if (!$res_rand) $res_rand = $this->conf['rand'] ?? '';
        return $res_rand;
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
        $host = $this->host();
        $path = $this->path();
        if (empty($host)) $host = $path;
        return str_replace([$path, '__RAND__'], [$host, $this->rand()], $html);
    }

}