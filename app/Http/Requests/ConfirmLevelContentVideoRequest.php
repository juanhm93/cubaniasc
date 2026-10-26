<?php

namespace App\Http\Requests;

use App\Services\Content\FigureVideoStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ConfirmLevelContentVideoRequest extends LevelContentVideoRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'bail',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $levelContent = $this->levelContent();

                    if ($levelContent === null || ! app(FigureVideoStorage::class)->isUploadKeyFor($levelContent, (string) $value)) {
                        $fail('El video subido no corresponde a esta figura.');
                    }
                },
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => 'video subido',
            'duration_seconds' => 'duración',
        ];
    }
}
