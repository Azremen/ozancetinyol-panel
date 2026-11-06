<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-07-01 03:00'));
        (require database_path('migrations\2026_11_02_000001_create_appointment_requests_table.php'))->up();
        Schema::table('appointment_requests', fn (Blueprint $table) => $table->softDeletes());
        (require database_path('migrations\2026_11_06_000001_add_appointment_scheduling.php'))->up();
    }

    public function test_cleanup_removes_old_data_but_preserves_boundary_recent_and_future_appointments(): void
    {
        $old = $this->insertAppointment('2026-12-01', now()->subMonths(7));
        $deleted = $this->insertAppointment(null, now()->subMonths(7), now()->subMonths(7));
        $future = $this->insertAppointment('2027-08-01', now()->subMonths(7));
        $recent = $this->insertAppointment('2026-12-01', now()->subDays(1));
        $boundary = $this->insertAppointment('2027-01-01', now()->subMonths(6));
        $recentDate = $this->insertAppointment('2027-06-01', now()->subMonths(7));
        foreach ([$old, $deleted, $future, $recent, $boundary, $recentDate] as $id) {
            DB::table('appointment_notifications')->insert([
                'appointment_request_id' => $id, 'audience' => 'admin', 'locale' => 'tr',
                'event' => 'received', 'details' => '{}', 'created_at' => now()->subMonths(7), 'updated_at' => now(),
            ]);
        }
        $this->artisan('appointments:prune', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseMissing('appointment_requests', ['id' => $old]);
        $this->assertDatabaseMissing('appointment_requests', ['id' => $deleted]);
        foreach ([$future, $recent, $boundary, $recentDate] as $id) {
            $this->assertDatabaseHas('appointment_requests', ['id' => $id]);
        }
        $this->assertDatabaseMissing('appointment_notifications', ['appointment_request_id' => $recent]);
        $this->assertDatabaseHas('appointment_notifications', ['appointment_request_id' => $future]);
        $this->assertDatabaseHas('appointment_notifications', ['appointment_request_id' => $boundary]);
        $this->artisan('appointments:prune', ['--force' => true])->assertSuccessful();
    }

    public function test_cleanup_does_not_run_while_notification_sender_is_active(): void
    {
        $lock = Cache::lock('appointments:send-notifications', 3600);
        $lock->get();
        try {
            $this->artisan('appointments:prune', ['--force' => true])->assertFailed();
        } finally {
            $lock->release();
        }
    }

    public function test_cleanup_schedule_runs_twice_a_year(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'appointments:prune'));
        $this->assertSame('0 3 1 1,7 *', $event->expression);
    }

    private function insertAppointment(?string $date, Carbon $updated, ?Carbon $deleted = null): string
    {
        $id = (string) Str::ulid();
        DB::table('appointment_requests')->insert([
            'id' => $id, 'name' => 'Test Patient', 'phone' => '+905550000000',
            'preferred_date' => $date, 'created_at' => now()->subMonths(7),
            'updated_at' => $updated, 'deleted_at' => $deleted,
        ]);

        return $id;
    }
}
