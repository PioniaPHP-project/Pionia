<?php

namespace Database\Migrations;

use Pionia\Database\Blueprint;
use Pionia\Database\Schema;
use Pionia\TestSuite\PioniaTestCase;
use PDOException;

final class SpecializedFieldsAndManyToManyTest extends PioniaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        connectionManager()->register('default', $this->useInMemoryDatabase());
        Schema::connection('default');
    }

    public function testEmailPhoneUrlSlugFieldsRejectInvalidValues(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->email();
            $table->phone()->nullable();
            $table->url('website')->nullable();
            $table->slug()->unique();
            $table->ipAddress()->nullable();
            $table->macAddress()->nullable();
            $table->year('founded')->nullable();
            $table->money('balance')->default(0);
            $table->currency()->default('USD');
            $table->rememberToken();
        });

        $pdo = $this->testPdo;
        $this->assertNotNull($pdo);

        $pdo->exec("INSERT INTO contacts (email, slug, currency, balance) VALUES ('a@b.co', 'hello-world', 'USD', 1.5)");

        try {
            $pdo->exec("INSERT INTO contacts (email, slug, currency, balance) VALUES ('not-an-email', 'ok', 'USD', 0)");
            $this->fail('Expected CHECK to reject invalid email');
        } catch (PDOException) {
            $this->assertTrue(true);
        }

        try {
            $pdo->exec("INSERT INTO contacts (email, slug, currency, balance) VALUES ('ok@x.com', 'has space', 'USD', 0)");
            $this->fail('Expected CHECK to reject slug with spaces');
        } catch (PDOException) {
            $this->assertTrue(true);
        }
    }

    public function testManyToManyCreatesPivotWithCompositeKeyAndCascade(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $pivot = Schema::manyToMany('posts', 'tags', function (Blueprint $table) {
            $table->string('role')->nullable();
        }, timestamps: true);

        $this->assertSame('post_tag', $pivot);
        $this->assertTrue(Schema::hasTable('post_tag'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'post_id'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'tag_id'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'role'));
        $this->assertTrue(Schema::hasColumn('post_tag', 'created_at'));

        $pdo = $this->testPdo;
        $this->assertNotNull($pdo);
        $pdo->exec("INSERT INTO posts (title) VALUES ('Hello')");
        $pdo->exec("INSERT INTO tags (name) VALUES ('news')");
        $pdo->exec("INSERT INTO post_tag (post_id, tag_id, role) VALUES (1, 1, 'primary')");

        $pdo->exec('DELETE FROM posts WHERE id = 1');
        $count = (int) $pdo->query('SELECT COUNT(*) FROM post_tag')->fetchColumn();
        $this->assertSame(0, $count);

        Schema::dropManyToMany('posts', 'tags');
        $this->assertFalse(Schema::hasTable('post_tag'));
    }

    public function testMorphsColumns(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->text('body');
        });

        $this->assertTrue(Schema::hasColumn('comments', 'commentable_type'));
        $this->assertTrue(Schema::hasColumn('comments', 'commentable_id'));
    }

    public function testColumnCheckModifierIsPortableInSql(): void
    {
        $grammar = Schema::grammarForDriver('sqlite');
        $blueprint = new Blueprint('things');
        $blueprint->creating(true);
        $blueprint->id();
        $blueprint->string('code')->check("{column} LIKE 'X%'");

        $sql = $grammar->compile($blueprint);
        $this->assertStringContainsString('CHECK (', $sql[0]);
        $this->assertStringContainsString('"code"', $sql[0]);
    }

    public function testFluentNonNullableUniqueChain(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nonNullable()->unique();
            $table->string('nickname')->nullable();
            $table->email()->required()->unique();
        });

        $pdo = $this->testPdo;
        $this->assertNotNull($pdo);
        $info = $pdo->query('PRAGMA table_info(people)')->fetchAll(\PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($info as $row) {
            $byName[$row['name']] = $row;
        }

        $this->assertSame(1, (int) $byName['first_name']['notnull']);
        $this->assertSame(0, (int) $byName['nickname']['notnull']);
        $this->assertSame(1, (int) $byName['email']['notnull']);

        $pdo->exec("INSERT INTO people (first_name, email) VALUES ('Ada', 'ada@x.com')");
        try {
            $pdo->exec("INSERT INTO people (first_name, email) VALUES ('Ada', 'other@x.com')");
            $this->fail('Expected unique on first_name');
        } catch (\PDOException) {
            $this->assertTrue(true);
        }
    }
}
