<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Services\Review\FigureSelectionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewSessionFiguresRequest extends FormRequest
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
            'level_content_ids' => ['required', 'array', 'min:1', 'max:'.FigureSelectionService::SELECTION_COUNT],
            'level_content_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'level_content_ids' => 'figuras seleccionadas',
        ];
    }
}
