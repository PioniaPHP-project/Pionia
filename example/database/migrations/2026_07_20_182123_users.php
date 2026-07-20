<?php

use Pionia\Database\Blueprint;
use Pionia\Database\Migrations\Migration;
use Pionia\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
    // Schema::create(…);
        Schema::create("users", function (Blueprint $table) {
            $table->id("id");
            $table->string("first_name");
            $table->string("last_name");
            $table->email()->unique();
            $table->string("password");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("users");
    }
};
