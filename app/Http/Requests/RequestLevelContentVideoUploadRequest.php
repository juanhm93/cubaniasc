<?php

namespace App\Http\Requests;

use App\Services\Content\FigureVideoStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class RequestLevelContentVideoUploadRequest extends LevelContentVideoRequest
{
    /**
     * The size is the one the browser reports; the stored object is checked again on confirm.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content_type' => ['required', 'string', Rule::in([FigureVideoStorage::CONTENT_TYPE])],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.app(FigureVideoStorage::class)->maxUploadBytes()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMegabytes = intdiv(app(FigureVideoStorage::class)->maxUploadBytes(), 1024 * 1024);

        return [
            'content_type.in' => 'El archivo debe ser un video MP4.',
            'size_bytes.max' => "El video no puede pesar más de {$maxMegabytes} MB.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content_type' => 'tipo de archivo',
            'size_bytes' => 'tamaño del video',
        ];
    }
}
