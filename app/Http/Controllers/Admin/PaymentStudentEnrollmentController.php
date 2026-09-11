<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentFromPreRegistrationRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentStudentEnrollmentController extends Controller
{
    /**
     * Form to create a student from the payments workspace without a pre-registration.
     */
    public function create(): Response
    {
        $courses = Course::query()
            ->with('level:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'label' => $course->level?->name ?? ('Curso #'.$course->id),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/payments/enroll', [
            'courses' => $courses,
        ]);
    }

    /**
     * Persist student (and optional enrollment) from the payments workspace.
     */
    public function store(StoreStudentFromPreRegistrationRequest $request): RedirectResponse
    {
        $attributes = $request->validatedStudentAttributes();

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $courseId = isset($validated['course_id']) && is_numeric($validated['course_id'])
            ? (int) $validated['course_id']
            : null;

        $student = DB::transaction(function () use ($attributes, $courseId): Student {
            $student = Student::query()->create($attributes);

            if ($courseId !== null) {
                Enrollment::query()->create([
                    'course_id' => $courseId,
                    'student_id' => $student->id,
                    'status' => EnrollmentStatus::Active,
                    'enrolled_at' => now(),
                ]);
            }

            return $student;
        });

        return redirect()->route('admin.students.show', $student);
    }
}
