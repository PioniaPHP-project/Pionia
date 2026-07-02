<?php

namespace Pionia\TestSuite\Autoload;

use Pionia\Autoload\ApplicationAutoloader;
use Pionia\TestSuite\PioniaTestCase;

class ApplicationAutoloaderTest extends PioniaTestCase
{
    public function testResolvePathMapsApplicationNamespaceToLowercaseDirectories(): void
    {
        $root = sys_get_temp_dir() . '/pionia-autoload-' . uniqid();
        mkdir($root . '/switches', 0775, true);

        $file = $root . '/switches/DemoSwitch.php';
        file_put_contents($file, '<?php namespace Application\Switches; class DemoSwitch {}');

        $this->assertSame($file, ApplicationAutoloader::resolvePath($root, 'Application\\Switches\\DemoSwitch'));

        unlink($file);
        rmdir($root . '/switches');
        rmdir($root);
    }

    public function testResolvePathReturnsNullForNonApplicationClasses(): void
    {
        $this->assertNull(ApplicationAutoloader::resolvePath('/tmp', 'Vendor\\Package\\Foo'));
    }
}
