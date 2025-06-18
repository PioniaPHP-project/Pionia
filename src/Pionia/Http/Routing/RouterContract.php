<?php

namespace Pionia\Http\Routing;

interface RouterContract
{
    public function switch(string $switch, string $version, ?array $schemas = ['https', 'http']);

}
