<?php

namespace App\Enums;

enum BikeStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case InRide = 'in_ride';
    case Maintenance = 'maintenance';
    case Offline = 'offline';
}
