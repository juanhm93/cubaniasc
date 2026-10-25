<?php

namespace App\Enums;

/**
 * Where a student should continue a review session.
 */
enum ReviewStep: string
{
    case SelectFigures = 'select_figures';
    case ReviewFigures = 'review_figures';
    case Quiz = 'quiz';
    case Songs = 'songs';
    case Expired = 'expired';
    case Completed = 'completed';
}
