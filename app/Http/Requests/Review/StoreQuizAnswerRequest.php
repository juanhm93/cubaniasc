<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Models\QuizItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $item = $this->route('item');

        return [
            'quiz_option_id' => [
                'required',
                'integer',
                Rule::exists('quiz_options', 'id')->where(
                    'quiz_item_id',
                    $item instanceof QuizItem ? $item->id : 0,
                ),
            ],
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
