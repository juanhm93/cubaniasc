<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Course;
use App\Models\DanceType;
use App\Models\Level;
use App\Models\Role;
use App\Models\User;
use App\Support\ReviewSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourseReviewSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_set_custom_review_settings(): void
    {
        $admin = $this->userWithRole('admin');
        $course = Course::factory()->create(['company_id' => $admin->company_id]);

        $this->actingAs($admin)
            ->patch(route('admin.courses.review-settings.update', $course), $this->customPayload())
            ->assertRedirect(route('admin.courses.show', $course));

        $course->refresh();

        $this->assertTrue($course->review_custom_settings);
        $this->assertSame(2, $course->review_previous_levels_count);
        $this->assertSame(6, $course->review_figure_options_count);
        $this->assertSame(3, $course->review_figure_selection_count);
        $this->assertSame(2700, $course->review_duration_seconds);
    }

    public function test_all_earlier_levels_are_saved_as_null(): void
    {
        $admin = $this->userWithRole('admin');
        $course = Course::factory()->create(['company_id' => $admin->company_id]);

        $this->actingAs($admin)
            ->patch(route('admin.courses.review-settings.update', $course), $this->customPayload([
                'review_previous_levels_count' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($course->refresh()->review_previous_levels_count);
    }

    public function test_back_to_normal_keeps_the_custom_values_but_reviews_as_usual(): void
    {
        $admin = $this->userWithRole('admin');
        $level = Level::factory()->create(['review_duration_seconds' => 1800]);
        $course = Course::factory()->customReview([
            'review_previous_levels_count' => 2,
            'review_figure_options_count' => 6,
            'review_figure_selection_count' => 3,
            'review_duration_seconds' => 2700,
        ])->create(['company_id' => $admin->company_id, 'level_id' => $level->id]);

        $this->actingAs($admin)
            ->patch(route('admin.courses.review-settings.update', $course), ['review_custom_settings' => '0'])
            ->assertSessionHasNoErrors();

        $course->refresh();
        $settings = ReviewSettings::forCourse($course);

        $this->assertFalse($course->review_custom_settings);
        $this->assertSame(6, $course->review_figure_options_count);
        $this->assertNull($settings->previousLevelsCount);
        $this->assertSame(4, $settings->figureOptionsCount);
        $this->assertSame(2, $settings->figureSelectionCount);
        $this->assertSame(1800, $settings->durationSeconds);
    }

    public function test_teacher_can_set_the_review_of_a_course(): void
    {
        $teacher = $this->userWithRole('teacher');
        $course = Course::factory()->create(['company_id' => $teacher->company_id]);

        $this->actingAs($teacher)
            ->patch(route('admin.courses.review-settings.update', $course), $this->customPayload())
            ->assertSessionHasNoErrors();

        $this->assertTrue($course->refresh()->review_custom_settings);
    }

    public function test_user_without_courses_ability_cannot_change_the_review(): void
    {
        $user = $this->userWithRole('sin-permisos');
        $course = Course::factory()->create(['company_id' => $user->company_id]);

        $this->actingAs($user)
            ->patch(route('admin.courses.review-settings.update', $course), $this->customPayload())
            ->assertForbidden();

        $this->assertFalse($course->refresh()->review_custom_settings);
    }

    public function test_user_of_another_company_cannot_change_the_review(): void
    {
        $admin = $this->userWithRole('admin');
        $course = Course::factory()->create(['company_id' => Company::factory()->create()->id]);

        $this->actingAs($admin)
            ->patch(route('admin.courses.review-settings.update', $course), $this->customPayload())
            ->assertForbidden();

        $this->assertFalse($course->refresh()->review_custom_settings);
    }

    public function test_custom_values_are_validated(): void
    {
        $admin = $this->userWithRole('admin');
        $course = Course::factory()->create(['company_id' => $admin->company_id]);

        $this->actingAs($admin)
            ->patch(route('admin.courses.review-settings.update', $course), $this->customPayload([
                'review_figure_options_count' => 4,
                'review_figure_selection_count' => 5,
                'review_duration_minutes' => 200,
            ]))
            ->assertSessionHasErrors(['review_figure_selection_count', 'review_duration_minutes']);

        $this->assertFalse($course->refresh()->review_custom_settings);
    }

    public function test_course_show_exposes_the_review_settings(): void
    {
        $admin = $this->userWithRole('admin');
        $danceType = DanceType::factory()->create();
        Level::factory()->create(['dance_type_id' => $danceType->id, 'sort_order' => 1]);
        Level::factory()->create(['dance_type_id' => $danceType->id, 'sort_order' => 2]);
        $level = Level::factory()->create([
            'dance_type_id' => $danceType->id,
            'sort_order' => 3,
            'review_duration_seconds' => 1800,
        ]);
        $course = Course::factory()->create(['company_id' => $admin->company_id, 'level_id' => $level->id]);

        $this->actingAs($admin)
            ->get(route('admin.courses.show', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/courses/show')
                ->where('reviewSettings.custom', false)
                ->where('reviewSettings.values.figure_options_count', 4)
                ->where('reviewSettings.defaults.figure_selection_count', 2)
                ->where('reviewSettings.defaults.duration_minutes', 30)
                ->where('reviewSettings.available_previous_levels', 2));
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::factory()->create(['slug' => $slug]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function customPayload(array $overrides = []): array
    {
        return [
            'review_custom_settings' => '1',
            'review_previous_levels_count' => 2,
            'review_figure_options_count' => 6,
            'review_figure_selection_count' => 3,
            'review_duration_minutes' => 45,
            ...$overrides,
        ];
    }
}
