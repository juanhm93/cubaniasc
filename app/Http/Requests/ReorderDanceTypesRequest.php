<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DanceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderDanceTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DanceType::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:dance_types,id'],
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
