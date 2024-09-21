<?php

namespace Pionia\Builtins\Composer;

use Composer\Script\Event;

class PioComposer
{
    private static array $postCreateProject = [
        __DIR__.DIRECTORY_SEPARATOR.'Scripts'.DIRECTORY_SEPARATOR.'rename.php',
    ];
    /**
     * Run post project creation scripts
     *
     * @param Event $event
     */
    public static function postCreateProjectScripts(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        require $vendorDir . '/autoload.php';

        // register all the scripts here to run after composer install
        foreach (self::$postCreateProject as $script) {
            require $script;
        }
    }

}
