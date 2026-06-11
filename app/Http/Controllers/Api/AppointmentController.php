<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService
    ) {}

    public function store(BookAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->appointmentService->book($request->validated());

        return response()->json([
            'message'     => 'Appointment booked successfully.',
            'appointment' => $appointment->load('slot', 'doctor', 'patient'),
        ], 201);
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $appointment = $this->appointmentService->cancel(
            $appointment,
            $request->validated('cancellation_reason')
        );

        return response()->json([
            'message'     => 'Appointment cancelled successfully.',
            'appointment' => $appointment->load('slot'),
        ]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $appointment = $this->appointmentService->reschedule(
            $appointment,
            $request->validated('new_slot_id')
        );

        return response()->json([
            'message'     => 'Appointment rescheduled successfully.',
            'appointment' => $appointment->load('slot', 'doctor'),
        ]);
    }
}