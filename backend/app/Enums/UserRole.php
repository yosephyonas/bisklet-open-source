<?php

namespace App\Enums;

enum UserRole: string
{
    case Rider = 'rider';
    case Admin = 'admin';
    case Mechanic = 'mechanic';
}
