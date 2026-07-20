<?php

use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

/**
 * Sample tables for the example app (company + sample_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('sample_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file')->nullable();
            $table->foreignId('company')->nullable()->constrained('company')->nullOnDelete();
        });

        Schema::raw("INSERT INTO company (name) VALUES ('Acme Corp'), ('Globex')");
        Schema::raw("INSERT INTO sample_table (name, company) VALUES ('Widget A', 1), ('Widget B', 2)");
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_table');
        Schema::dropIfExists('company');
    }
};
