<?php

namespace App\Enums;

enum SlotStatus: string
{
    case AVAILABLE = 'available';
    case BOOKED = 'booked';
}
