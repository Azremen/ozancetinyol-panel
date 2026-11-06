<?php

namespace App\Console\Commands;

use App\Mail\AppointmentNotification;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendAppointmentNotifications extends Command
{
    protected $signature = 'appointments:send-notifications';

    protected $description = 'Send pending appointment emails and retry failed deliveries';

    public function handle(): int
    {
        $lock = Cache::lock('appointments:send-notifications', 3600);
        if (! $lock->get()) {
            $this->error('Another appointment notification sender is running.');

            return self::FAILURE;
        }

        try {
            return $this->deliver();
        } finally {
            $lock->release();
        }
    }

    private function assertDeliveryMailer(string $name): void
    {
        $config = config('mail.mailers.'.$name);
        if (in_array($config['transport'] ?? null, ['log', 'array'], true)) {
            throw new InvalidArgumentException('Configure a delivery mailer such as SMTP; log/array mailers do not deliver appointment emails.');
        }
        foreach ($config['mailers'] ?? [] as $child) {
            $this->assertDeliveryMailer($child);
        }
    }

    private function deliver(): int
    {
        $failed = false;
        $now = now();
        $notifications = DB::table('appointment_notifications')->whereNull('sent_at')
            ->where(function (Builder $query) use ($now): void {
                $query->where('attempts', 0);
                foreach ([1 => 1, 2 => 5, 3 => 15] as $attempts => $minutes) {
                    $query->orWhere(fn (Builder $retry) => $retry
                        ->where('attempts', $attempts)->where('updated_at', '<=', $now->copy()->subMinutes($minutes)));
                }
                $query->orWhere(fn (Builder $retry) => $retry
                    ->where('attempts', '>=', 4)->where('updated_at', '<=', $now->copy()->subHour()));
            })
            ->orderBy('updated_at')->orderBy('id')->limit(100)->get();
        $deadline = hrtime(true) + 300_000_000_000;
        foreach ($notifications as $notification) {
            if (hrtime(true) >= $deadline) {
                $this->info('Delivery time budget reached; remaining notifications will be sent on a later run.');
                break;
            }
            try {
                $this->assertDeliveryMailer(config('mail.default'));
                $recipient = $notification->recipient;
                if ($notification->audience === 'admin') {
                    $contact = Setting::query()->where('key', 'email')->first()?->value;
                    $recipient = is_array($contact) ? ($contact['tr'] ?? $contact['en'] ?? null) : $contact;
                }
                if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Configure a valid contact email in panel settings.');
                }
                Mail::to($recipient)->locale($notification->locale)->send(new AppointmentNotification(
                    json_decode($notification->details, true, 512, JSON_THROW_ON_ERROR),
                    $notification->event,
                    $notification->audience,
                ));
                DB::table('appointment_notifications')->where('id', $notification->id)->update([
                    'sent_at' => now(), 'last_error' => null, 'attempts' => $notification->attempts + 1, 'updated_at' => now(),
                ]);
            } catch (TransportExceptionInterface|InvalidArgumentException $exception) {
                report($exception);
                DB::table('appointment_notifications')->where('id', $notification->id)->update([
                    'last_error' => $exception instanceof InvalidArgumentException
                        ? $exception->getMessage() : 'Mail transport failed; see application logs.',
                    'attempts' => $notification->attempts + 1, 'updated_at' => now(),
                ]);
                $this->error("Appointment notification #{$notification->id} failed and will be retried.");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
