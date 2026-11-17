<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Enums\PlatformAbility;
use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpsertCourseAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility(PlatformAbility::Courses) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The student must belong to this course (any enrollment status, so past
            // sessions can still be corrected after a student leaves).
            'student_id' => [
                'required',
                'integer',
                Rule::exists('enrollments', 'student_id')
                    ->where('course_id', $this->courseId())
                    ->whereNull('deleted_at'),
            ],
            'status' => ['required', new Enum(AttendanceStatus::class)],
        ];
    }

    private function courseId(): int
    {
        $course = $this->route('course');

        return $course instanceof Course ? $course->id : (int) $course;
    }
}
