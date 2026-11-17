<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Migrations and cache clearing ask for the owner's password on every run, so a
 * stolen session alone is not enough to touch the production database.
 */
class RunMaintenanceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Escribe tu contraseña para confirmar la acción.',
            'password.current_password' => 'La contraseña no es correcta.',
        ];
    }
}
