<?php

namespace App\Http\Requests;

use App\Models\LevelContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Managing a figure's MP4 needs the same permission as editing the figure.
 */
class LevelContentVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $levelContent = $this->levelContent();

        if ($levelContent === null) {
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
        return [];
    }

    protected function levelContent(): ?LevelContent
    {
        $levelContent = $this->route('levelContent');

        return $levelContent instanceof LevelContent ? $levelContent : null;
    }
}
