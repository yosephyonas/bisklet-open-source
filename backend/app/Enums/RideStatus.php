<?php

namespace App\Enums;

enum RideStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
