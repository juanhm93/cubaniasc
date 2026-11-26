/**
 * Review values of a course. `previous_levels_count` null means every earlier level.
 */
export type CourseReviewValues = {
    previous_levels_count: number | null;
    figure_options_count: number;
    figure_selection_count: number;
    duration_minutes: number;
};

/**
 * Review mode of a course as the course page receives it: `values` are the saved custom
 * values (or the normal ones when none were saved), `defaults` what normal mode uses.
 */
export type CourseReviewSettings = {
    custom: boolean;
    values: CourseReviewValues;
    defaults: CourseReviewValues & { previous_levels_count: null };
    available_previous_levels: number;
    limits: {
        max_figure_options: number;
        min_duration_minutes: number;
        max_duration_minutes: number;
    };
};
