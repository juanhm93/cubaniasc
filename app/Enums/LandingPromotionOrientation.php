<?php

namespace App\Enums;

/**
 * Shape of the promotion image, so the entry modal fits a vertical flyer or a horizontal banner.
 */
enum LandingPromotionOrientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';
}
