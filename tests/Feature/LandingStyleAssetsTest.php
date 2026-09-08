<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LandingStyleAssetsTest extends TestCase
{
    public function test_style_card_background_images_exist(): void
    {
        foreach ([
            'salsa-casino.webp',
            'bachata.webp',
            'rueda-casino.webp',
        ] as $file) {
            $this->assertFileExists(public_path('cubania-assets/'.$file));
        }
    }

    public function test_styles_section_uses_background_images_and_accent_title(): void
    {
        $section = (string) file_get_contents(
            resource_path('js/components/base/cubania/cubania-styles-section.tsx'),
        );
        $card = (string) file_get_contents(
            resource_path('js/components/cards/style-card.tsx'),
        );
        $css = (string) file_get_contents(
            resource_path('css/landing/cubania-landing.css'),
        );

        $this->assertStringContainsString('/cubania-assets/salsa-casino.webp', $section);
        $this->assertStringContainsString('/cubania-assets/bachata.webp', $section);
        $this->assertStringContainsString('/cubania-assets/rueda-casino.webp', $section);
        $this->assertStringContainsString('cubania-section-header__title-accent', $section);
        $this->assertStringContainsString('cubania-styles__header', $section);
        $this->assertStringContainsString('onActivate', $section);

        $this->assertStringContainsString('cubania-style-card__media', $card);
        $this->assertStringContainsString('onActivate', $card);
        $this->assertStringNotContainsString('cubania-style-card__icon', $card);

        $this->assertStringContainsString('.cubania-landing .cubania-styles__header', $css);
        $this->assertStringContainsString('text-align: center', $css);
        $this->assertStringContainsString('.cubania-landing .cubania-styles .cubania-section-header__label', $css);
        $this->assertStringContainsString('color: var(--cubania-amarillo-glow)', $css);
        $this->assertStringContainsString('.cubania-landing .cubania-style-card__name', $css);
        $this->assertStringContainsString('font-size: 1.75rem', $css);
    }
}
