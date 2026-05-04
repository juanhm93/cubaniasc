<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicPreRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:pre_registrations,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:5000'],
            'agree' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'message' => 'mensaje',
            'agree' => 'autorización para el tratamiento de tus datos',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : $this->input('email'),
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : null,
        ]);
    }
}
