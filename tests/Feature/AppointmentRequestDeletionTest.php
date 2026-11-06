<?php

namespace Tests\Feature;

use App\Models\AppointmentRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentRequestDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('appointment_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('phone', 30);
            $table->date('preferred_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('privacy_consent_at')->nullable();
            $table->char('locale', 2)->default('tr');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations\2026_11_06_000001_add_appointment_scheduling.php'))->up();
    }

    public function test_appointment_requests_can_be_soft_deleted_restored_and_permanently_deleted(): void
    {
        $appointment = AppointmentRequest::create([
            'name' => 'Test User',
            'phone' => '+905551234567',
            'status' => 'pending',
        ]);

        $appointment->delete();

        $this->assertSoftDeleted('appointment_requests', ['id' => $appointment->id]);
        $this->assertNull(AppointmentRequest::find($appointment->id));

        $appointment->restore();

        $this->assertNotSoftDeleted('appointment_requests', ['id' => $appointment->id]);
        $this->assertNotNull(AppointmentRequest::find($appointment->id));

        $appointment->forceDelete();

        $this->assertDatabaseMissing('appointment_requests', ['id' => $appointment->id]);
    }
}
