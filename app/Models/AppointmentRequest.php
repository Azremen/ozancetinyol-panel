<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppointmentRequest extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'preferred_date',
        'note',
        'privacy_consent_at',
        'locale',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'privacy_consent_at' => 'datetime',
        ];
    }
}
