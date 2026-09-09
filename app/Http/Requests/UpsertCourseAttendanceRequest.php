<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Enums\PlatformAbility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'status' => ['required', new Enum(AttendanceStatus::class)],
        ];
    }
}
