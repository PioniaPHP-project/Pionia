<?php

namespace Database\Migrations;

use Pionia\Database\MigrationStubBuilder;
use Pionia\Database\Migrations\Migrator;
use Pionia\Database\Schema;
use Pionia\TestSuite\PioniaTestCase;

final class MigrationConsoleTest extends PioniaTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        connectionManager()->register('default', $this->useInMemoryDatabase());
        Schema::connection('default');

        $this->tmpDir = sys_get_temp_dir() . '/pionia_mig_console_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    public function testMakeTableGeneratesFile(): void
    {
        $path = MigrationStubBuilder::writeCreateTable(
            'widgets',
            MigrationStubBuilder::parseColumns('name:string,sku:string:unique'),
            true,
            false,
            'create_widgets_table',
        );

        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString("Schema::create('widgets'", $contents);
        $this->assertStringContainsString("\$table->string('name')", $contents);
        $this->assertStringContainsString("\$table->string('sku')->unique()", $contents);
        $this->assertStringContainsString('$table->timestamps()', $contents);

        @unlink($path);
    }

    public function testColumnDslParser(): void
    {
        $cols = MigrationStubBuilder::parseColumns('email:string:unique,org_id:foreignId:orgs,bio:text:nullable');

        $this->assertCount(3, $cols);
        $this->assertSame('email', $cols[0]['name']);
        $this->assertContains('unique', $cols[0]['modifiers']);
        $this->assertSame('orgs', $cols[1]['constrained']);
        $this->assertSame('text', $cols[2]['type']);
    }

    public function testMigrateCommandRunsPending(): void
    {
        $basename = '2024_06_01_000000_create_console_t';
        file_put_contents($this->tmpDir . '/' . $basename . '.php', <<<'PHP'
<?php
use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('console_t', function (Blueprint $table) {
            $table->id();
            $table->string('label');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('console_t');
    }
};
PHP);

        $code = $this->artisan('migrate', [
            '--path' => $this->tmpDir,
            '--database' => 'default',
        ]);

        $this->assertSame(0, $code);
        $this->assertTrue(Schema::hasTable('console_t'));
        $this->assertStringContainsString('Migrated', $this->consoleOutput());
    }

    public function testMakeTableCommandViaArtisan(): void
    {
        // Write via stub builder path used by command; exercise command registration
        $code = $this->artisan('make:table', [
            0 => 'artisan_widgets',
            '--columns' => 'title:string',
            '--no-timestamps' => true,
            '--name' => 'create_artisan_widgets_table',
        ]);

        $this->assertSame(0, $code, $this->consoleOutput());
        $this->assertStringContainsString('Created migration:', $this->consoleOutput());

        $dir = MigrationStubBuilder::migrationsDirectory();
        $matches = glob($dir . '/*create_artisan_widgets_table.php') ?: [];
        $this->assertNotEmpty($matches);
        foreach ($matches as $file) {
            unlink($file);
        }
    }
}
