<?php

namespace App\Http\Requests;

use App\Enums\PreRegistrationCountry;
use App\Support\PreRegistrationSpamGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Letters only: no brackets, slashes or colons that could build links in the emails.
            'name' => ['required', 'string', 'max:120', 'regex:/^[\pL\pM\s\'.-]+$/u'],
            // No `unique` rule on purpose: telling whether an email is already registered
            // would let anyone check who pre-registered. Duplicates are handled silently.
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s().-]+$/'],
            'country' => ['nullable', Rule::enum(PreRegistrationCountry::class)],
            'message' => ['nullable', 'string', 'max:5000'],
            'agree' => ['required', 'accepted'],
            PreRegistrationSpamGuard::TOKEN_FIELD => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (PreRegistrationSpamGuard::tokenIssuedAt($value) === null) {
                    $fail('El formulario caducó. Recarga la página e inténtalo de nuevo.');
                }
            }],
            PreRegistrationSpamGuard::HONEYPOT_FIELD => ['nullable'],
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
            'country' => 'país',
            'message' => 'mensaje',
            'agree' => 'autorización para el tratamiento de tus datos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras, espacios, apóstrofos, puntos y guiones.',
            'phone.regex' => 'El teléfono solo puede contener números, espacios y los signos + ( ) -.',
            PreRegistrationSpamGuard::TOKEN_FIELD.'.required' => 'El formulario caducó. Recarga la página e inténtalo de nuevo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : $this->input('name'),
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : $this->input('email'),
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'country' => $this->filled('country') ? trim((string) $this->input('country')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : null,
        ]);
    }

    /**
     * Only the columns stored on the pre-registration; the spam fields are dropped.
     *
     * @return array<string, mixed>
     */
    public function preRegistrationData(): array
    {
        return $this->safe()->only(['name', 'email', 'phone', 'country', 'message', 'agree']);
    }
}
