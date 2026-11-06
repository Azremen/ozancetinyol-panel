<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable(); // Parent menu link
            $table->string('type'); // Header, Footer etc.
            $table->json('title'); // Multilingual title
            $table->string('custom_url')->nullable();
            // Create the page_id ULID column and index it.
            // We avoid adding the foreign key constraint here so migrations can run in any order.
            $table->ulid('page_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
