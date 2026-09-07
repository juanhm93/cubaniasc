<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\CubaniaLanding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingCubaniaConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_shares_default_cubania_social_and_hero_config(): void
    {
        $shared = CubaniaLanding::shared();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('cubania.social.instagram', $shared['social']['instagram'])
                ->where('cubania.social.tiktok', $shared['social']['tiktok'])
                ->where('cubania.social.whatsapp', $shared['social']['whatsapp'])
                ->where('cubania.hero.youtubeUrl', $shared['hero']['youtubeUrl'])
                ->where('cubania.hero.youtubeId', 's4DT0BFxDEk')
                ->where('cubania.hero.playbackRate', 0.75)
                ->has('cubania.instructors', 3)
                ->where('cubania.instructors.0.image', $shared['instructors'][0]['image']));
    }

    public function test_landing_uses_overridden_cubania_config_values(): void
    {
        config([
            'cubania.social.instagram' => 'https://www.instagram.com/cubania.test',
            'cubania.social.tiktok' => 'https://www.tiktok.com/@cubania.test',
            'cubania.social.whatsapp' => 'https://wa.me/580000000000',
            'cubania.hero.youtube_url' => 'https://youtu.be/dQw4w9wgGcQ',
            'cubania.hero.playback_rate' => 0.4,
            'cubania.instructors' => [
                ['image' => 'https://example.com/instructor-a.jpg'],
                ['image' => 'https://example.com/instructor-b.jpg'],
                ['image' => 'https://example.com/instructor-c.jpg'],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('cubania.social.instagram', 'https://www.instagram.com/cubania.test')
                ->where('cubania.social.tiktok', 'https://www.tiktok.com/@cubania.test')
                ->where('cubania.social.whatsapp', 'https://wa.me/580000000000')
                ->where('cubania.hero.youtubeUrl', 'https://youtu.be/dQw4w9wgGcQ')
                ->where('cubania.hero.youtubeId', 'dQw4w9wgGcQ')
                ->where('cubania.hero.playbackRate', 0.5)
                ->where('cubania.instructors.0.image', 'https://example.com/instructor-a.jpg'));
    }

    public function test_pre_registration_page_receives_cubania_social_links(): void
    {
        config([
            'cubania.social.whatsapp' => 'https://wa.me/581111111111',
        ]);

        $this->get(route('pre-registration.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pre-registration')
                ->where('cubania.social.whatsapp', 'https://wa.me/581111111111'));
    }
}
