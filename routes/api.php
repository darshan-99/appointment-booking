<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DoctorAvailabilityController;
use App\Http\Controllers\Api\AppointmentController;

Route::prefix('doctors/{doctor}')->group(function () {
    Route::post('availability', [DoctorAvailabilityController::class, 'store']);
    Route::get('availability', [DoctorAvailabilityController::class, 'index']);
    Route::get('slots', [DoctorAvailabilityController::class, 'slots']);
});

Route::prefix('appointments')->group(function () {
    Route::post('/', [AppointmentController::class, 'store']);
    Route::post('{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
});