<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->string('email')->nullable();
            $table->string('preferred_time', 5)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->index(['preferred_date', 'status']);
        });

        // Both applications lock this row while checking and saving bookings.
        Schema::create('appointment_schedule_locks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('appointment_schedule_locks')->insert(['id' => 1]);

        Schema::create('appointment_notifications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('appointment_request_id')->index();
            $table->string('recipient')->nullable();
            $table->string('audience', 10);
            $table->string('locale', 2);
            $table->string('event', 20);
            $table->json('details');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notifications');
        Schema::dropIfExists('appointment_schedule_locks');
        Schema::table('appointment_requests', function (Blueprint $table): void {
            $table->dropIndex(['preferred_date', 'status']);
            $table->dropColumn(['email', 'preferred_time', 'duration_minutes']);
        });
    }
};
