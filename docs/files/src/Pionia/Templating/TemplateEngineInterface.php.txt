<?php

namespace Pionia\Templating;

interface TemplateEngineInterface
{
    /**
     * Render a template file
     * @param $file
     * @param array|null $data
     * @return void
     */
    public function view($file, ?array $data = []): void;

    /**
     * Parse the template file and return the content as string
     * @param $file
     * @param array|null $data
     * @return string
     */
    public function parse($file, ?array $data = null): string;
    /**
     * Clear the cached files
     * @return void
     */
    static function clearCache(): void;
}
