<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CubaniaLanding;
use Tests\TestCase;

class CubaniaLandingSliderTest extends TestCase
{
    public function test_discovers_slider_images_from_public_assets_directory(): void
    {
        config(['cubania.slider' => []]);

        $images = CubaniaLanding::sliderImages();

        $this->assertSame(
            [
                '/cubania-assets/slider/slider-1.webp',
                '/cubania-assets/slider/slider-2.webp',
                '/cubania-assets/slider/slider-3.webp',
                '/cubania-assets/slider/slider-4.webp',
            ],
            $images,
        );
    }

    public function test_configured_slider_images_take_precedence_over_discovery(): void
    {
        config([
            'cubania.slider' => [
                'https://example.com/custom.webp',
                '',
            ],
        ]);

        $this->assertSame(
            ['https://example.com/custom.webp'],
            CubaniaLanding::sliderImages(),
        );
    }
}
