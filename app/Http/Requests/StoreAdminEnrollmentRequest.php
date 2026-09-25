<?php

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility(PlatformAbility::Students) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->where(function ($query): void {
                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where('company_id', $companyId);
                    }
                }),
            ],
            'student_id' => [
                'required',
                'integer',
                'exists:students,id',
                Rule::unique('enrollments', 'student_id')->where(
                    fn ($query) => $query->where(
                        'course_id',
                        (int) $this->input('course_id')
                    )
                ),
            ],
        ];
    }
}
