<?php

namespace App\Enums;

/**
 * Where a landing promotion appears: entry modal, section after the hero, or both.
 */
enum LandingPromotionDisplayMode: string
{
    case Modal = 'modal';
    case Section = 'section';
    case Both = 'both';

    public function showsModal(): bool
    {
        return $this !== self::Section;
    }

    public function showsSection(): bool
    {
        return $this !== self::Modal;
    }
}
