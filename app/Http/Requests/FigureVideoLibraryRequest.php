<?php

namespace App\Http\Requests;

use App\Services\Content\FigureVideoStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FigureVideoLibraryRequest extends LevelContentVideoRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folder' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(FigureVideoStorage::class)->isLibraryPath((string) $value)) {
                        $fail('Esa carpeta no se puede abrir.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'folder' => 'carpeta',
        ];
    }
}
