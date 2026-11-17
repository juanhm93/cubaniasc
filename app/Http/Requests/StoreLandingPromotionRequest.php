<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LandingPromotionDisplayMode;
use App\Enums\LandingPromotionOrientation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLandingPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isOwner() || $user->isAdmin());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'image' => [$this->imageRequirement(), 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'cta_url' => ['nullable', 'url:http,https', 'max:255'],
            'display_mode' => ['required', Rule::enum(LandingPromotionDisplayMode::class)],
            'modal_orientation' => ['required', Rule::enum(LandingPromotionOrientation::class)],
            'is_active' => ['required', 'boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'image' => 'imagen',
            'cta_label' => 'texto del botón',
            'cta_url' => 'enlace del botón',
            'display_mode' => 'mostrar como',
            'modal_orientation' => 'orientación del modal',
            'starts_on' => 'fecha de inicio',
            'ends_on' => 'fecha de fin',
        ];
    }

    protected function imageRequirement(): string
    {
        return 'required';
    }
}
