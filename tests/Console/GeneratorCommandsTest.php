<?php

namespace Console;

use Pionia\Builtins\Commands\Generators\CreateMiddleware;
use Pionia\Builtins\Commands\Generators\GenerateAuthenticationBackend;
use Pionia\Builtins\Commands\Generators\GenerateCommand;
use Pionia\Builtins\Commands\Generators\GenerateProvider;
use Pionia\Builtins\Commands\Generators\GenerateService;
use Pionia\Builtins\Commands\Generators\GenerateSwitch;
use Pionia\Console\Command;
use Pionia\Console\Input\ArrayInput;
use Pionia\Console\Output\BufferedOutput;
use Pionia\Console\OutputStyle;
use Pionia\TestSuite\PioniaTestCase;
use ReflectionProperty;

class GeneratorCommandsTest extends PioniaTestCase
{
  private array $createdFiles = [];

  private ?string $generatedIniBackup = null;

  protected function setUp(): void
  {
    parent::setUp();

    $iniPath = app()->envPath('generated.ini');
    $this->generatedIniBackup = is_file($iniPath) ? (string) file_get_contents($iniPath) : null;
  }

  protected function tearDown(): void
  {
    foreach ($this->createdFiles as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }

    $iniPath = app()->envPath('generated.ini');
    if ($this->generatedIniBackup !== null) {
      file_put_contents($iniPath, $this->generatedIniBackup);
    } elseif (is_file($iniPath)) {
      @unlink($iniPath);
    }

    $this->createdFiles = [];
    $this->generatedIniBackup = null;
    parent::tearDown();
  }

  public function testNamespaceForResolvesApplicationNamespaces(): void
  {
    $this->assertSame('Application\\Services', namespaceFor('SERVICE_NS'));
    $this->assertSame('Application\\Switches', namespaceFor('SWITCH_NS'));
    $this->assertSame('Application\\Commands', namespaceFor('COMMAND_NS'));
    $this->assertSame('Application\\Middlewares', namespaceFor('MIDDLEWARE_NS'));
    $this->assertSame('Application\\Authentications', namespaceFor('AUTHENTICATION_NS'));
    $this->assertSame('Application\\Providers', namespaceFor('PROVIDER_NS'));
  }

  public function testDirectoryPathResolvesAbsolutePaths(): void
  {
    $services = directoryPath('SERVICES_DIR');
    $this->assertStringEndsWith('services', rtrim($services, DIRECTORY_SEPARATOR));
    $this->assertDirectoryExists($services);
  }

  private function generator(Command $command): Command
  {
    $input = new ArrayInput([]);
    $output = new OutputStyle($input, new BufferedOutput());
    $ref = new ReflectionProperty($command, 'output');
    $ref->setValue($command, $output);

    return $command;
  }

  public function testMakeServiceWritesClassWithCorrectNamespace(): void
  {
    $path = BASE_PATH . '/services/GenTestService.php';
    $this->createdFiles[] = $path;

    $this->generator(new GenerateService())->generate('GenTest', arr(['ping']), 'Basic');

    $this->assertFileExists($path);
    $source = (string) file_get_contents($path);
    $this->assertStringContainsString('namespace Application\Services;', $source);
    $this->assertStringContainsString('class GenTestService', $source);
    $this->assertStringContainsString('@moonlight-action ping_gen_test_action', $source);
    $this->assertStringContainsString('function pingGenTestAction', $source);
  }

  public function testMakeSwitchWritesClassWithCorrectNamespace(): void
  {
    $path = BASE_PATH . '/switches/GenTestSwitch.php';
    $this->createdFiles[] = $path;

    $this->generator(new GenerateSwitch())->generate('GenTest');

    $this->assertFileExists($path);
    $source = (string) file_get_contents($path);
    $this->assertStringContainsString('namespace Application\Switches;', $source);
    $this->assertStringContainsString('class GenTestSwitch', $source);
    $this->assertStringContainsString('registerServices', $source);
  }

  public function testMakeCommandWritesClassAndRegistersInGeneratedIni(): void
  {
    $path = BASE_PATH . '/commands/GenTestCommand.php';
    $this->createdFiles[] = $path;

    $this->generator(new GenerateCommand())->generate(
      'GenTest',
      'gen:test',
      'Gen test',
      'A generated test command',
      'Help text',
      [],
      [],
      [],
    );

    $this->assertFileExists($path);
    $source = (string) file_get_contents($path);
    $this->assertStringContainsString('namespace Application\Commands;', $source);
    $this->assertStringContainsString('class GenTestCommand', $source);
    $this->assertStringContainsString("protected function handle()", $source);

    $iniPath = app()->envPath('generated.ini');
    $ini = parse_ini_file($iniPath, true);
    $this->assertArrayHasKey('app_commands', $ini);
    $this->assertSame('Application\\Commands\\GenTestCommand', $ini['app_commands']['gen:test'] ?? null);
  }

  public function testMakeMiddlewareWritesClassAndRegistersWithSnakeCaseAlias(): void
  {
    $path = BASE_PATH . '/middlewares/GenTestMiddleware.php';
    $this->createdFiles[] = $path;

    $this->generator(new CreateMiddleware())->generate('GenTest');

    $this->assertFileExists($path);
    $source = (string) file_get_contents($path);
    $this->assertStringContainsString('namespace Application\Middlewares;', $source);
    $this->assertStringContainsString('class GenTestMiddleware', $source);

    $iniPath = app()->envPath('generated.ini');
    $ini = parse_ini_file($iniPath, true);
    $this->assertArrayHasKey('app_middlewares', $ini);
    $this->assertSame('Application\\Middlewares\\GenTestMiddleware', $ini['app_middlewares']['gen_test'] ?? null);
  }

  public function testMakeAuthWritesClassAndRegistersWithSnakeCaseAlias(): void
  {
    $path = BASE_PATH . '/authentications/GenTestAuthentication.php';
    $this->createdFiles[] = $path;

    $this->generator(new GenerateAuthenticationBackend())->generate('GenTest');

    $this->assertFileExists($path);
    $source = (string) file_get_contents($path);
    $this->assertStringContainsString('namespace Application\Authentications;', $source);
    $this->assertStringContainsString('class GenTestAuthentication', $source);

    $iniPath = app()->envPath('generated.ini');
    $ini = parse_ini_file($iniPath, true);
    $this->assertArrayHasKey('app_authentications', $ini);
    $this->assertSame('Application\\Authentications\\GenTestAuthentication', $ini['app_authentications']['gen_test'] ?? null);
  }

  public function testMakeProviderWritesClassAndRegistersInGeneratedIni(): void
  {
    $path = BASE_PATH . '/providers/GenTestProvider.php';
    $this->createdFiles[] = $path;

    $this->generator(new GenerateProvider())->generate('GenTest');

    $this->assertFileExists($path);
    $source = (string) file_get_contents($path);
    $this->assertStringContainsString('namespace Application\Providers;', $source);
    $this->assertStringContainsString('class GenTestProvider', $source);

    $iniPath = app()->envPath('generated.ini');
    $ini = parse_ini_file($iniPath, true);
    $this->assertArrayHasKey('app_providers', $ini);
    $this->assertSame('Application\\Providers\\GenTestProvider', $ini['app_providers']['gen_test_provider'] ?? null);
  }
}
