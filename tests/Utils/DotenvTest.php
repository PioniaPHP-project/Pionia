<?php

namespace Utils;

use Pionia\TestSuite\PioniaTestCase;
use Pionia\Utils\Dotenv;
use Pionia\Utils\Filesystem;

class DotenvTest extends PioniaTestCase
{
    public function testLoadEnvParsesKeyValuePairs(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pionia_dotenv_' . uniqid() . '.env';
        file_put_contents($path, "APP_NAME='Sample App'\nDEBUG=true\n# comment\n\nPORT=8003\n");

        $dotenv = new Dotenv();
        $dotenv->loadEnv($path);

        $this->assertSame('Sample App', $_ENV['APP_NAME']);
        $this->assertSame('8003', $_ENV['PORT']);
        $this->assertStringContainsString('APP_NAME', (string) $_ENV['PIONIA_ENV_VARS']);

        unlink($path);
    }

    public function testPopulateRespectsOverrideFlag(): void
    {
        putenv('KEEP_ME=original');
        $_ENV['KEEP_ME'] = 'original';
        $_SERVER['KEEP_ME'] = 'original';

        $dotenv = new Dotenv();
        $dotenv->populate(['KEEP_ME' => 'changed', 'NEW_KEY' => 'added'], false);

        $this->assertSame('original', $_ENV['KEEP_ME']);
        $this->assertSame('added', $_ENV['NEW_KEY']);

        $dotenv->populate(['KEEP_ME' => 'changed'], true);
        $this->assertSame('changed', $_ENV['KEEP_ME']);

        putenv('KEEP_ME');
        unset($_ENV['KEEP_ME'], $_SERVER['KEEP_ME'], $_ENV['NEW_KEY'], $_SERVER['NEW_KEY']);
    }

    public function testPopulateStoresNestedIniSections(): void
    {
        $dotenv = new Dotenv();
        $dotenv->populate([
            'logging' => ['LOG_REQUESTS' => 'false'],
        ], true);

        $this->assertIsArray($_ENV['logging']);
        $this->assertSame('false', $_ENV['logging']['LOG_REQUESTS']);
    }
}
