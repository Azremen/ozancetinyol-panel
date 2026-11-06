<?php

namespace Database\Factories;

use App\Models\AppointmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentRequest>
 */
class AppointmentRequestFactory extends Factory
{
    protected $model = AppointmentRequest::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->e164PhoneNumber(),
            'preferred_date' => fake()->optional()->dateTimeBetween('today', '+30 days'),
            'note' => null,
            'privacy_consent_at' => now(),
            'locale' => 'tr',
            'status' => 'pending',
        ];
    }
}
