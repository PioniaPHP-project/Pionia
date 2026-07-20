<?php

namespace Database\Migrations;

use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Migrations\MigrationException;
use Pionia\Database\Migrations\Migrator;
use Pionia\Database\Schema;
use Pionia\TestSuite\PioniaTestCase;

final class MigratorTest extends PioniaTestCase
{
    private string $migrationsDir;

    protected function setUp(): void
    {
        parent::setUp();
        connectionManager()->register('default', $this->useInMemoryDatabase());
        Schema::connection('default');

        $this->migrationsDir = sys_get_temp_dir() . '/pionia_mig_' . uniqid('', true);
        mkdir($this->migrationsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->migrationsDir);
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testMigrateStatusRollbackAndIdempotent(): void
    {
        $this->writeMigration('2024_01_01_000001_create_items', <<<'PHP'
<?php
use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::raw("INSERT INTO items (name) VALUES ('one')");
    }
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
PHP);

        $migrator = (new Migrator())->path($this->migrationsDir, 'app');

        $ran = $migrator->migrate();
        $this->assertSame(['2024_01_01_000001_create_items'], $ran);
        $this->assertTrue(Schema::hasTable('items'));

        $again = $migrator->migrate();
        $this->assertSame([], $again);

        $status = $migrator->status();
        $this->assertSame('Ran', $status[0]['status']);
        $this->assertSame('app', $status[0]['source']);

        $pdo = $this->testPdo;
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn());

        $rolled = $migrator->rollback(1);
        $this->assertSame(['2024_01_01_000001_create_items'], $rolled);
        $this->assertFalse(Schema::hasTable('items'));
    }

    public function testDataRoundTripInUpDown(): void
    {
        $this->writeMigration('2024_01_01_000002_seed', <<<'PHP'
<?php
use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seeds', function (Blueprint $table) {
            $table->id();
            $table->string('label');
        });
        Schema::raw("INSERT INTO seeds (label) VALUES ('alpha')");
    }
    public function down(): void
    {
        Schema::dropIfExists('seeds');
    }
};
PHP);

        $migrator = (new Migrator())->path($this->migrationsDir, 'app');
        $migrator->migrate();
        $this->assertSame('alpha', $this->testPdo->query('SELECT label FROM seeds')->fetchColumn());
        $migrator->rollback();
        $this->assertFalse(Schema::hasTable('seeds'));
    }

    public function testDuplicateBasenameThrows(): void
    {
        $dirA = $this->migrationsDir . '/a';
        $dirB = $this->migrationsDir . '/b';
        mkdir($dirA);
        mkdir($dirB);
        file_put_contents($dirA . '/dup.php', $this->blankMigration());
        file_put_contents($dirB . '/dup.php', $this->blankMigration());

        $this->expectException(MigrationException::class);
        (new Migrator())->path($dirA, 'app')->path($dirB, 'pkg')->allMigrationFiles();
    }

    public function testProviderMigrationsCollection(): void
    {
        $providerDir = $this->migrationsDir . '/provider';
        mkdir($providerDir);
        file_put_contents($providerDir . '/2024_01_01_000010_provider_table.php', <<<'PHP'
<?php
use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_table', function (Blueprint $table) {
            $table->id();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('provider_table');
    }
};
PHP);

        $providerLabel = 'Vendor\\Package\\BillingProvider';
        $migrator = (new Migrator())
            ->path($this->migrationsDir, 'app')
            ->path($providerDir, $providerLabel);

        $files = $migrator->allMigrationFiles();
        $this->assertCount(1, $files);
        $this->assertSame($providerLabel, $files[0]['source']);

        $status = $migrator->status();
        $this->assertSame($providerLabel, $status[0]['source']);
        $this->assertSame('Pending', $status[0]['status']);

        $migrator->migrate();
        $this->assertTrue(Schema::hasTable('provider_table'));
    }

    public function testFreshReRunsMigrations(): void
    {
        $this->writeMigration('2024_01_01_000003_fresh', <<<'PHP'
<?php
use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fresh_t', function (Blueprint $table) {
            $table->id();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('fresh_t');
    }
};
PHP);

        $migrator = (new Migrator())->path($this->migrationsDir, 'app');
        $migrator->migrate();
        $this->assertTrue(Schema::hasTable('fresh_t'));

        $ran = $migrator->fresh(true);
        $this->assertSame(['2024_01_01_000003_fresh'], $ran);
        $this->assertTrue(Schema::hasTable('fresh_t'));
    }

    private function writeMigration(string $basename, string $contents): void
    {
        file_put_contents($this->migrationsDir . '/' . $basename . '.php', $contents);
    }

    private function blankMigration(): string
    {
        return <<<'PHP'
<?php
use Pionia\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {}
    public function down(): void {}
};
PHP;
    }
}
