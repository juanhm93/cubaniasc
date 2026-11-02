<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use App\Services\Content\SongAudioStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class StoreRecommendedSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility(PlatformAbility::Content) ?? false;
    }

    /**
     * The soft-deleted song of a file does not hold it: the file can become a new song.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'artist' => ['required', 'string', 'max:160'],
            'audio_file_path' => [
                'bail',
                $this->audioFileRequirement(),
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! app(SongAudioStorage::class)->isLibraryKey((string) $value)) {
                        $fail('Ese archivo no se puede usar.');
                    }
                },
                Rule::unique('recommended_songs', 'audio_file_path')
                    ->withoutTrashed()
                    ->ignore($this->route('recommendedSong')),
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'is_active' => ['required', 'boolean'],
            'level_ids' => ['required', 'array', 'min:1'],
            'level_ids.*' => ['integer', 'distinct', Rule::exists('levels', 'id')->withoutTrashed()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio_file_path.unique' => 'Ese archivo ya es otra canción del catálogo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'artist' => 'artista',
            'audio_file_path' => 'archivo',
            'duration_seconds' => 'duración',
            'is_active' => 'activa',
            'level_ids' => 'niveles',
            'level_ids.*' => 'nivel',
        ];
    }

    protected function audioFileRequirement(): string|RequiredIf
    {
        return 'required';
    }
}
