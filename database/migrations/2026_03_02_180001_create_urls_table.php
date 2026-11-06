<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('urls', function (Blueprint $table) {
            // 1. Table's Own Primary Key
            $table->ulid('id')->primary();

            // 2. URL Extension/Slug
            $table->string('slug')->unique();

            // 3. Related Table Relationships (Foreign Keys)
            // WARNING: These names should be 'page_id' and 'menu_id', not 'id'.
            $table->foreignUlid('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignUlid('menu_id')->nullable()->constrained('menus')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};