<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DanceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDanceTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
