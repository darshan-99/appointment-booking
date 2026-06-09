<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case BOOKED = 'booked';
    case CANCELLED = 'cancelled';
    case RESCHEDULED = 'rescheduled';
}
