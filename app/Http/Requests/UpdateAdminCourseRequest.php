<?php

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility(PlatformAbility::Courses) ?? false;
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
                    $currentTeacherId = $course instanceof Course ? $course->user_id : null;

                    // Eligible teachers, or the course's current teacher so it can be kept as is.
                    $query->where(function ($q) use ($currentTeacherId): void {
                        $q->where(fn ($eligible) => $eligible->where('status', 'active')
                            ->whereNull('deleted_at')
                            ->where(fn ($withRole) => $withRole->whereNotNull('role_id')->orWhere('is_owner', true)));

                        if ($currentTeacherId !== null) {
                            $q->orWhere('id', $currentTeacherId);
                        }
                    });

                    $companyId = $this->user()?->company_id;

                    if ($companyId !== null) {
                        $query->where(function ($q) use ($companyId, $currentTeacherId): void {
                            $q->where('company_id', $companyId);
                            if ($currentTeacherId !== null) {
                                $q->orWhere('id', $currentTeacherId);
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
