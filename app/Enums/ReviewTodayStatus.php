<?php

namespace App\Enums;

/**
 * State of the student's daily review: one session per local day.
 */
enum ReviewTodayStatus: string
{
    case Available = 'available';
    case InProgress = 'in_progress';
    case Locked = 'locked';
}
