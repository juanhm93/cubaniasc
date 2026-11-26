<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use App\Models\Level;
use App\Models\ReviewSession;

/**
 * How a group reviews: how many earlier levels enter, how many figures are offered and
 * chosen, and how long the session lasts. A course in normal mode (and a session saved
 * before courses had settings) reviews with {@see self::defaultsFor()}.
 */
final readonly class ReviewSettings
{
    public const DEFAULT_FIGURE_OPTIONS_COUNT = 4;

    public const DEFAULT_FIGURE_SELECTION_COUNT = 2;

    /**
     * @param  int|null  $previousLevelsCount  Earlier levels of the dance type in the review; null means all of them.
     * @param  bool  $figuresByLevel  Each review level offers its own `$figureOptionsCount` figures (shown in
     *                                one tab per level) instead of one mixed list; the selection count is
     *                                still the total, so the student can mix levels.
     */
    public function __construct(
        public ?int $previousLevelsCount,
        public int $figureOptionsCount,
        public int $figureSelectionCount,
        public int $durationSeconds,
        public bool $figuresByLevel = false,
    ) {}

    /**
     * Every earlier level, 4 figures offered, 2 chosen and the level's duration.
     */
    public static function defaultsFor(Level $level): self
    {
        return new self(
            previousLevelsCount: null,
            figureOptionsCount: self::DEFAULT_FIGURE_OPTIONS_COUNT,
            figureSelectionCount: self::DEFAULT_FIGURE_SELECTION_COUNT,
            durationSeconds: (int) $level->review_duration_seconds,
        );
    }

    /**
     * The course's own values when it is in custom mode; otherwise the defaults of its
     * current level. A custom value left empty falls back to its default. A custom course
     * that includes earlier levels offers its figures level by level.
     */
    public static function forCourse(Course $course, ?Level $level = null): self
    {
        $level ??= $course->level;
        $defaults = self::defaultsFor($level);

        if (! $course->review_custom_settings) {
            return $defaults;
        }

        $optionsCount = $course->review_figure_options_count ?? $defaults->figureOptionsCount;

        return new self(
            previousLevelsCount: $course->review_previous_levels_count,
            figureOptionsCount: $optionsCount,
            figureSelectionCount: min($course->review_figure_selection_count ?? $defaults->figureSelectionCount, $optionsCount),
            durationSeconds: $course->review_duration_seconds ?? $defaults->durationSeconds,
            figuresByLevel: $course->review_previous_levels_count !== 0,
        );
    }

    /**
     * Settings of the course the session was started from, applied to the session's level.
     */
    public static function forSession(ReviewSession $session): self
    {
        $session->loadMissing(['level', 'course']);

        if ($session->course === null) {
            return self::defaultsFor($session->level);
        }

        return self::forCourse($session->course, $session->level);
    }
}
