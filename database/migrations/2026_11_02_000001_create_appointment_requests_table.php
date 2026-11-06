<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('phone', 30);
            $table->date('preferred_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('privacy_consent_at')->nullable();
            $table->char('locale', 2)->default('tr');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
