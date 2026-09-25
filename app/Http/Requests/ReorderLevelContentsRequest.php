<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Level;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderLevelContentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $level = $this->route('level');

        if (! $level instanceof Level) {
            return false;
        }

        return $this->user()?->can('update', $level) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:level_contents,id'],
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
