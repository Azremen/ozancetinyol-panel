<?php

namespace App\Models;

use App\Services\AppointmentNotifications;
use App\Services\AppointmentSchedule;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AppointmentRequest extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $attributes = [
        'locale' => 'tr',
        'status' => 'pending',
    ];

    protected $fillable = [
        'name',
        'phone',
        'email',
        'preferred_date',
        'preferred_time',
        'note',
        'privacy_consent_at',
        'locale',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'duration_minutes' => 'integer',
            'privacy_consent_at' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        // Keep validation, persistence and outbox writes atomic for every model save.
        return DB::transaction(function () use ($options): bool {
            DB::table('appointment_schedule_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($this->exists) {
                $current = static::withTrashed()->lockForUpdate()->findOrFail($this->getKey());
                foreach (['name', 'phone', 'email', 'locale', 'status', 'preferred_date', 'preferred_time', 'duration_minutes', 'deleted_at'] as $field) {
                    if ($current->getRawOriginal($field) !== $this->getRawOriginal($field)) {
                        throw ValidationException::withMessages(['status' => __('appointments.stale_record')]);
                    }
                }
            }
            $scheduleChanged = ! $this->exists || $this->isDirty(['status', 'preferred_date', 'preferred_time', 'deleted_at']);
            $confirmed = $this->status === 'confirmed' && ($scheduleChanged || $this->isDirty(['email', 'duration_minutes']));
            if ($confirmed) {
                Validator::make([
                    'email' => $this->email,
                    'preferred_date' => $this->preferred_date?->toDateString(),
                    'preferred_time' => $this->preferred_time,
                ], [
                    'email' => ['required', 'email', 'max:255'],
                    'preferred_date' => ['required', 'date_format:Y-m-d'],
                    'preferred_time' => ['required', 'date_format:H:i'],
                ], ['required' => __('appointments.confirmation_requires_details')])->validate();
                $schedule = app(AppointmentSchedule::class);
                $retainDuration = $this->exists && $this->getRawOriginal('status') === 'confirmed'
                    && ! $this->isDirty(['preferred_date', 'preferred_time']);
                $duration = $retainDuration && $this->getRawOriginal('duration_minutes')
                    ? (int) $this->getRawOriginal('duration_minutes') : (int) $schedule->settings()['duration'];
                $schedule->assertAvailable($this->preferred_date->toDateString(), $this->preferred_time, $this->id, $duration);
                $this->duration_minutes = $duration;
            }
            $creating = ! $this->exists;
            $result = parent::save($options);
            if ($result && ($creating || $confirmed)) {
                app(AppointmentNotifications::class)->record($this, $confirmed ? 'confirmed' : 'received');
            }

            return $result;
        });
    }
}
