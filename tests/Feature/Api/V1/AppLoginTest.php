<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Api\V1\Auth\VerifyLoginCodeController;
use App\Mail\StudentLoginCodeMail;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentLoginCode;
use App\Services\Auth\StudentLoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_code_emails_a_code_to_an_enrolled_student_found_by_email(): void
    {
        Mail::fake();
        [$student] = $this->createEnrolledStudent();

        $this->postJson(route('v1.auth.request-code'), ['email' => strtoupper($student->email)])
            ->assertStatus(202)
            ->assertJsonPath('data.code_length', StudentLoginCodeService::CODE_LENGTH)
            ->assertJsonPath('data.expires_in_minutes', StudentLoginCodeService::EXPIRES_IN_MINUTES);

        Mail::assertSent(StudentLoginCodeMail::class, function (StudentLoginCodeMail $mail) use ($student): bool {
            return $mail->hasTo($student->email)
                && preg_match('/^\d{6}$/', $mail->code) === 1;
        });

        $this->assertSame(1, $student->loginCodes()->pending()->count());
    }

    public function test_request_code_finds_the_student_by_dni(): void
    {
        Mail::fake();
        [$student] = $this->createEnrolledStudent(['dni' => 'V-12345678']);

        $this->postJson(route('v1.auth.request-code'), ['dni' => ' V-12345678 '])
            ->assertStatus(202);

        Mail::assertSent(StudentLoginCodeMail::class, fn (StudentLoginCodeMail $mail): bool => $mail->hasTo($student->email));
    }

    public function test_request_code_answers_the_same_for_unknown_or_unenrolled_students(): void
    {
        Mail::fake();
        Student::factory()->create(['email' => 'sin-curso@example.com']);

        $unknown = $this->postJson(route('v1.auth.request-code'), ['email' => 'nadie@example.com']);
        $unenrolled = $this->postJson(route('v1.auth.request-code'), ['email' => 'sin-curso@example.com']);

        $unknown->assertStatus(202);
        $unenrolled->assertStatus(202);
        $this->assertSame($unknown->json(), $unenrolled->json());
        Mail::assertNothingSent();
        $this->assertDatabaseCount('student_login_codes', 0);
    }

    public function test_request_code_requires_email_or_dni(): void
    {
        $this->postJson(route('v1.auth.request-code'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'dni']);
    }

    public function test_requesting_a_new_code_invalidates_the_previous_one(): void
    {
        Mail::fake();
        [$student] = $this->createEnrolledStudent();
        $previous = StudentLoginCode::factory()->for($student)->withCode('111111')->create();

        $this->postJson(route('v1.auth.request-code'), ['email' => $student->email])->assertStatus(202);

        $this->assertNotNull($previous->fresh()->consumed_at);
        $this->assertSame(1, $student->loginCodes()->pending()->count());
    }

    public function test_request_code_is_rate_limited_per_identifier(): void
    {
        Mail::fake();
        [$student] = $this->createEnrolledStudent();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson(route('v1.auth.request-code'), ['email' => $student->email])->assertStatus(202);
        }

        $this->postJson(route('v1.auth.request-code'), ['email' => $student->email])
            ->assertTooManyRequests();
    }

    public function test_verify_code_returns_an_app_token_and_the_student_profile(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        StudentLoginCode::factory()->for($student)->withCode('482913')->create();

        $response = $this->postJson(route('v1.auth.verify-code'), [
            'email' => $student->email,
            'code' => '482 913',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_at',
                    'student' => [
                        'id', 'name', 'email', 'dni', 'phone', 'birthday',
                        'current_level' => ['id', 'name', 'slug', 'dance_type' => ['id', 'name']],
                    ],
                ],
            ])
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.student.current_level.id', $level->id);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Student::class,
            'tokenable_id' => $student->id,
            'name' => VerifyLoginCodeController::TOKEN_PREFIX.'Pixel 8',
        ]);
        $this->assertSame(0, $student->loginCodes()->pending()->count());
    }

    public function test_a_code_only_works_once(): void
    {
        [$student] = $this->createEnrolledStudent();
        StudentLoginCode::factory()->for($student)->withCode('482913')->create();
        $payload = ['email' => $student->email, 'code' => '482913', 'device_name' => 'Pixel 8'];

        $this->postJson(route('v1.auth.verify-code'), $payload)->assertOk();
        $this->postJson(route('v1.auth.verify-code'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_verify_code_rejects_a_wrong_code_and_counts_the_attempt(): void
    {
        [$student] = $this->createEnrolledStudent();
        $loginCode = StudentLoginCode::factory()->for($student)->withCode('482913')->create();

        $this->postJson(route('v1.auth.verify-code'), [
            'email' => $student->email,
            'code' => '000000',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame(1, $loginCode->fresh()->attempts);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_code_is_burned_after_too_many_wrong_attempts(): void
    {
        [$student] = $this->createEnrolledStudent();
        $loginCode = StudentLoginCode::factory()->for($student)->withCode('482913')->create([
            'attempts' => StudentLoginCodeService::MAX_ATTEMPTS - 1,
        ]);

        $this->postJson(route('v1.auth.verify-code'), [
            'email' => $student->email,
            'code' => '000000',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable();

        $this->assertNotNull($loginCode->fresh()->consumed_at);

        $this->postJson(route('v1.auth.verify-code'), [
            'email' => $student->email,
            'code' => '482913',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable();
    }

    public function test_verify_code_rejects_an_expired_code(): void
    {
        [$student] = $this->createEnrolledStudent();
        StudentLoginCode::factory()->for($student)->withCode('482913')->expired()->create();

        $this->postJson(route('v1.auth.verify-code'), [
            'email' => $student->email,
            'code' => '482913',
            'device_name' => 'Pixel 8',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_verify_code_validates_its_fields(): void
    {
        $this->postJson(route('v1.auth.verify-code'), ['email' => 'ana@example.com', 'code' => '12ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'device_name']);
    }

    public function test_signing_in_on_a_new_device_replaces_the_previous_app_token_but_keeps_the_review_token(): void
    {
        [$student] = $this->createEnrolledStudent();
        $student->createToken(VerifyLoginCodeController::TOKEN_PREFIX.'Old phone', ['app']);
        $student->createToken('review-panel');
        StudentLoginCode::factory()->for($student)->withCode('482913')->create();

        $this->postJson(route('v1.auth.verify-code'), [
            'email' => $student->email,
            'code' => '482913',
            'device_name' => 'New phone',
        ])->assertOk();

        $tokenNames = $student->tokens()->pluck('name')->all();
        $this->assertEqualsCanonicalizing([VerifyLoginCodeController::TOKEN_PREFIX.'New phone', 'review-panel'], $tokenNames);
    }

    public function test_me_returns_the_profile_for_an_app_token(): void
    {
        [$student, $level] = $this->createEnrolledStudent();
        $token = $student->createToken(VerifyLoginCodeController::TOKEN_PREFIX.'Pixel 8', ['app'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.current_level.id', $level->id);
    }

    public function test_me_returns_a_null_level_when_the_enrollment_is_no_longer_active(): void
    {
        $student = Student::factory()->create();
        $token = $student->createToken(VerifyLoginCodeController::TOKEN_PREFIX.'Pixel 8', ['app'])->plainTextToken;

        $this->withToken($token)
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertJsonPath('data.current_level', null);
    }

    public function test_me_rejects_guests_with_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_rejects_review_panel_tokens(): void
    {
        [$student] = $this->createEnrolledStudent();
        $token = $student->createToken('review-panel')->plainTextToken;

        $this->withToken($token)->getJson(route('v1.me'))->assertForbidden();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        [$student] = $this->createEnrolledStudent();
        $appToken = $student->createToken(VerifyLoginCodeController::TOKEN_PREFIX.'Pixel 8', ['app'])->plainTextToken;
        $student->createToken('review-panel');

        $this->withToken($appToken)->postJson(route('v1.auth.logout'))->assertNoContent();

        $this->assertSame(['review-panel'], $student->tokens()->pluck('name')->all());
    }

    public function test_login_codes_are_stored_hashed(): void
    {
        Mail::fake();
        [$student] = $this->createEnrolledStudent();

        $this->postJson(route('v1.auth.request-code'), ['email' => $student->email])->assertStatus(202);

        $sentCode = null;
        Mail::assertSent(StudentLoginCodeMail::class, function (StudentLoginCodeMail $mail) use (&$sentCode): bool {
            $sentCode = $mail->code;

            return true;
        });

        $stored = $student->loginCodes()->first();
        $this->assertNotSame($sentCode, $stored->code_hash);
        $this->assertTrue(Hash::check($sentCode, $stored->code_hash));
    }

    /**
     * @param  array<string, mixed>  $studentAttributes
     * @return array{0: Student, 1: Level}
     */
    private function createEnrolledStudent(array $studentAttributes = []): array
    {
        $level = Level::factory()->create();
        $course = Course::factory()->create([
            'level_id' => $level->id,
            'is_active' => true,
        ]);
        $student = Student::factory()->create($studentAttributes);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, $level];
    }
}
