<?php

namespace Http\Worker;

use Pionia\Http\Worker\RoadRunnerListenResolver;
use Pionia\TestSuite\PioniaTestCase;

class RoadRunnerListenResolverTest extends PioniaTestCase
{
    private RoadRunnerListenResolver $resolver;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new RoadRunnerListenResolver();
        $this->configPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pionia-rr-' . uniqid('', true) . '.yaml';
        file_put_contents($this->configPath, <<<'YAML'
version: "3"
http:
  address: 127.0.0.1:8080
YAML);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }

        parent::tearDown();
    }

    public function testEnvironmentPortOverridesYaml(): void
    {
        $listen = $this->resolver->resolve($this->configPath);

        $this->assertSame('127.0.0.1', $listen['host']);
        $this->assertSame('8003', $listen['port']);
        $this->assertSame('127.0.0.1:8003', $listen['address']);
    }

    public function testCliPortOverridesEnvironment(): void
    {
        $listen = $this->resolver->resolve($this->configPath, null, 9100);

        $this->assertSame('9100', $listen['port']);
    }

    public function testCliHostCanIncludePort(): void
    {
        $listen = $this->resolver->resolve($this->configPath, '0.0.0.0:9200');

        $this->assertSame('0.0.0.0', $listen['host']);
        $this->assertSame('9200', $listen['port']);
    }

    public function testCliPortOverridesHostEmbeddedPort(): void
    {
        $listen = $this->resolver->resolve($this->configPath, '0.0.0.0:9200', 9300);

        $this->assertSame('0.0.0.0', $listen['host']);
        $this->assertSame('9300', $listen['port']);
    }

    public function testYamlPortUsedWhenEnvironmentEmpty(): void
    {
        $listen = (new RoadRunnerListenResolver([]))->resolve($this->configPath);

        $this->assertSame('127.0.0.1', $listen['host']);
        $this->assertSame('8080', $listen['port']);
    }

    public function testFrameworkDefaultPortWhenYamlAddressMissing(): void
    {
        $configPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pionia-rr-empty-' . uniqid('', true) . '.yaml';
        file_put_contents($configPath, <<<'YAML'
version: "3"
server:
  command: "php worker.php"
YAML);

        try {
            $listen = (new RoadRunnerListenResolver([]))->resolve($configPath);
            $this->assertSame('8003', $listen['port']);
        } finally {
            if (is_file($configPath)) {
                unlink($configPath);
            }
        }
    }

    public function testIniRoadrunnerPortOverridesYaml(): void
    {
        $listen = (new RoadRunnerListenResolver([
            'roadrunner' => ['PORT' => '7500'],
        ]))->resolve($this->configPath);

        $this->assertSame('7500', $listen['port']);
    }

    public function testYamlAddressIsPreservedForOverrideComparison(): void
    {
        $listen = $this->resolver->resolve($this->configPath);

        $this->assertSame('127.0.0.1:8080', $listen['yaml_address']);
    }
}
