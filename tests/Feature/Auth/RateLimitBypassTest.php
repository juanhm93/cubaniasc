<?php

namespace Tests\Feature\Auth;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The hosting has no proxy in front, so a client-supplied X-Forwarded-For must not
 * give an attacker a fresh rate-limit bucket on every request.
 */
class RateLimitBypassTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_IP = '203.0.113.10';

    public function test_rotating_forwarded_for_does_not_bypass_the_panel_login_limit(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpoofedIp($attempt)
                ->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->fromSpoofedIp(6)
            ->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])
            ->assertTooManyRequests();
    }

    public function test_rotating_forwarded_for_does_not_bypass_the_app_code_limit(): void
    {
        $this->assertCodeVerificationIsThrottled(route('v1.auth.verify-code'), ['device_name' => 'Pixel 8']);
    }

    public function test_rotating_forwarded_for_does_not_bypass_the_review_panel_code_limit(): void
    {
        $this->assertCodeVerificationIsThrottled(route('review.auth.verify-code'));
    }

    public function test_forwarded_for_is_honoured_only_from_a_local_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'])
            ->get('/');

        $this->assertSame('198.51.100.7', request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT_IP, 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'])
            ->get('/');

        $this->assertSame(self::CLIENT_IP, request()->ip());
    }

    public function test_panel_login_is_capped_per_account_across_real_ips(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->fromRealIp($attempt)
                ->post(route('login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->fromRealIp(21)
            ->post(route('login.store'), ['email' => ' OWNER@example.com', 'password' => 'wrong-password'])
            ->assertTooManyRequests();
    }

    public function test_code_verification_is_capped_per_account_across_real_ips(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->fromRealIp($attempt)
                ->postJson(route('review.auth.verify-code'), ['email' => 'ana@example.com', 'code' => '000000'])
                ->assertUnprocessable();
        }

        $this->fromRealIp(21)
            ->postJson(route('review.auth.verify-code'), ['email' => 'ANA@example.com', 'code' => '000000'])
            ->assertTooManyRequests();
    }

    public function test_code_requests_are_capped_per_account_across_real_ips(): void
    {
        Mail::fake();
        $student = $this->createEnrolledStudent();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->fromRealIp($attempt)
                ->postJson(route('v1.auth.request-code'), ['email' => $student->email])
                ->assertStatus(202);
        }

        $this->fromRealIp(7)
            ->postJson(route('review.auth.request-code'), ['email' => strtoupper($student->email)])
            ->assertTooManyRequests();
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function assertCodeVerificationIsThrottled(string $url, array $extra = []): void
    {
        $payload = ['email' => 'ana@example.com', 'code' => '000000', ...$extra];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->fromSpoofedIp($attempt)->postJson($url, $payload)->assertUnprocessable();
        }

        $this->fromSpoofedIp(6)->postJson($url, $payload)->assertTooManyRequests();
    }

    /**
     * Same real client every time, with a different forged X-Forwarded-For.
     */
    private function fromSpoofedIp(int $attempt): static
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => self::CLIENT_IP,
            'HTTP_X_FORWARDED_FOR' => "198.51.100.{$attempt}",
        ]);
    }

    /**
     * A genuinely different client IP on every request (e.g. a botnet).
     */
    private function fromRealIp(int $attempt): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$attempt}"]);
    }

    private function createEnrolledStudent(): Student
    {
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create(['is_active' => true])->id,
            'status' => EnrollmentStatus::Active,
        ]);

        return $student;
    }
}
