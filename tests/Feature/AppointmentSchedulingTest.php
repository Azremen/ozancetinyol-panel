<?php

namespace Tests\Feature;

use App\Filament\Pages\AppointmentSettings;
use App\Filament\Resources\AppointmentRequests\Pages\AppointmentCalendar;
use App\Filament\Resources\AppointmentRequests\Pages\CreateAppointmentRequest;
use App\Filament\Resources\AppointmentRequests\Pages\EditAppointmentRequest;
use App\Mail\AppointmentNotification;
use App\Models\AppointmentRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\AppointmentSchedule;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AppointmentSchedulingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['activitylog.enabled' => false, 'mail.default' => 'smtp', 'app.timezone' => 'Europe/Istanbul']);
        $this->travelTo(Carbon::parse('2026-11-09 08:00', config('app.timezone')));
        (require database_path('migrations\2026_11_02_000001_create_appointment_requests_table.php'))->up();
        Schema::table('appointment_requests', fn (Blueprint $table) => $table->softDeletes());
        (require database_path('migrations\2026_11_06_000001_add_appointment_scheduling.php'))->up();
        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
        $schedule = app(AppointmentSchedule::class)->settings();
        $schedule['days'] = array_map(fn ($day) => [
            'day' => $day, 'enabled' => true, 'start' => '09:00', 'end' => '18:00',
        ], range(1, 7));
        Setting::create(['key' => 'appointment_schedule', 'value' => $schedule]);
        Setting::create(['key' => 'email', 'value' => ['tr' => 'clinic@example.test']]);
    }

    private function request(array $overrides = []): AppointmentRequest
    {
        return AppointmentRequest::create([
            'name' => 'Test Patient', 'phone' => '+905551234567', 'email' => 'patient@example.test',
            'preferred_date' => '2026-11-10', 'preferred_time' => '09:00',
            'status' => 'pending', 'locale' => 'en', ...$overrides,
        ]);
    }

    public function test_confirmation_records_messages_once_and_rescheduling_notifies_again(): void
    {
        $request = $this->request();
        $request->update(['status' => 'confirmed']);
        $this->assertSame(60, $request->duration_minutes);
        $this->assertSame(4, DB::table('appointment_notifications')->count());
        $request->update(['note' => 'Contacted']);
        $this->assertSame(4, DB::table('appointment_notifications')->count());
        $request->update(['preferred_time' => '10:00']);
        $this->assertSame(6, DB::table('appointment_notifications')->count());
        $details = json_decode(DB::table('appointment_notifications')->latest('id')->value('details'), true);
        $this->assertSame('10:00', $details['time']);
    }

    public function test_two_pending_requests_cannot_both_be_confirmed_for_the_same_time(): void
    {
        $first = $this->request();
        $second = $this->request();
        $first->update(['status' => 'confirmed']);
        try {
            $second->update(['status' => 'confirmed']);
            $this->fail('An occupied slot was confirmed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preferred_time', $exception->errors());
        }
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(6, DB::table('appointment_notifications')->count());
        $first->update(['status' => 'cancelled']);
        $second->fresh()->update(['status' => 'confirmed']);
        $this->assertSame('confirmed', $second->fresh()->status);
    }

    public function test_existing_durations_block_overlaps_after_duration_changes(): void
    {
        $this->request()->update(['status' => 'confirmed']);
        $setting = Setting::where('key', 'appointment_schedule')->firstOrFail();
        $schedule = $setting->value;
        $schedule['duration'] = 30;
        $setting->update(['value' => $schedule]);
        $slots = app(AppointmentSchedule::class)->slots('2026-11-10');
        $this->assertSame('busy', $slots[0]['status']);
        $this->assertSame('busy', $slots[1]['status']);
        $this->assertSame('available', $slots[2]['status']);
    }

    public function test_confirmation_requires_email_date_and_time(): void
    {
        $request = $this->request(['preferred_time' => null]);
        $this->expectException(ValidationException::class);
        $request->update(['status' => 'confirmed']);
    }

    public function test_restoring_a_confirmed_booking_cannot_reoccupy_a_booked_slot(): void
    {
        $first = $this->request();
        $first->update(['status' => 'confirmed']);
        $first->delete();
        $this->request()->update(['status' => 'confirmed']);
        try {
            $first->restore();
            $this->fail('A conflicting appointment was restored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preferred_time', $exception->errors());
        }
        $this->assertSoftDeleted('appointment_requests', ['id' => $first->id]);
    }

    public function test_a_legacy_confirmation_without_time_blocks_its_date_until_repaired(): void
    {
        $request = $this->request(['preferred_time' => null]);
        DB::table('appointment_requests')->where('id', $request->id)->update(['status' => 'confirmed']);
        $slots = app(AppointmentSchedule::class)->slots('2026-11-10');
        $this->assertNotEmpty($slots);
        $this->assertSame(['busy'], array_values(array_unique(array_column($slots, 'status'))));
    }

    public function test_stale_pending_instance_cannot_move_a_confirmed_booking_without_validation(): void
    {
        $request = $this->request();
        $stale = $request->fresh();
        $request->update(['status' => 'confirmed']);
        try {
            $stale->update(['preferred_time' => '18:00']);
            $this->fail('A stale appointment bypassed confirmation validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('09:00', $request->fresh()->preferred_time);
        $this->assertSame(4, DB::table('appointment_notifications')->count());
    }

    public function test_confirmation_rejects_invalid_email_even_outside_the_panel_form(): void
    {
        $request = $this->request(['email' => 'invalid']);
        try {
            $request->update(['status' => 'confirmed']);
            $this->fail('An invalid customer email was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(2, DB::table('appointment_notifications')->count());
    }

    public function test_restoration_preserves_original_duration_and_checks_the_full_interval(): void
    {
        $request = $this->request();
        $request->update(['status' => 'confirmed']);
        $request->delete();
        $setting = Setting::where('key', 'appointment_schedule')->firstOrFail();
        $schedule = $setting->value;
        $schedule['duration'] = 30;
        $setting->update(['value' => $schedule]);
        $other = $this->request(['preferred_time' => '09:30']);
        $other->update(['status' => 'confirmed']);
        try {
            $request->restore();
            $this->fail('The restored booking overlaps another booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preferred_time', $exception->errors());
        }
        $this->assertSoftDeleted('appointment_requests', ['id' => $request->id]);
        $other->update(['status' => 'cancelled']);
        $restored = AppointmentRequest::withTrashed()->findOrFail($request->id);
        $restored->restore();
        $this->assertSame(60, $restored->duration_minutes);
    }

    public function test_configured_timezone_is_used_and_snapshotted_for_confirmations(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $schedule = app(AppointmentSchedule::class);
        $this->travelTo(Carbon::parse('2026-11-10 00:30', 'UTC'));
        $this->assertSame('2026-11-09', $schedule->today());
        $this->assertSame('past', $schedule->slots('2026-11-09')[0]['status']);
        $request = $this->request();
        $request->update(['status' => 'confirmed']);
        $details = json_decode(DB::table('appointment_notifications')->latest('id')->value('details'), true);
        $this->assertSame('America/New_York', $details['timezone']);
        config(['app.timezone' => 'UTC']);
        app()->setLocale('en');
        (new AppointmentNotification($details, 'confirmed', 'customer'))->assertSeeInHtml('America/New_York');
    }

    public function test_mail_with_legacy_details_uses_configured_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        (new AppointmentNotification([
            'name' => 'Test Patient', 'phone' => '+905551234567', 'date' => '10.11.2026', 'time' => '09:00',
        ], 'confirmed', 'customer'))->assertSeeInHtml('UTC');
    }

    public function test_delivery_sends_customer_and_admin_emails_without_repeating_sent_messages(): void
    {
        Mail::fake();
        $request = $this->request();
        $request->update(['status' => 'confirmed']);
        $this->artisan('appointments:send-notifications')->assertSuccessful();
        Mail::assertSentCount(4);
        Mail::assertSent(AppointmentNotification::class, fn ($mail) => $mail->event === 'received'
            && $mail->hasTo('patient@example.test') && $mail->locale === 'en');
        Mail::assertSent(AppointmentNotification::class, fn ($mail) => $mail->event === 'confirmed'
            && $mail->hasTo('clinic@example.test') && $mail->details['time'] === '09:00');
        $this->artisan('appointments:send-notifications')->assertSuccessful();
        Mail::assertSentCount(4);
    }

    public function test_missing_contact_email_is_visible_and_retried_after_configuration(): void
    {
        Mail::fake();
        Setting::where('key', 'email')->delete();
        $this->request();
        $this->artisan('appointments:send-notifications')->assertFailed();
        $this->assertDatabaseHas('appointment_notifications', ['audience' => 'admin', 'sent_at' => null, 'attempts' => 1]);
        $this->assertNotNull(DB::table('appointment_notifications')->where('audience', 'admin')->value('last_error'));
        Setting::create(['key' => 'email', 'value' => ['tr' => 'clinic@example.test']]);
        $this->travel(1)->minutes();
        $this->artisan('appointments:send-notifications')->assertSuccessful();
        Mail::assertSentCount(2);
    }

    public function test_failures_use_backoff_without_dropping_notifications(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $this->request();
        $this->artisan('appointments:send-notifications')->assertFailed();
        $this->artisan('appointments:send-notifications')->assertSuccessful();
        $this->assertSame([1, 1], DB::table('appointment_notifications')->pluck('attempts')->all());
        $this->travel(1)->minutes();
        $this->artisan('appointments:send-notifications')->assertFailed();
        $this->assertSame([2, 2], DB::table('appointment_notifications')->pluck('attempts')->all());
        $this->travel(4)->minutes();
        config(['mail.default' => 'smtp']);
        $this->artisan('appointments:send-notifications')->assertSuccessful();
        Mail::assertNothingSent();
        $this->travel(1)->minutes();
        $this->artisan('appointments:send-notifications')->assertSuccessful();
        Mail::assertSentCount(2);
        $this->assertSame(0, DB::table('appointment_notifications')->whereNull('sent_at')->count());
    }

    public function test_failed_messages_do_not_starve_new_notifications(): void
    {
        Mail::fake();
        Setting::where('key', 'email')->delete();
        $request = $this->request();
        DB::table('appointment_notifications')->delete();
        $row = [
            'appointment_request_id' => $request->id, 'recipient' => null, 'audience' => 'admin',
            'locale' => 'tr', 'event' => 'received',
            'details' => json_encode(['name' => 'Test Patient', 'phone' => '+905551234567', 'date' => '10.11.2026', 'time' => '09:00']),
            'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute(),
        ];
        DB::table('appointment_notifications')->insert(array_fill(0, 100, $row));
        DB::table('appointment_notifications')->insert([
            ...$row, 'recipient' => 'patient@example.test', 'audience' => 'customer',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->travel(1)->seconds();
        $this->artisan('appointments:send-notifications')->assertFailed();
        $this->travel(1)->seconds();
        $this->artisan('appointments:send-notifications')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('appointment_notifications', ['audience' => 'customer', 'attempts' => 1]);
    }

    public function test_transport_failure_keeps_notifications_for_retry(): void
    {
        $this->request();
        Mail::shouldReceive('to')->twice()->andThrow(new TransportException('SMTP unavailable'));
        $this->artisan('appointments:send-notifications')->assertFailed();
        $this->assertSame(2, DB::table('appointment_notifications')->whereNull('sent_at')->where('attempts', 1)->count());
    }

    public function test_log_mailer_does_not_discard_undelivered_notifications(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $this->request();
        $this->artisan('appointments:send-notifications')->assertFailed();
        $this->assertSame(2, DB::table('appointment_notifications')->whereNull('sent_at')->count());
        Mail::assertNothingSent();
    }

    public function test_concurrent_notification_senders_are_rejected(): void
    {
        Mail::fake();
        $this->request();
        $lock = Cache::lock('appointments:send-notifications', 3600);
        $lock->get();
        try {
            $this->artisan('appointments:send-notifications')->assertFailed();
            Mail::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_mail_content_contains_confirmation_date_and_time_and_received_text(): void
    {
        app()->setLocale('en');
        $details = ['name' => 'Test Patient', 'phone' => '+905551234567', 'date' => '10.11.2026', 'time' => '09:00', 'timezone' => 'Europe/Istanbul'];
        (new AppointmentNotification($details, 'confirmed', 'customer'))
            ->assertSeeInHtml('10.11.2026')->assertSeeInHtml('09:00')->assertSeeInHtml('confirmed');
        (new AppointmentNotification($details, 'received', 'customer'))
            ->assertSeeInHtml('contact you as soon as possible')->assertSeeInHtml('not yet confirmed');
    }

    public function test_redesigned_mail_renders_all_variants_in_both_languages(): void
    {
        $details = [
            'name' => 'Test Patient', 'phone' => '+905551234567',
            'date' => '10.11.2026', 'time' => '09:00', 'timezone' => 'Europe/Istanbul',
        ];
        foreach (['tr', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach (['customer', 'admin'] as $audience) {
                foreach (['received', 'confirmed'] as $event) {
                    $mail = new AppointmentNotification($details, $event, $audience);
                    $mail->assertSeeInHtml('Ozan Çetinyol')
                        ->assertSeeInHtml(__('appointments.mail_status_'.$event))
                        ->assertSeeInHtml(__('appointments.mail_details'))
                        ->assertSeeInText('10.11.2026')
                        ->assertSeeInText('09:00');
                    $this->assertStringNotContainsString('href="'.config('app.url').'"', $mail->render());
                }
            }
        }
    }

    public function test_schedule_settings_and_calendar_are_renderable_and_validated(): void
    {
        $this->loginAdmin();
        Livewire::test(AppointmentSettings::class)->set('data.duration', 30)->call('save')->assertHasNoErrors();
        $this->assertSame(30, (int) app(AppointmentSchedule::class)->settings()['duration']);
        $this->assertNotEmpty(app(AppointmentSchedule::class)->slots('2026-11-10'));
        Livewire::test(AppointmentSettings::class)->set('data.days.0.end', '08:00')->call('save')->assertHasErrors();
        $this->assertNotEmpty(app(AppointmentSchedule::class)->slots('2026-11-10'));
        Livewire::test(AppointmentCalendar::class)->set('date', '2026-11-10')->assertSet('date', '2026-11-10')->assertHasNoErrors()->assertSee('09:00');
    }

    public function test_panel_maps_domain_validation_errors_to_form_fields(): void
    {
        (require database_path('migrations\2026_03_02_165406_create_permission_tables.php'))->up();
        $this->loginAdmin();
        $this->request()->update(['status' => 'confirmed']);
        $pending = $this->request();
        Livewire::test(EditAppointmentRequest::class, ['record' => $pending->id])
            ->set('data.status', 'confirmed')->call('save')->assertHasErrors(['data.preferred_time']);
        Livewire::test(CreateAppointmentRequest::class)
            ->set('data.name', 'Test Patient')->set('data.phone', '+905551234567')
            ->set('data.email', 'patient@example.test')
            ->set('data.preferred_date', '2026-11-10')->set('data.preferred_time', '09:00')
            ->set('data.status', 'confirmed')->call('create')->assertHasErrors(['data.preferred_time']);
    }

    public function test_calendar_navigation_and_daily_summary_in_both_languages(): void
    {
        $this->loginAdmin();
        $this->request()->update(['status' => 'confirmed']);
        $this->request(['name' => 'Pending Patient']);

        foreach (['tr', 'en'] as $locale) {
            app()->setLocale($locale);
            Livewire::test(AppointmentCalendar::class)
                ->set('date', '2026-11-10')
                ->assertSee(__('appointments.day_slots'))
                ->assertSee(__('appointments.calendar_hint'))
                ->assertSee(__('appointments.busy'))
                ->assertSee(__('appointments.available'))
                ->assertSee('Pending Patient')
                ->assertViewHas('slotCounts', fn ($counts) => $counts['busy'] === 1 && $counts['available'] === 8)
                ->assertViewHas('appointments', fn ($appointments) => $appointments->count() === 2)
                ->call('nextDay')->assertSet('date', '2026-11-11')
                ->assertSee(__('appointments.no_requests'))
                ->call('previousDay')->assertSet('date', '2026-11-10')
                ->call('goToToday')->assertSet('date', '2026-11-09')
                ->assertHasNoErrors();
        }
    }

    public function test_calendar_closed_day_and_month_boundary_navigation(): void
    {
        $this->loginAdmin();
        $setting = Setting::where('key', 'appointment_schedule')->firstOrFail();
        $schedule = $setting->value;
        $schedule['closed_dates'] = [['date' => '2026-12-01']];
        $setting->update(['value' => $schedule]);

        Livewire::test(AppointmentCalendar::class)
            ->set('date', '2026-11-30')->call('nextDay')
            ->assertSet('date', '2026-12-01')
            ->assertSee(__('appointments.no_slots'))
            ->assertSee(__('appointments.settings'))
            ->assertViewHas('appointmentSlots', [])
            ->call('previousDay')->assertSet('date', '2026-11-30')
            ->assertHasNoErrors();
    }

    public function test_calendar_and_working_hours_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('filament.admin.resources.appointment-requests.calendar'));
        $this->assertTrue(Route::has(AppointmentSettings::getRouteName()));
        $this->assertStringContainsString('/appointment-settings', AppointmentSettings::getUrl());
        $this->assertStringContainsString('/appointment-requests/calendar', AppointmentCalendar::getUrl());
    }

    private function loginAdmin(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => '01J00000000000000000000000', 'name' => 'Admin', 'email' => 'admin@example.test']);
        $user->shouldReceive('hasRole')->andReturn(true);
        $user->shouldReceive('canAccessPanel')->andReturn(true);
        Auth::guard()->setUser($user);
    }

    public function test_unauthorized_users_cannot_access_schedule_or_calendar(): void
    {
        $this->assertFalse(AppointmentSettings::canAccess());
        $this->assertFalse(AppointmentCalendar::canAccess());
    }

    public function test_monthly_cleanup_retains_the_last_thirty_days(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
        DB::table('activity_log')->insert([
            ['created_at' => now()->subDays(31), 'updated_at' => now()],
            ['created_at' => now()->subDays(30), 'updated_at' => now()],
            ['created_at' => now(), 'updated_at' => now()],
        ]);
        $this->artisan('schedule:list')->assertSuccessful();
        $events = app(Schedule::class)->events();
        $event = collect($events)->first(fn ($event) => str_contains($event->command ?? '', 'activitylog:clean'));
        $this->assertSame('0 0 1 * *', $event->expression);
        $this->assertStringContainsString('--days=30 --force', $event->command);
        $this->artisan('activitylog:clean', ['--days' => 30, '--force' => true])->assertSuccessful();
        $this->assertSame(2, DB::table('activity_log')->count());
    }
}
