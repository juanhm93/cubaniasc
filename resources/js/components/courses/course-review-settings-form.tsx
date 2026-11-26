import { Form } from '@inertiajs/react';
import { useState } from 'react';
import CourseReviewSettingsController from '@/actions/App/Http/Controllers/Admin/CourseReviewSettingsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/i18n/use-translation';
import { cn } from '@/lib/utils';
import type { CourseReviewSettings } from '@/types/course';

type CourseReviewSettingsFormProps = {
    courseId: number;
    settings: CourseReviewSettings;
};

const SELECT_CLASS =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

/** Empty value of the select: every earlier level enters the review. */
const ALL_PREVIOUS_LEVELS = '';

/**
 * Normal or custom review for the students of a course. Custom values stay in state
 * while switching modes, so going back to custom does not lose what was typed.
 */
export function CourseReviewSettingsForm({
    courseId,
    settings,
}: CourseReviewSettingsFormProps) {
    const { t } = useTranslation();
    const [custom, setCustom] = useState(settings.custom);
    const [previousLevels, setPreviousLevels] = useState(
        settings.values.previous_levels_count === null
            ? ALL_PREVIOUS_LEVELS
            : String(settings.values.previous_levels_count),
    );
    const [optionsCount, setOptionsCount] = useState(
        String(settings.values.figure_options_count),
    );
    const [selectionCount, setSelectionCount] = useState(
        String(settings.values.figure_selection_count),
    );
    const [durationMinutes, setDurationMinutes] = useState(
        String(settings.values.duration_minutes),
    );
    const update = CourseReviewSettingsController({ course: courseId });
    const { defaults, limits } = settings;
    const availablePreviousLevels = settings.available_previous_levels;

    return (
        <Form
            action={update.url}
            method={update.method}
            options={{ preserveScroll: true }}
            className="grid gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <fieldset className="grid gap-3 sm:grid-cols-2">
                        <legend className="sr-only">
                            {t('admin.courses.review.modeLabel')}
                        </legend>
                        <ModeOption
                            value="0"
                            checked={!custom}
                            onSelect={() => setCustom(false)}
                            title={t('admin.courses.review.normal')}
                            description={t(
                                'admin.courses.review.normalDescription',
                            )}
                        />
                        <ModeOption
                            value="1"
                            checked={custom}
                            onSelect={() => setCustom(true)}
                            title={t('admin.courses.review.custom')}
                            description={t(
                                'admin.courses.review.customDescription',
                            )}
                        />
                    </fieldset>
                    <InputError message={errors.review_custom_settings} />

                    {custom ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1 sm:col-span-2">
                                <Label htmlFor="review-previous-levels">
                                    {t('admin.courses.review.previousLevels')}
                                </Label>
                                <select
                                    id="review-previous-levels"
                                    name="review_previous_levels_count"
                                    className={SELECT_CLASS}
                                    value={previousLevels}
                                    onChange={(event) =>
                                        setPreviousLevels(event.target.value)
                                    }
                                >
                                    <option value={ALL_PREVIOUS_LEVELS}>
                                        {t(
                                            'admin.courses.review.allPreviousLevels',
                                        )}
                                    </option>
                                    <option value="0">
                                        {t(
                                            'admin.courses.review.onlyCurrentLevel',
                                        )}
                                    </option>
                                    {Array.from(
                                        { length: availablePreviousLevels },
                                        (_, index) => index + 1,
                                    ).map((levels) => (
                                        <option key={levels} value={levels}>
                                            {t(
                                                'admin.courses.review.previousLevelsOption',
                                                { levels },
                                            )}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    {availablePreviousLevels > 0
                                        ? t(
                                              'admin.courses.review.previousLevelsHint',
                                              {
                                                  levels: availablePreviousLevels,
                                              },
                                          )
                                        : t(
                                              'admin.courses.review.noPreviousLevelsHint',
                                          )}
                                </p>
                                <InputError
                                    message={
                                        errors.review_previous_levels_count
                                    }
                                />
                            </div>

                            <NumberField
                                id="review-figure-options"
                                name="review_figure_options_count"
                                label={t('admin.courses.review.figureOptions')}
                                hint={t(
                                    'admin.courses.review.figureOptionsHint',
                                    { max: limits.max_figure_options },
                                )}
                                min={1}
                                max={limits.max_figure_options}
                                value={optionsCount}
                                onChange={setOptionsCount}
                                error={errors.review_figure_options_count}
                            />
                            <NumberField
                                id="review-figure-selection"
                                name="review_figure_selection_count"
                                label={t(
                                    'admin.courses.review.figureSelection',
                                )}
                                hint={t(
                                    'admin.courses.review.figureSelectionHint',
                                )}
                                min={1}
                                max={
                                    Number(optionsCount) ||
                                    limits.max_figure_options
                                }
                                value={selectionCount}
                                onChange={setSelectionCount}
                                error={errors.review_figure_selection_count}
                            />
                            <NumberField
                                id="review-duration"
                                name="review_duration_minutes"
                                label={t('admin.courses.review.duration')}
                                hint={t('admin.courses.review.durationHint', {
                                    min: limits.min_duration_minutes,
                                    max: limits.max_duration_minutes,
                                    level: defaults.duration_minutes,
                                })}
                                min={limits.min_duration_minutes}
                                max={limits.max_duration_minutes}
                                value={durationMinutes}
                                onChange={setDurationMinutes}
                                error={errors.review_duration_minutes}
                            />
                        </div>
                    ) : (
                        <ul className="grid gap-1 rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 text-sm text-muted-foreground">
                            <li>{t('admin.courses.review.summaryLevels')}</li>
                            <li>
                                {t('admin.courses.review.summaryFigures', {
                                    options: defaults.figure_options_count,
                                    selection: defaults.figure_selection_count,
                                })}
                            </li>
                            <li>
                                {t('admin.courses.review.summaryDuration', {
                                    minutes: defaults.duration_minutes,
                                })}
                            </li>
                        </ul>
                    )}

                    <div>
                        <Button
                            type="submit"
                            className="h-10 w-full sm:w-auto"
                            disabled={processing}
                        >
                            {processing
                                ? t('admin.courses.review.saving')
                                : t('admin.courses.review.save')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

type ModeOptionProps = {
    value: string;
    checked: boolean;
    onSelect: () => void;
    title: string;
    description: string;
};

function ModeOption({
    value,
    checked,
    onSelect,
    title,
    description,
}: ModeOptionProps) {
    return (
        <label
            className={cn(
                'flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors',
                checked
                    ? 'border-primary bg-primary/5'
                    : 'border-sidebar-border/70 hover:bg-muted/40',
            )}
        >
            <input
                type="radio"
                name="review_custom_settings"
                value={value}
                checked={checked}
                onChange={onSelect}
                className="mt-1 size-4 accent-primary"
            />
            <span className="grid gap-1">
                <span className="text-sm font-medium">{title}</span>
                <span className="text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </label>
    );
}

type NumberFieldProps = {
    id: string;
    name: string;
    label: string;
    hint: string;
    min: number;
    max: number;
    value: string;
    onChange: (value: string) => void;
    error?: string;
};

function NumberField({
    id,
    name,
    label,
    hint,
    min,
    max,
    value,
    onChange,
    error,
}: NumberFieldProps) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                type="number"
                inputMode="numeric"
                min={min}
                max={max}
                step={1}
                required
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <p className="text-xs text-muted-foreground">{hint}</p>
            <InputError message={error} />
        </div>
    );
}
