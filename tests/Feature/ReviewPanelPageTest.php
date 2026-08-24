<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewPanelPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_can_view_review_panel_page(): void
    {
        $this->get(route('review-panel'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('repaso'));
    }
}
