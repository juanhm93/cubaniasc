<?php

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToggleCourseLevelContentRequest extends FormRequest
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
            'level_content_id' => ['required', 'integer', 'exists:level_contents,id'],
        ];
    }
}
