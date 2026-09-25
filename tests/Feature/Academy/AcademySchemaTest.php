<?php

namespace Tests\Feature\Academy;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\DanceType;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\LevelContent;
use Database\Seeders\LevelCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_enrollment_for_same_course_and_student_is_rejected(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->expectException(QueryException::class);

        Enrollment::query()->create([
            'course_id' => $enrollment->course_id,
            'student_id' => $enrollment->student_id,
            'enrolled_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_duplicate_attendance_for_same_session_and_student_is_rejected(): void
    {
        $attendance = Attendance::factory()->create([
            'status' => AttendanceStatus::Present,
        ]);

        $this->expectException(QueryException::class);

        Attendance::query()->create([
            'course_session_id' => $attendance->course_session_id,
            'student_id' => $attendance->student_id,
            'status' => AttendanceStatus::Absent->value,
        ]);
    }

    public function test_level_catalog_seeder_creates_salsa_casino_levels_and_figures(): void
    {
        $this->seed(LevelCatalogSeeder::class);

        $salsa = DanceType::query()->where('slug', 'salsa-casino')->firstOrFail();

        $this->assertSame(15, Level::query()->where('dance_type_id', $salsa->id)->count());

        $basico1 = Level::query()->where('slug', 'basico_1')->firstOrFail();
        $master2 = Level::query()->where('slug', 'master_2')->firstOrFail();

        $this->assertSame($salsa->id, $basico1->dance_type_id);
        $this->assertSame('Máster 2', $master2->name);

        $names = $basico1->levelContents()->orderBy('sort_order')->pluck('name')->all();

        $this->assertSame('Ángulos', $names[0]);
        $this->assertSame('Exhíbela (Doble y N…)', $names[array_key_last($names)]);
        $this->assertCount(13, $names);

        $this->assertSame(
            246,
            LevelContent::query()
                ->whereIn('level_id', Level::query()->where('dance_type_id', $salsa->id)->select('id'))
                ->count()
        );
    }
}
