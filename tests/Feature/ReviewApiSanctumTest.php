<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewApiSanctumTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_access_tokens_table_exists_after_migration(): void
    {
        $this->assertTrue(
            \Schema::hasTable('personal_access_tokens'),
            'Sanctum personal_access_tokens table should exist.',
        );
    }

    public function test_student_can_create_api_token(): void
    {
        $student = Student::factory()->create();

        $token = $student->createToken('review-panel');

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Student::class,
            'tokenable_id' => $student->id,
            'name' => 'review-panel',
        ]);
    }

    public function test_authenticated_student_can_access_protected_review_route(): void
    {
        $student = Student::factory()->create([
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'dni' => '12345678',
        ]);

        Sanctum::actingAs($student);

        $this->getJson(route('review.me'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana López')
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.dni', '12345678');
    }

    public function test_guest_cannot_access_protected_review_route(): void
    {
        $this->getJson(route('review.me'))
            ->assertUnauthorized();
    }

    public function test_student_can_authenticate_with_bearer_token(): void
    {
        $student = Student::factory()->create([
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
        ]);

        $plainTextToken = $student->createToken('review-panel')->plainTextToken;

        $this->withToken($plainTextToken)
            ->getJson(route('review.me'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Carlos Ruiz')
            ->assertJsonPath('data.email', 'carlos@example.com');
    }

    public function test_identify_route_is_rate_limited_by_email_or_dni(): void
    {
        RateLimiter::clear('review-identify');

        $student = Student::factory()->create(['email' => 'alumno@example.com']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create(['is_active' => true])->id,
            'status' => EnrollmentStatus::Active,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('review.identify'), [
                'email' => 'alumno@example.com',
            ])->assertOk();
        }

        $this->postJson(route('review.identify'), [
            'email' => 'alumno@example.com',
        ])->assertTooManyRequests();
    }
}
