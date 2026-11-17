<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Http\Requests\V1\Auth\RequestLoginCodeRequest;
use App\Services\Auth\StudentLoginCodeService;
use Illuminate\Contracts\Validation\ValidationRule;

class VerifyReviewLoginCodeRequest extends RequestLoginCodeRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'digits:'.StudentLoginCodeService::CODE_LENGTH],
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
