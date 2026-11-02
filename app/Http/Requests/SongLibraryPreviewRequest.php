<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use App\Services\Content\SongAudioStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A file of the music folder, to listen to it before creating its song.
 */
class SongLibraryPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility(PlatformAbility::Content) ?? false;
    }

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
                    if (! app(SongAudioStorage::class)->isLibraryKey((string) $value)) {
                        $fail('Ese archivo no se puede escuchar.');
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
            'key' => 'archivo',
        ];
    }
}
