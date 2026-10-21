<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseScheduleSlot;
use App\Models\DanceType;
use App\Models\Level;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_fetch_the_empty_weekly_schedule(): void
    {
        $this->getJson(route('schedule.index'))
            ->assertOk()
            ->assertJsonPath('hours.0', '16:00')
            ->assertJsonPath('hours.5', '21:00')
            ->assertJsonCount(6, 'hours')
            ->assertJsonCount(5, 'days')
            ->assertJsonPath('days.0.weekday', 1)
            ->assertJsonPath('days.4.weekday', 5)
            ->assertJsonPath('days.0.cells.0.occupied', false)
            ->assertJsonPath('days.0.cells.0.classes', []);
    }

    public function test_active_course_slots_occupy_matching_weekday_hours(): void
    {
        $this->createCourseWithSlots('Salsa Casino', [
            ['weekday' => 1, 'starts_at' => '17:00:00', 'ends_at' => '18:00:00'],
            ['weekday' => 3, 'starts_at' => '17:00:00', 'ends_at' => '18:00:00'],
        ]);

        $response = $this->getJson(route('schedule.index'))->assertOk();

        $this->assertCell($response->json('days'), 1, '17:00', true, [$this->scheduleClass('Salsa Casino')]);
        $this->assertCell($response->json('days'), 3, '17:00', true, [$this->scheduleClass('Salsa Casino')]);
        $this->assertCell($response->json('days'), 1, '16:00', false, []);
        $this->assertCell($response->json('days'), 2, '17:00', false, []);
    }

    public function test_inactive_courses_are_excluded(): void
    {
        $this->createCourseWithSlots('Bachata', [
            ['weekday' => 2, 'starts_at' => '18:00:00', 'ends_at' => '19:00:00'],
        ], ['is_active' => false]);

        $this->assertCell(
            $this->getJson(route('schedule.index'))->assertOk()->json('days'),
            2,
            '18:00',
            false,
            [],
        );
    }

    public function test_weekend_slots_are_excluded(): void
    {
        $this->createCourseWithSlots('Rueda de Casino', [
            ['weekday' => 6, 'starts_at' => '17:00:00', 'ends_at' => '18:00:00'],
            ['weekday' => 7, 'starts_at' => '17:00:00', 'ends_at' => '18:00:00'],
        ]);

        $days = $this->getJson(route('schedule.index'))->assertOk()->json('days');

        $this->assertCount(5, $days);

        foreach ($days as $day) {
            foreach ($day['cells'] as $cell) {
                $this->assertFalse($cell['occupied']);
            }
        }
    }

    public function test_slots_that_end_at_four_are_not_shown(): void
    {
        $this->createCourseWithSlots('Salsa Casino', [
            ['weekday' => 4, 'starts_at' => '15:00:00', 'ends_at' => '16:00:00'],
        ]);

        $this->assertCell(
            $this->getJson(route('schedule.index'))->assertOk()->json('days'),
            4,
            '16:00',
            false,
            [],
        );
    }

    public function test_slots_that_overlap_four_occupy_the_first_hour(): void
    {
        $this->createCourseWithSlots('Salsa Casino', [
            ['weekday' => 5, 'starts_at' => '15:30:00', 'ends_at' => '16:30:00'],
        ]);

        $this->assertCell(
            $this->getJson(route('schedule.index'))->assertOk()->json('days'),
            5,
            '16:00',
            true,
            [$this->scheduleClass('Salsa Casino')],
        );
    }

    public function test_slots_spanning_two_hours_occupy_both_columns(): void
    {
        $this->createCourseWithSlots('Bachata', [
            ['weekday' => 1, 'starts_at' => '19:00:00', 'ends_at' => '20:30:00'],
        ]);

        $days = $this->getJson(route('schedule.index'))->assertOk()->json('days');

        $this->assertCell($days, 1, '19:00', true, [$this->scheduleClass('Bachata')]);
        $this->assertCell($days, 1, '20:00', true, [$this->scheduleClass('Bachata')]);
        $this->assertCell($days, 1, '21:00', false, []);
    }

    public function test_late_slots_extend_the_hour_columns(): void
    {
        $this->createCourseWithSlots('Salsa Casino', [
            ['weekday' => 2, 'starts_at' => '22:00:00', 'ends_at' => '23:00:00'],
        ]);

        $this->getJson(route('schedule.index'))
            ->assertOk()
            ->assertJsonPath('hours.0', '16:00')
            ->assertJsonPath('hours.6', '22:00');
    }

    public function test_overlapping_dance_types_share_the_same_cell(): void
    {
        $this->createCourseWithSlots('Salsa Casino', [
            ['weekday' => 3, 'starts_at' => '18:00:00', 'ends_at' => '19:00:00'],
        ]);
        $this->createCourseWithSlots('Bachata', [
            ['weekday' => 3, 'starts_at' => '18:00:00', 'ends_at' => '19:00:00'],
        ]);

        $cell = $this->cell(
            $this->getJson(route('schedule.index'))->assertOk()->json('days'),
            3,
            '18:00',
        );

        $this->assertTrue($cell['occupied']);
        $this->assertEqualsCanonicalizing(
            [$this->scheduleClass('Salsa Casino'), $this->scheduleClass('Bachata')],
            $cell['classes'],
        );
    }

    public function test_cells_include_the_course_current_level(): void
    {
        $this->createCourseWithSlots('Salsa Casino', [
            ['weekday' => 3, 'starts_at' => '17:00:00', 'ends_at' => '18:00:00'],
        ], levelName: 'Avanzado 3');

        $this->getJson(route('schedule.index'))
            ->assertOk()
            ->assertJsonPath('days.2.cells.1.classes', [
                ['danceType' => 'Salsa Casino', 'level' => 'Avanzado 3'],
            ]);
    }

    public function test_same_dance_type_with_different_levels_in_the_same_hour_lists_both(): void
    {
        $danceType = DanceType::factory()->create(['name' => 'Salsa Casino']);

        $this->createCourseWithSlots($danceType, [
            ['weekday' => 2, 'starts_at' => '18:00:00', 'ends_at' => '19:00:00'],
        ], levelName: 'Básico 1');
        $this->createCourseWithSlots($danceType, [
            ['weekday' => 2, 'starts_at' => '18:00:00', 'ends_at' => '19:00:00'],
        ], levelName: 'Avanzado 3');

        $cell = $this->cell(
            $this->getJson(route('schedule.index'))->assertOk()->json('days'),
            2,
            '18:00',
        );

        $this->assertEqualsCanonicalizing(
            [$this->scheduleClass('Salsa Casino', 'Básico 1'), $this->scheduleClass('Salsa Casino', 'Avanzado 3')],
            $cell['classes'],
        );
    }

    public function test_back_to_back_courses_keep_their_own_level(): void
    {
        $danceType = DanceType::factory()->create(['name' => 'Salsa Casino']);

        $this->createCourseWithSlots($danceType, [
            ['weekday' => 1, 'starts_at' => '17:00:00', 'ends_at' => '18:00:00'],
        ], levelName: 'Básico 1');
        $this->createCourseWithSlots($danceType, [
            ['weekday' => 1, 'starts_at' => '18:00:00', 'ends_at' => '19:00:00'],
        ], levelName: 'Avanzado 3');

        $days = $this->getJson(route('schedule.index'))->assertOk()->json('days');

        $this->assertCell($days, 1, '17:00', true, [$this->scheduleClass('Salsa Casino', 'Básico 1')]);
        $this->assertCell($days, 1, '18:00', true, [$this->scheduleClass('Salsa Casino', 'Avanzado 3')]);
    }

    /**
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $slots
     * @param  array<string, mixed>  $courseAttributes
     */
    private function createCourseWithSlots(
        DanceType|string $danceType,
        array $slots,
        array $courseAttributes = [],
        string $levelName = 'Básico 1',
    ): Course {
        if (is_string($danceType)) {
            $danceType = DanceType::factory()->create(['name' => $danceType]);
        }

        $level = Level::factory()->create([
            'dance_type_id' => $danceType->id,
            'name' => $levelName,
        ]);
        $course = Course::factory()->create([
            'level_id' => $level->id,
            'is_active' => true,
            ...$courseAttributes,
        ]);

        $course->scheduleSlots()->delete();

        foreach ($slots as $index => $slot) {
            CourseScheduleSlot::query()->create([
                'course_id' => $course->id,
                'weekday' => $slot['weekday'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'sort_order' => $index,
            ]);
        }

        return $course;
    }

    /**
     * @return array{danceType: string, level: ?string}
     */
    private function scheduleClass(string $danceType, string $level = 'Básico 1'): array
    {
        return ['danceType' => $danceType, 'level' => $level];
    }

    /**
     * @param  list<array{weekday: int, cells: list<array{hour: string, occupied: bool, classes: list<array{danceType: string, level: ?string}>}>}>  $days
     * @param  list<array{danceType: string, level: ?string}>  $classes
     */
    private function assertCell(array $days, int $weekday, string $hour, bool $occupied, array $classes): void
    {
        $cell = $this->cell($days, $weekday, $hour);

        $this->assertSame($occupied, $cell['occupied']);
        $this->assertSame($classes, $cell['classes']);
    }

    /**
     * @param  list<array{weekday: int, cells: list<array{hour: string, occupied: bool, classes: list<array{danceType: string, level: ?string}>}>}>  $days
     * @return array{hour: string, occupied: bool, classes: list<array{danceType: string, level: ?string}>}
     */
    private function cell(array $days, int $weekday, string $hour): array
    {
        foreach ($days as $day) {
            if ($day['weekday'] !== $weekday) {
                continue;
            }

            foreach ($day['cells'] as $cell) {
                if ($cell['hour'] === $hour) {
                    return $cell;
                }
            }
        }

        $this->fail("Missing cell for weekday {$weekday} at {$hour}.");
    }
}
