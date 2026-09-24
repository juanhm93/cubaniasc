<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        if (! $student instanceof Student) {
            return false;
        }

        return $this->user()?->can('update', $student) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->route('student');
        $canUpdateEmail = $student instanceof Student
            && ($this->user()?->can('updateEmail', $student) ?? false);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
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
        ];

        if ($canUpdateEmail) {
            $rules['email'] = [
                'required',
                'email',
                'max:255',
                Rule::unique('students', 'email')->ignore($student instanceof Student ? $student->id : null),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function studentAttributes(): array
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
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];

            if ($value === '') {
                $value = null;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
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
