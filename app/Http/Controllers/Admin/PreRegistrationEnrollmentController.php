<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentFromPreRegistrationRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PreRegistration;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PreRegistrationEnrollmentController extends Controller
{
    /**
     * Form to convert a pre-registration into a student record.
     */
    public function create(PreRegistration $preRegistration): Response
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

        return Inertia::render('admin/pre-registrations/enroll', [
            'preRegistration' => [
                'id' => $preRegistration->id,
                'name' => $preRegistration->name,
                'email' => $preRegistration->email,
                'phone' => $preRegistration->phone,
            ],
            'courses' => $courses,
            'studentDraft' => [
                'name' => $preRegistration->name,
                'email' => $preRegistration->email,
                'phone' => $preRegistration->phone ?? '',
                'dni' => '',
                'birthday' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country' => '',
                'emergency_contact_name' => '',
                'emergency_contact_phone' => '',
                'course_id' => null,
            ],
        ]);
    }

    /**
     * Persist student (and optional enrollment), remove pre-registration.
     */
    public function store(StoreStudentFromPreRegistrationRequest $request, PreRegistration $preRegistration): RedirectResponse
    {
        $attributes = $request->validatedStudentAttributes();

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $courseId = isset($validated['course_id']) && is_numeric($validated['course_id'])
            ? (int) $validated['course_id']
            : null;

        $student = DB::transaction(function () use ($attributes, $courseId, $preRegistration): Student {
            $student = Student::query()->create($attributes);
            $preRegistration->delete();

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
