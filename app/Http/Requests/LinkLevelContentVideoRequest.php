<?php

namespace App\Http\Requests;

use App\Services\Content\FigureVideoStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A file of the bucket, to preview it or to make it the figure's video.
 */
class LinkLevelContentVideoRequest extends LevelContentVideoRequest
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
                'regex:/\.mp4$/i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(FigureVideoStorage::class)->isLibraryPath((string) $value)) {
                        $fail('Ese archivo no se puede vincular.');
                    }
                },
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'El archivo debe ser un video MP4.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'key' => 'archivo',
            'duration_seconds' => 'duración',
        ];
    }
}
