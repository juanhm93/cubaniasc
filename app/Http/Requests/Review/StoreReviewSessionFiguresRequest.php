<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Models\ReviewSession;
use App\Support\ReviewSettings;
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
            'level_content_ids' => ['required', 'array', 'min:1', 'max:'.$this->maxSelectionCount()],
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

    /**
     * Figures the student may choose, set by the course the session was started from.
     */
    private function maxSelectionCount(): int
    {
        $session = $this->route('session');

        if (! $session instanceof ReviewSession) {
            return ReviewSettings::DEFAULT_FIGURE_SELECTION_COUNT;
        }

        return ReviewSettings::forSession($session)->figureSelectionCount;
    }
}
