<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use App\Models\CourseScheduleSlot;
use Illuminate\Support\Collection;

/**
 * Human readable pieces of a course (courses have no name of their own) shared
 * by the admin screens and the notifications.
 */
final class CourseSummary
{
    /**
     * "Salsa cubana — Nivel 1", falling back to the level alone or the id.
     */
    public static function label(Course $course): string
    {
        $levelName = $course->level?->name;

        if (! is_string($levelName) || $levelName === '') {
            return 'Curso #'.$course->id;
        }

        $danceTypeName = $course->level?->danceType?->name;

        return is_string($danceTypeName) && $danceTypeName !== ''
            ? $danceTypeName.' — '.$levelName
            : $levelName;
    }

    /**
     * "Lun 19:00–20:00 · Mié 19:00–20:00".
     *
     * @param  Collection<int, CourseScheduleSlot>  $slots
     */
    public static function schedule(Collection $slots): string
    {
        if ($slots->isEmpty()) {
            return '';
        }

        return $slots
            ->sortBy(fn (CourseScheduleSlot $slot): array => [$slot->sort_order, $slot->weekday])
            ->map(function (CourseScheduleSlot $slot): string {
                $day = self::weekdayShort((int) $slot->weekday);
                $from = self::time($slot->starts_at);
                $to = self::time($slot->ends_at);

                return "{$day} {$from}–{$to}";
            })
            ->implode(' · ');
    }

    public static function weekdayShort(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
            default => '?',
        };
    }

    /**
     * "19:00" from a stored "19:00:00" time.
     */
    public static function time(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $str = is_string($value) ? $value : (string) $value;

        if (preg_match('/^\d{2}:\d{2}/', $str, $m)) {
            return substr($m[0], 0, 5);
        }

        return $str;
    }
}
