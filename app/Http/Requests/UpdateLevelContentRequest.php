<?php

namespace App\Http\Requests;

use App\Models\LevelContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLevelContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $levelContent = $this->route('levelContent');

        if (! $levelContent instanceof LevelContent) {
            return false;
        }

        return $this->user()?->can('update', $levelContent->level) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:255', 'url'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('video_url') && $this->input('video_url') === '') {
            $this->merge(['video_url' => null]);
        }
    }
}
