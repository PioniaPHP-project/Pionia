<?php

namespace Database\Migrations;

use Pionia\Database\Blueprint;
use Pionia\Database\Schema;
use Pionia\TestSuite\PioniaTestCase;

final class SchemaBlueprintTest extends PioniaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        connectionManager()->register('default', $this->useInMemoryDatabase());
        Schema::connection('default');
    }

    public function testCreateWithIdStringTimestampsAndHasHelpers(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumn('users', 'id'));
        $this->assertTrue(Schema::hasColumn('users', 'email'));
        $this->assertTrue(Schema::hasColumn('users', 'created_at'));
        $this->assertTrue(Schema::hasColumn('users', 'updated_at'));
    }

    public function testAlterAddAndDropColumn(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->text('body')->nullable();
        });
        $this->assertTrue(Schema::hasColumn('notes', 'body'));

        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('body');
        });
        $this->assertFalse(Schema::hasColumn('notes', 'body'));
    }

    public function testUniqueAndCompositeIndex(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('tenant');
            $table->string('slug');
            $table->unique(['tenant', 'slug']);
        });

        $this->assertTrue(Schema::hasTable('accounts'));

        $pdo = $this->testPdo;
        $this->assertNotNull($pdo);
        $indexes = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'accounts'")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertNotEmpty($indexes);
    }

    public function testForeignKeysWithPragma(): void
    {
        Schema::create('orgs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('orgs')->cascadeOnDelete();
            $table->string('name');
        });

        $pdo = $this->testPdo;
        $this->assertNotNull($pdo);
        $pdo->exec("INSERT INTO orgs (name) VALUES ('Acme')");
        $pdo->exec("INSERT INTO members (org_id, name) VALUES (1, 'Ada')");
        $pdo->exec('DELETE FROM orgs WHERE id = 1');

        $count = (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testSoftDeletes(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->softDeletes();
        });

        $this->assertTrue(Schema::hasColumn('articles', 'deleted_at'));
    }

    public function testDropIfExistsAndRename(): void
    {
        Schema::create('tmp_a', function (Blueprint $table) {
            $table->id();
        });
        Schema::rename('tmp_a', 'tmp_b');
        $this->assertFalse(Schema::hasTable('tmp_a'));
        $this->assertTrue(Schema::hasTable('tmp_b'));
        Schema::dropIfExists('tmp_b');
        $this->assertFalse(Schema::hasTable('tmp_b'));
    }

    public function testRenameColumnAndRaw(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->jsonb('meta')->nullable();
        });

        Schema::table('widgets', function (Blueprint $table) {
            $table->renameColumn('title', 'name');
        });
        $this->assertTrue(Schema::hasColumn('widgets', 'name'));
        $this->assertFalse(Schema::hasColumn('widgets', 'title'));
        $this->assertTrue(Schema::hasColumn('widgets', 'meta'));

        Schema::raw('CREATE INDEX widgets_name_idx ON widgets (name)');
        $pdo = $this->testPdo;
        $this->assertNotNull($pdo);
        $indexes = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'widgets'")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('widgets_name_idx', $indexes);
    }
}
