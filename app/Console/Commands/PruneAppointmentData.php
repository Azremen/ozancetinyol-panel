<?php

namespace App\Console\Commands;

use App\Services\AppointmentSchedule;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PruneAppointmentData extends Command
{
    use ConfirmableTrait;

    protected $signature = 'appointments:prune {--force : Run without production confirmation}';

    protected $description = 'Permanently remove appointment and notification data older than six months';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        // Do not delete an outbox entry while another process is sending it.
        $lock = Cache::lock('appointments:send-notifications', 3600);
        if (! $lock->get()) {
            $this->error('Appointment notifications are being processed. Retry cleanup later.');

            return self::FAILURE;
        }

        try {
            $cutoff = now(app(AppointmentSchedule::class)->timezone())->subMonthsNoOverflow(6);
            $appointments = 0;
            $notifications = 0;
            do {
                $count = DB::transaction(function () use ($cutoff, &$notifications): int {
                    DB::table('appointment_schedule_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
                    $ids = DB::table('appointment_requests')
                        ->where('updated_at', '<', $cutoff)
                        ->where(fn (Builder $query) => $query->whereNull('preferred_date')
                            ->orWhere('preferred_date', '<', $cutoff->toDateString()))
                        ->orderBy('id')->limit(500)->lockForUpdate()->pluck('id');
                    $notifications += DB::table('appointment_notifications')->whereIn('appointment_request_id', $ids)->delete();

                    return DB::table('appointment_requests')->whereIn('id', $ids)->delete();
                });
                $appointments += $count;
            } while ($count === 500);

            do {
                $count = DB::transaction(function () use ($cutoff): int {
                    DB::table('appointment_schedule_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
                    $ids = DB::table('appointment_notifications')
                        ->where('created_at', '<', $cutoff)
                        ->whereNotExists(function (Builder $query) use ($cutoff): void {
                            $query->selectRaw('1')->from('appointment_requests')
                                ->whereColumn('appointment_requests.id', 'appointment_notifications.appointment_request_id')
                                ->where('preferred_date', '>=', $cutoff->toDateString());
                        })
                        ->orderBy('id')->limit(500)->lockForUpdate()->pluck('id');

                    return DB::table('appointment_notifications')->whereIn('id', $ids)->delete();
                });
                $notifications += $count;
            } while ($count === 500);

            $this->info("Permanently deleted {$appointments} appointments and {$notifications} notifications.");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
