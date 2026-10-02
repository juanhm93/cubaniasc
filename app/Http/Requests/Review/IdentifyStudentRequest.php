<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IdentifyStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255', 'required_without:dni'],
            'dni' => ['nullable', 'string', 'max:32', 'required_without:email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'dni' => 'cédula',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'dni' => $this->filled('dni') ? trim((string) $this->input('dni')) : null,
        ]);
    }
}
