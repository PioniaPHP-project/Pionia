<?php

namespace Pionia\Templating;

use DIRECTORIES;
use Pionia\Cache\Cacheable;
use Symfony\Component\Filesystem\Filesystem;

class TemplateEngine implements TemplateEngineInterface {
    use Cacheable;
    private array $blocks = [];
    private bool $cache_enabled = FALSE;

    public function view($file, $data = array()): void
    {
        $cached_file = $this->cached($file);
        extract($data, EXTR_SKIP);
        require $cached_file;
    }

    public function parse($file, $data = array()): string
    {
        $cached_file = $this->cached($file);
        extract($data, EXTR_SKIP);
        return $cached_file;
    }

    private function cachePath(): string
    {
        return alias(DIRECTORIES::CACHE_DIR->name).DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR;
    }

    private function cached($file): string
    {
        $fs = new Filesystem();
        $fileName = md5(basename($file));
        if ($this->hasCache($fileName, true)) {
            $cached_file = $this->getCache($fileName, true);
            if ($fs->exists($cached_file)) {
                return $cached_file;
            }
        }

        $cache_path = $this->cachePath();
        $cached_file = $cache_path . $fileName . '.php';

        if (!$fs->exists($cache_path)) {
            $fs->mkdir($cache_path, 0744);
        }

        if (!$this->cache_enabled || !file_exists($cached_file) || filemtime($cached_file) < filemtime($file)) {
            $code = $this->includeFiles($file);
            $code = $this->compileCode($code);
            $fs->dumpFile($cached_file, '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code);
        }
        $this->setCache($fileName, $cached_file, 1000000, true);
        return $cached_file;
    }

    static function clearCache(): void
    {
        $self = new self();
        $cache_path = $self->cachePath();
        $fs = new Filesystem();
        $fs->remove($cache_path);
    }

    private function compileCode($code): array|string|null
    {
        $code = $this->compileBlock($code);
        $code = $this->compileYield($code);
        $code = $this->compileEscapedEchos($code);
        $code = $this->compileEchos($code);
        return $this->compilePHP($code);
    }

    private function includeFiles($file): array|string|null
    {
        $code = file_get_contents($file);
        preg_match_all('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            $code = str_replace($value[0], self::includeFiles($value[2]), $code);
        }
        return preg_replace('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', '', $code);
    }

    private function compilePHP($code): array|string|null
    {
        return preg_replace('~\{%\s*(.+?)\s*\%}~is', '<?php $1 ?>', $code);
    }

    private function compileEchos($code): array|string|null
    {
        return preg_replace('~\{{\s*(.+?)\s*\}}~is', '<?php echo $1 ?>', $code);
    }

    static function compileEscapedEchos($code): array|string|null
    {
        return preg_replace('~\{{{\s*(.+?)\s*\}}}~is', '<?php echo htmlentities($1, ENT_QUOTES, \'UTF-8\') ?>', $code);
    }

    private function compileBlock($code) {
        preg_match_all('/{% ?block ?(.*?) ?%}(.*?){% ?endblock ?%}/is', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            if (!array_key_exists($value[1], $this->blocks)) $this->blocks[$value[1]] = '';
            if (strpos($value[2], '@parent') === false) {
                $this->blocks[$value[1]] = $value[2];
            } else {
                $this->blocks[$value[1]] = str_replace('@parent', $this->blocks[$value[1]], $value[2]);
            }
            $code = str_replace($value[0], '', $code);
        }
        return $code;
    }

    private function compileYield($code): array|string|null
    {
        foreach($this->blocks as $block => $value) {
            $code = preg_replace('/{% ?yield ?' . $block . ' ?%}/', $value, $code);
        }
        return preg_replace('/{% ?yield ?(.*?) ?%}/i', '', $code);
    }

}
