<?php

namespace App\Models;

use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Slot extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_availability_id',
        'doctor_id',
        'date',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'date'   => 'date',
        'status' => SlotStatus::class,
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function availability(): BelongsTo
    {
        return $this->belongsTo(DoctorAvailability::class, 'doctor_availability_id');
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }
}