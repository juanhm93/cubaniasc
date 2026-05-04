<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'is_active' => ['required', 'boolean'],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($course): void {
                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where(function ($q) use ($companyId, $course): void {
                            $q->where('company_id', $companyId);
                            if ($course instanceof Course && $course->user_id !== null) {
                                $q->orWhere('id', $course->user_id);
                            }
                        });
                    }
                }),
            ],
            'place_id' => [
                'required',
                'integer',
                Rule::exists('places', 'id')->where(function ($query) use ($course): void {
                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where(function ($q) use ($companyId, $course): void {
                            $q->where('company_id', $companyId);
                            if ($course instanceof Course && $course->place_id !== null) {
                                $q->orWhere('id', $course->place_id);
                            }
                        });
                    }
                }),
            ],
        ];
    }
}
