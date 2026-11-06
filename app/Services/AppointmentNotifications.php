<?php

namespace App\Services;

use App\Models\AppointmentRequest;
use Illuminate\Support\Facades\DB;

class AppointmentNotifications
{
    public function record(AppointmentRequest $appointment, string $event): void
    {
        $details = json_encode([
            'name' => $appointment->name,
            'phone' => $appointment->phone,
            'date' => $appointment->preferred_date?->format('d.m.Y'),
            'time' => $appointment->preferred_time,
            'timezone' => app(AppointmentSchedule::class)->timezone(),
        ], JSON_THROW_ON_ERROR);

        foreach (['customer', 'admin'] as $audience) {
            if ($audience === 'customer' && ! $appointment->email) {
                continue;
            }
            DB::table('appointment_notifications')->insert([
                'appointment_request_id' => $appointment->id,
                'recipient' => $audience === 'customer' ? $appointment->email : null,
                'audience' => $audience,
                'locale' => $audience === 'customer' ? ($appointment->locale ?? 'tr') : 'tr',
                'event' => $event,
                'details' => $details,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
