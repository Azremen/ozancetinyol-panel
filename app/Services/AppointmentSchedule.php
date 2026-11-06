<?php

namespace App\Services;

use App\Models\AppointmentRequest;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class AppointmentSchedule
{
    public function timezone(): string
    {
        return config('app.timezone');
    }

    public function settings(): array
    {
        return Setting::query()->where('key', 'appointment_schedule')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->first()?->value ?? [
                'duration' => 60,
                'days' => array_map(fn (int $day): array => [
                    'day' => $day, 'enabled' => false, 'start' => '09:00', 'end' => '18:00',
                ], range(1, 7)),
                'closed_dates' => [],
            ];
    }

    public function today(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    public function slots(string $date, ?string $exceptId = null, ?int $appointmentDuration = null): array
    {
        $settings = $this->settings();
        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone);
        $day = CarbonImmutable::parse($date, $timezone);
        $hours = collect($settings['days'])->firstWhere('day', $day->isoWeekday());
        if (! $hours || ! $hours['enabled'] || in_array($date, array_column($settings['closed_dates'], 'date'), true)) {
            return [];
        }

        $duration = (int) $settings['duration'];
        if ($duration < 15 || $duration > 180) {
            throw new UnexpectedValueException('Appointment duration must be between 15 and 180 minutes.');
        }
        $appointmentDuration ??= $duration;
        if ($appointmentDuration < 15 || $appointmentDuration > 180) {
            throw new UnexpectedValueException('Appointment duration must be between 15 and 180 minutes.');
        }
        $end = CarbonImmutable::parse($date.' '.$hours['end'], $timezone);
        $bookings = AppointmentRequest::query()
            ->whereDate('preferred_date', $date)->where('status', 'confirmed')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->get(['preferred_time', 'duration_minutes']);
        $slots = [];
        for ($start = CarbonImmutable::parse($date.' '.$hours['start'], $timezone);
            $start->addMinutes($appointmentDuration)->lte($end);
            $start = $start->addMinutes($duration)) {
            $slotEnd = $start->addMinutes($appointmentDuration);
            $busy = $bookings->contains(function (AppointmentRequest $booking) use ($date, $start, $slotEnd, $duration, $timezone): bool {
                // Legacy confirmations without a time block the day until repaired.
                if (! $booking->preferred_time) {
                    return true;
                }
                $bookingStart = CarbonImmutable::parse($date.' '.$booking->preferred_time, $timezone);

                return $start->lt($bookingStart->addMinutes($booking->duration_minutes ?? $duration))
                    && $slotEnd->gt($bookingStart);
            });
            $slots[] = [
                'time' => $start->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'status' => $busy ? 'busy' : ($start->lte($now) ? 'past' : 'available'),
            ];
        }

        return $slots;
    }

    public function assertAvailable(string $date, string $time, ?string $exceptId = null, ?int $appointmentDuration = null): void
    {
        $slot = collect($this->slots($date, $exceptId, $appointmentDuration))->firstWhere('time', $time);
        if (! $slot || $slot['status'] !== 'available') {
            throw ValidationException::withMessages(['preferred_time' => __('appointments.unavailable')]);
        }
    }
}
