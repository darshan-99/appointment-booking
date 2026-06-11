<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorAvailability;
use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function createAvailability(Doctor $doctor, array $data): DoctorAvailability
    {
        $this->ensureNoOverlap($doctor, $data);

        $availability = DoctorAvailability::create([
            'doctor_id'     => $doctor->id,
            'date'          => $data['date'],
            'start_time'    => $data['start_time'],
            'end_time'      => $data['end_time'],
            'slot_duration' => $data['slot_duration'],
        ]);

        $this->generateSlots($availability);

        return $availability;
    }

    public function getAvailableSlots(Doctor $doctor, string $date): Collection
    {
        $query = Slot::where('doctor_id', $doctor->id)
            ->where('date', $date)
            ->where('status', 'available')
            ->orderBy('start_time');

        if ($date === now()->format('Y-m-d')) {
            $query->where('start_time', '>', now()->format('H:i:s'));
        }

        return $query->get();
    }

    private function generateSlots(DoctorAvailability $availability): void
    {
        $slots    = [];
        $start    = Carbon::parse($availability->date->format('Y-m-d') . ' ' . $availability->start_time);
        $end      = Carbon::parse($availability->date->format('Y-m-d') . ' ' . $availability->end_time);
        $duration = $availability->slot_duration;

        while ($start->copy()->addMinutes($duration)->lte($end)) {
            $slots[] = [
                'doctor_availability_id' => $availability->id,
                'doctor_id'              => $availability->doctor_id,
                'date'                   => $availability->date->format('Y-m-d'),
                'start_time'             => $start->format('H:i:s'),
                'end_time'               => $start->copy()->addMinutes($duration)->format('H:i:s'),
                'status'                 => 'available',
                'created_at'             => now(),
                'updated_at'             => now(),
            ];

            $start->addMinutes($duration);
        }

        Slot::insert($slots);
    }

    private function ensureNoOverlap(Doctor $doctor, array $data): void
    {
        $overlaps = DoctorAvailability::where('doctor_id', $doctor->id)
            ->where('date', $data['date'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlaps) {
            abort(422, 'This availability window overlaps with an existing schedule.');
        }
    }
}