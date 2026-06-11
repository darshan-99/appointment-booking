<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvailabilityRequest;
use App\Models\Doctor;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorAvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {}

    public function index(Doctor $doctor): JsonResponse
    {
        $availabilities = $doctor->availabilities()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'doctor'         => $doctor->only('id', 'name', 'specialization'),
            'availabilities' => $availabilities,
        ]);
    }

    public function store(StoreAvailabilityRequest $request, Doctor $doctor): JsonResponse
    {
        $availability = $this->availabilityService->createAvailability(
            $doctor,
            $request->validated()
        );

        return response()->json([
            'message'      => 'Availability created successfully.',
            'availability' => $availability->load('slots'),
        ], 201);
    }

    public function slots(Request $request, Doctor $doctor): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $slots = $this->availabilityService->getAvailableSlots(
            $doctor,
            $request->date
        );

        return response()->json([
            'doctor' => $doctor->only('id', 'name', 'specialization'),
            'date'   => $request->date,
            'slots'  => $slots,
        ]);
    }
}