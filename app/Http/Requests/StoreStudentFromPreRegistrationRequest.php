<?php

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentFromPreRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAbility(PlatformAbility::Students) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')],
            'dni' => ['nullable', 'string', 'max:32'],
            'birthday' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedStudentAttributes(): array
    {
        $validated = $this->validated();

        $keys = [
            'name',
            'email',
            'dni',
            'birthday',
            'phone',
            'address',
            'city',
            'state',
            'zip',
            'country',
            'emergency_contact_name',
            'emergency_contact_phone',
        ];

        $data = [];

        foreach ($keys as $key) {
            $value = $validated[$key] ?? null;

            if ($value === '') {
                $value = null;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('course_id') === '' || $this->input('course_id') === null) {
            $this->merge(['course_id' => null]);
        }

        $trimEmptyToNull = [
            'dni',
            'birthday',
            'phone',
            'address',
            'city',
            'state',
            'zip',
            'country',
            'emergency_contact_name',
            'emergency_contact_phone',
        ];

        foreach ($trimEmptyToNull as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
