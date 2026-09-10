<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Course;
use App\Models\CourseScheduleSlot;

final class BuildPublicWeeklySchedule
{
    public const GRID_START_HOUR = 16;

    public const DEFAULT_END_HOUR = 21;

    public const FIRST_WEEKDAY = 1;

    public const LAST_WEEKDAY = 5;

    /**
     * Public Monday–Friday timetable of active courses, from 4pm onward.
     *
     * @return array{
     *     hours: list<string>,
     *     days: list<array{
     *         weekday: int,
     *         cells: list<array{hour: string, occupied: bool, labels: list<string>}>
     *     }>
     * }
     */
    public function execute(): array
    {
        $courses = Course::query()
            ->active()
            ->with(['scheduleSlots', 'level.danceType'])
            ->get();

        /** @var array<int, array<int, list<string>>> $labelsByWeekdayAndHour */
        $labelsByWeekdayAndHour = [];
        $lastHour = self::DEFAULT_END_HOUR;

        foreach ($courses as $course) {
            $label = $this->courseLabel($course);

            foreach ($course->scheduleSlots as $slot) {
                $weekday = (int) $slot->weekday;

                if ($weekday < self::FIRST_WEEKDAY || $weekday > self::LAST_WEEKDAY) {
                    continue;
                }

                foreach ($this->occupiedHours($slot) as $hour) {
                    $lastHour = max($lastHour, $hour);
                    $labelsByWeekdayAndHour[$weekday][$hour] ??= [];

                    if (! in_array($label, $labelsByWeekdayAndHour[$weekday][$hour], true)) {
                        $labelsByWeekdayAndHour[$weekday][$hour][] = $label;
                    }
                }
            }
        }

        $hours = range(self::GRID_START_HOUR, $lastHour);
        $hourLabels = array_map(fn (int $hour): string => $this->formatHour($hour), $hours);

        $days = [];

        for ($weekday = self::FIRST_WEEKDAY; $weekday <= self::LAST_WEEKDAY; $weekday++) {
            $cells = [];

            foreach ($hours as $hour) {
                $labels = $labelsByWeekdayAndHour[$weekday][$hour] ?? [];

                $cells[] = [
                    'hour' => $this->formatHour($hour),
                    'occupied' => $labels !== [],
                    'labels' => $labels,
                ];
            }

            $days[] = [
                'weekday' => $weekday,
                'cells' => $cells,
            ];
        }

        return [
            'hours' => $hourLabels,
            'days' => $days,
        ];
    }

    private function courseLabel(Course $course): string
    {
        $danceTypeName = $course->level?->danceType?->name;

        if (is_string($danceTypeName) && $danceTypeName !== '') {
            return $danceTypeName;
        }

        $levelName = $course->level?->name;

        if (is_string($levelName) && $levelName !== '') {
            return $levelName;
        }

        return 'Clase';
    }

    /**
     * @return list<int>
     */
    private function occupiedHours(CourseScheduleSlot $slot): array
    {
        $startMinutes = $this->timeToMinutes($slot->starts_at);
        $endMinutes = $this->timeToMinutes($slot->ends_at);
        $gridStartMinutes = self::GRID_START_HOUR * 60;

        if ($endMinutes <= $gridStartMinutes || $endMinutes <= $startMinutes) {
            return [];
        }

        $hours = [];

        for ($hour = self::GRID_START_HOUR; $hour <= 23; $hour++) {
            $hourStart = $hour * 60;
            $hourEnd = $hourStart + 60;

            if ($startMinutes < $hourEnd && $endMinutes > $hourStart) {
                $hours[] = $hour;
            }
        }

        return $hours;
    }

    private function timeToMinutes(mixed $value): int
    {
        $str = is_string($value) ? $value : (string) $value;

        if (preg_match('/^(\d{1,2}):(\d{2})/', $str, $matches) !== 1) {
            return 0;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    private function formatHour(int $hour): string
    {
        return sprintf('%02d:00', $hour);
    }
}
