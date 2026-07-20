<?php

namespace Database\Migrations;

use Pionia\Database\Blueprint;
use Pionia\Database\Grammar\MysqlGrammar;
use Pionia\Database\Grammar\PgsqlGrammar;
use Pionia\TestSuite\PioniaTestCase;

final class GrammarSqlTest extends PioniaTestCase
{
    public function testMysqlCreateTableSql(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->creating(true);
        $blueprint->id();
        $blueprint->string('email')->unique();
        $blueprint->timestamps();

        $sql = (new MysqlGrammar())->compile($blueprint);

        $this->assertStringContainsString('CREATE TABLE `users`', $sql[0]);
        $this->assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql[0]);
        $this->assertStringContainsString('`email` VARCHAR(255)', $sql[0]);
        $this->assertStringContainsString('UNIQUE', $sql[0]);
        $this->assertStringContainsString('`created_at`', $sql[0]);
    }

    public function testMysqlForeignKeyAndAlter(): void
    {
        $create = new Blueprint('posts');
        $create->creating(true);
        $create->id();
        $create->foreignId('user_id')->constrained('users')->cascadeOnDelete();

        $sql = (new MysqlGrammar())->compile($create);
        $this->assertStringContainsString('FOREIGN KEY', $sql[0]);
        $this->assertStringContainsString('ON DELETE CASCADE', $sql[0]);
        $this->assertStringContainsString('REFERENCES `users`', $sql[0]);

        $alter = new Blueprint('posts');
        $alter->string('slug');
        $alterSql = (new MysqlGrammar())->compile($alter);
        $this->assertStringContainsString('ALTER TABLE `posts` ADD COLUMN', $alterSql[0]);
        $this->assertStringContainsString('`slug` VARCHAR(255)', $alterSql[0]);
    }

    public function testPgsqlCreateTableSql(): void
    {
        $blueprint = new Blueprint('users');
        $blueprint->creating(true);
        $blueprint->id();
        $blueprint->string('email')->unique();
        $blueprint->json('meta')->nullable();

        $sql = (new PgsqlGrammar())->compile($blueprint);

        $this->assertStringContainsString('CREATE TABLE "users"', $sql[0]);
        $this->assertStringContainsString('"id" BIGSERIAL PRIMARY KEY', $sql[0]);
        $this->assertStringContainsString('"email" VARCHAR(255)', $sql[0]);
        $this->assertStringContainsString('"meta" JSON', $sql[0]);
        $this->assertStringContainsString('NULL', $sql[0]);
    }

    public function testPgsqlIndexAndDrop(): void
    {
        $blueprint = new Blueprint('orders');
        $blueprint->creating(true);
        $blueprint->id();
        $blueprint->string('code');
        $blueprint->index(['code'], 'orders_code_idx');

        $sql = (new PgsqlGrammar())->compile($blueprint);
        $this->assertGreaterThan(1, count($sql));
        $this->assertStringContainsString('CREATE INDEX "orders_code_idx"', $sql[1]);

        $grammar = new PgsqlGrammar();
        $this->assertStringContainsString('DROP TABLE IF EXISTS "orders" CASCADE', $grammar->compileDropIfExists('orders'));
    }

    public function testMysqlAfterModifierOnAlter(): void
    {
        $alter = new Blueprint('users');
        $alter->string('nickname')->after('email');
        $sql = (new MysqlGrammar())->compile($alter);

        $this->assertStringContainsString('AFTER `email`', $sql[0]);
    }

    public function testPgsqlJsonbType(): void
    {
        $blueprint = new Blueprint('docs');
        $blueprint->creating(true);
        $blueprint->id();
        $blueprint->json('payload');
        $blueprint->jsonb('attrs');

        $sql = (new PgsqlGrammar())->compile($blueprint);
        $this->assertStringContainsString('"payload" JSON', $sql[0]);
        $this->assertStringContainsString('"attrs" JSONB', $sql[0]);
    }
}
