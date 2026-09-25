<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecommendedSongRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'artist' => ['required', 'string', 'max:160'],
            'audio_or_link_url' => ['required', 'string', 'url:http,https', 'max:512'],
            'is_active' => ['required', 'boolean'],
            'level_ids' => ['required', 'array', 'min:1'],
            'level_ids.*' => ['integer', 'distinct', Rule::exists('levels', 'id')->withoutTrashed()],
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
            'audio_or_link_url' => 'enlace',
            'is_active' => 'activa',
            'level_ids' => 'niveles',
            'level_ids.*' => 'nivel',
        ];
    }
}
