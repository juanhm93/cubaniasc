<?php

declare(strict_types=1);

namespace App\Enums;

enum PlatformAbility: string
{
    case Content = 'content';
    case Payments = 'payments';
    case Courses = 'courses';
    case OneTimeSessions = 'oneTimeSessions';
    case Students = 'students';
    case AdminUsers = 'adminUsers';
}
