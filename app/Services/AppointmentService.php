<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use App\Exceptions\DomainException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Slot;
use App\Notifications\BookingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentService
{
    public function book(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            // Lock the slot row to prevent concurrent booking
            $slot = Slot::lockForUpdate()->findOrFail($data['slot_id']);

            if ($slot->status === SlotStatus::BOOKED) {
                throw new DomainException('This slot has already been booked.', 409);
            }

            if ($slot->date->isPast() && $slot->end_time < now()->format('H:i:s')) {
                throw new DomainException('Cannot book a slot in the past.', 422);
            }

            $slot->update(['status' => SlotStatus::BOOKED]);

            $appointment = Appointment::create([
                'reference_number' => $this->generateReference(),
                'patient_id'       => $data['patient_id'],
                'doctor_id'        => $slot->doctor_id,
                'slot_id'          => $slot->id,
                'status'           => AppointmentStatus::BOOKED,
            ]);

            $patient = Patient::findOrFail($data['patient_id']);
            $patient->notify(new BookingNotification($appointment, 'booked'));

            return $appointment;
        });
    }

    public function cancel(Appointment $appointment, string $reason): Appointment
    {
        if ($appointment->status === AppointmentStatus::CANCELLED) {
            throw new DomainException('Appointment is already cancelled.', 422);
        }

        DB::transaction(function () use ($appointment, $reason) {
            $appointment->slot->update(['status' => SlotStatus::AVAILABLE]);

            $appointment->update([
                'status'              => AppointmentStatus::CANCELLED,
                'cancellation_reason' => $reason,
            ]);

            $patient = $appointment->patient;
            $patient->notify(new BookingNotification($appointment, 'cancelled'));
        });

        return $appointment->fresh();
    }

    public function reschedule(Appointment $appointment, int $newSlotId): Appointment
    {
        if ($appointment->status === AppointmentStatus::CANCELLED) {
            throw new DomainException('Cannot reschedule a cancelled appointment.', 422);
        }

        return DB::transaction(function () use ($appointment, $newSlotId) {
            $newSlot = Slot::lockForUpdate()->findOrFail($newSlotId);

            if ($newSlot->status === SlotStatus::BOOKED) {
                throw new DomainException('The requested slot is not available.', 409);
            }

            if ($newSlot->date->isPast() && $newSlot->end_time < now()->format('H:i:s')) {
                throw new DomainException('Cannot reschedule to a slot in the past.', 422);
            }

            // Free old slot & book new slot
            $appointment->slot->update(['status' => SlotStatus::AVAILABLE]);
            $newSlot->update(['status' => SlotStatus::BOOKED]);

            $appointment->update([
                'slot_id'   => $newSlot->id,
                'doctor_id' => $newSlot->doctor_id,
                'status'    => AppointmentStatus::RESCHEDULED,
            ]);

            $patient = $appointment->patient;
            $patient->notify(new BookingNotification($appointment, 'rescheduled'));

            return $appointment->fresh();
        });
    }

    private function generateReference(): string
    {
        return 'APT-' . strtoupper(Str::random(8));
    }
}