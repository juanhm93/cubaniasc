<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Services\Auth\StudentLoginCodeService;
use Illuminate\Contracts\Validation\ValidationRule;

class VerifyLoginCodeRequest extends RequestLoginCodeRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'digits:'.StudentLoginCodeService::CODE_LENGTH],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'code' => 'código',
            'device_name' => 'nombre del dispositivo',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'code' => $this->filled('code') ? preg_replace('/\s+/', '', (string) $this->input('code')) : null,
        ]);
    }
}
