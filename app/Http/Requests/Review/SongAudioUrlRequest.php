<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Enums\MediaUrlPurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Who can get the song depends on the surface, so each controller checks that.
 */
class SongAudioUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['nullable', Rule::enum(MediaUrlPurpose::class)],
        ];
    }

    /**
     * Playing is the main action, so it is what a request without a purpose gets.
     */
    public function purpose(): MediaUrlPurpose
    {
        return $this->enum('purpose', MediaUrlPurpose::class) ?? MediaUrlPurpose::Stream;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purpose' => 'uso',
        ];
    }
}
