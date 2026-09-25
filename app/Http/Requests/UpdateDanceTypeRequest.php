<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DanceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDanceTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
