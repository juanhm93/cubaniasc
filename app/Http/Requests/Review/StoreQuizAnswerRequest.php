<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuizAnswerRequest extends FormRequest
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
            'quiz_option_id' => ['required', 'integer', 'exists:quiz_options,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quiz_option_id' => 'opción seleccionada',
        ];
    }
}
