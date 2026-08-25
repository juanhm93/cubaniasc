<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DanceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderLevelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $danceType = $this->route('danceType');

        if (! $danceType instanceof DanceType) {
            return false;
        }

        return $this->user()?->can('update', $danceType) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:levels,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');

        return array_map(intval(...), $ids);
    }
}
