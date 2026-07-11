<?php

namespace Tests\Feature\Review;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Review\StudentNotIdentifiableException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Review\StudentIdentificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentIdentificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudentIdentificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StudentIdentificationService::class);
    }

    public function test_identifies_student_by_email_when_enrolled(): void
    {
        $student = Student::factory()->create([
            'email' => 'Ana@Example.com',
            'dni' => '12345678',
        ]);

        $this->enroll($student);

        $identified = $this->service->identify(email: 'ana@example.com');

        $this->assertTrue($student->is($identified));
    }

    public function test_identifies_student_by_dni_when_enrolled(): void
    {
        $student = Student::factory()->create([
            'email' => 'carlos@example.com',
            'dni' => 'V-99887766',
        ]);

        $this->enroll($student);

        $identified = $this->service->identify(dni: 'V-99887766');

        $this->assertTrue($student->is($identified));
    }

    public function test_prefers_email_over_dni_when_both_are_provided(): void
    {
        $studentByEmail = Student::factory()->create(['email' => 'match@example.com', 'dni' => '111']);
        $studentByDni = Student::factory()->create(['email' => 'other@example.com', 'dni' => '222']);

        $this->enroll($studentByEmail);
        $this->enroll($studentByDni);

        $identified = $this->service->identify(email: 'match@example.com', dni: '222');

        $this->assertTrue($studentByEmail->is($identified));
    }

    public function test_throws_when_identifier_is_missing(): void
    {
        $this->expectException(StudentNotIdentifiableException::class);
        $this->expectExceptionMessage('An email or DNI is required');

        $this->service->identify();
    }

    public function test_throws_when_student_is_not_found(): void
    {
        $this->expectException(StudentNotIdentifiableException::class);
        $this->expectExceptionMessage('No student matches');

        $this->service->identify(email: 'missing@example.com');
    }

    public function test_throws_when_student_is_not_enrolled(): void
    {
        Student::factory()->create(['email' => 'solo@example.com']);

        $this->expectException(StudentNotIdentifiableException::class);
        $this->expectExceptionMessage('not enrolled');

        $this->service->identify(email: 'solo@example.com');
    }

    public function test_throws_when_enrollment_is_not_active(): void
    {
        $student = Student::factory()->create(['email' => 'inactive@example.com']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'status' => EnrollmentStatus::Withdrawn,
        ]);

        $this->expectException(StudentNotIdentifiableException::class);
        $this->expectExceptionMessage('not enrolled');

        $this->service->identify(email: 'inactive@example.com');
    }

    private function enroll(Student $student): Enrollment
    {
        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => Course::factory()->create(['is_active' => true])->id,
            'status' => EnrollmentStatus::Active,
        ]);
    }
}
