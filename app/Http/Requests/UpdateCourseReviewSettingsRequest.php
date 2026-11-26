<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PlatformAbility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Review mode of a course: normal (the academy's usual review) or custom, with its own
 * earlier levels, figures offered and chosen, and duration.
 */
class UpdateCourseReviewSettingsRequest extends FormRequest
{
    public const MAX_PREVIOUS_LEVELS = 20;

    public const MAX_FIGURE_OPTIONS = 12;

    public const MIN_DURATION_MINUTES = 5;

    public const MAX_DURATION_MINUTES = 120;

    public function authorize(): bool
    {
        return $this->user()?->hasAbility(PlatformAbility::Courses) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('review_custom_settings')) {
            $this->merge([
                'review_custom_settings' => filter_var($this->input('review_custom_settings'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * The custom values are only validated (and saved) in custom mode; in normal mode the
     * last custom values stay stored for when the course goes back to custom.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_custom_settings' => ['required', 'boolean'],
            'review_previous_levels_count' => [
                'exclude_unless:review_custom_settings,true',
                'nullable',
                'integer',
                'min:0',
                'max:'.self::MAX_PREVIOUS_LEVELS,
            ],
            'review_figure_options_count' => [
                'exclude_unless:review_custom_settings,true',
                'required',
                'integer',
                'min:1',
                'max:'.self::MAX_FIGURE_OPTIONS,
            ],
            'review_figure_selection_count' => [
                'exclude_unless:review_custom_settings,true',
                'required',
                'integer',
                'min:1',
                'lte:review_figure_options_count',
            ],
            'review_duration_minutes' => [
                'exclude_unless:review_custom_settings,true',
                'required',
                'integer',
                'min:'.self::MIN_DURATION_MINUTES,
                'max:'.self::MAX_DURATION_MINUTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'review_custom_settings' => 'modo del repaso',
            'review_previous_levels_count' => 'niveles anteriores',
            'review_figure_options_count' => 'figuras ofrecidas',
            'review_figure_selection_count' => 'figuras a elegir',
            'review_duration_minutes' => 'duración del repaso',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_figure_selection_count.lte' => 'No se pueden elegir más figuras de las que se ofrecen.',
        ];
    }

    /**
     * Validated values as `courses` columns: the duration goes from minutes to seconds.
     * In normal mode only the mode changes.
     *
     * @return array{review_custom_settings: bool, review_previous_levels_count?: int|null, review_figure_options_count?: int, review_figure_selection_count?: int, review_duration_seconds?: int}
     */
    public function reviewSettings(): array
    {
        $validated = $this->validated();

        if (! $validated['review_custom_settings']) {
            return ['review_custom_settings' => false];
        }

        return [
            'review_custom_settings' => true,
            'review_previous_levels_count' => isset($validated['review_previous_levels_count'])
                ? (int) $validated['review_previous_levels_count']
                : null,
            'review_figure_options_count' => (int) $validated['review_figure_options_count'],
            'review_figure_selection_count' => (int) $validated['review_figure_selection_count'],
            'review_duration_seconds' => (int) $validated['review_duration_minutes'] * 60,
        ];
    }
}
