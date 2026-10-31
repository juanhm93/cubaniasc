<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LevelContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LevelContentVideoFileSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = [
        'video_file_path',
        'video_file_size_bytes',
        'video_file_duration_seconds',
        'video_file_version',
        'video_file_uploaded_at',
    ];

    public function test_new_figures_have_no_video_file_and_version_zero(): void
    {
        $figure = LevelContent::factory()->create(['video_url' => 'https://www.youtube.com/watch?v=s4DT0BFxDEk']);

        $this->assertDatabaseHas('level_contents', [
            'id' => $figure->id,
            'video_url' => 'https://www.youtube.com/watch?v=s4DT0BFxDEk',
            'video_file_path' => null,
            'video_file_size_bytes' => null,
            'video_file_duration_seconds' => null,
            'video_file_version' => 0,
            'video_file_uploaded_at' => null,
        ]);

        $this->assertFalse($figure->fresh()->hasVideoFile());
        $this->assertSame([
            'available' => false,
            'linked' => false,
            'size_bytes' => null,
            'duration_seconds' => null,
            'version' => 0,
            'uploaded_at' => null,
        ], $figure->fresh()->video_file);
    }

    public function test_video_file_describes_the_uploaded_mp4(): void
    {
        $figure = LevelContent::factory()->withVideoFile()->create([
            'video_file_size_bytes' => 48211234,
            'video_file_duration_seconds' => 74,
            'video_file_version' => 3,
        ])->fresh();

        $this->assertTrue($figure->hasVideoFile());
        $this->assertTrue($figure->video_file['available']);
        $this->assertSame(48211234, $figure->video_file['size_bytes']);
        $this->assertSame(74, $figure->video_file['duration_seconds']);
        $this->assertSame(3, $figure->video_file['version']);
        $this->assertNotNull($figure->video_file['uploaded_at']);
    }

    public function test_removed_video_file_keeps_its_version(): void
    {
        $figure = LevelContent::factory()->withVideoFile()->create(['video_file_version' => 4]);

        $figure->forceFill([
            'video_file_path' => null,
            'video_file_size_bytes' => null,
            'video_file_duration_seconds' => null,
            'video_file_uploaded_at' => null,
        ])->save();

        $this->assertSame([
            'available' => false,
            'linked' => false,
            'size_bytes' => null,
            'duration_seconds' => null,
            'version' => 4,
            'uploaded_at' => null,
        ], $figure->fresh()->video_file);
    }

    public function test_serialization_includes_video_file_but_never_the_object_key(): void
    {
        $serialized = LevelContent::factory()->withVideoFile()->create()->fresh()->toArray();

        $this->assertArrayHasKey('video_file', $serialized);
        $this->assertArrayNotHasKey('video_file_path', $serialized);
    }

    public function test_video_file_columns_are_not_mass_assignable(): void
    {
        $figure = LevelContent::factory()->create();

        $figure->update(['name' => 'Enchufla', 'video_file_path' => 'level-contents/1/injected.mp4', 'video_file_version' => 99]);

        $this->assertDatabaseHas('level_contents', [
            'id' => $figure->id,
            'name' => 'Enchufla',
            'video_file_path' => null,
            'video_file_version' => 0,
        ]);
    }

    public function test_factory_video_file_key_uses_the_figure_id(): void
    {
        $figure = LevelContent::factory()->withVideoFile()->create();

        $this->assertMatchesRegularExpression(
            '#^level-contents/'.$figure->id.'/[0-9a-f-]{36}\.mp4$#',
            $figure->fresh()->getAttributes()['video_file_path'],
        );
    }

    public function test_migration_can_run_again_on_a_migrated_database(): void
    {
        $this->videoFileMigration()->up();

        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('level_contents', $column), "Missing column {$column}.");
        }
    }

    public function test_migration_resumes_after_a_partial_run(): void
    {
        $figure = LevelContent::factory()->create();

        Schema::table('level_contents', function (Blueprint $table) {
            $table->dropColumn(['video_file_version', 'video_file_uploaded_at']);
        });

        $this->videoFileMigration()->up();

        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('level_contents', $column), "Missing column {$column}.");
        }

        $this->assertDatabaseHas('level_contents', ['id' => $figure->id, 'video_file_version' => 0]);
    }

    public function test_migration_rolls_back_only_its_own_columns(): void
    {
        $migration = $this->videoFileMigration();

        $migration->down();
        $migration->down();

        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('level_contents', $column), "Column {$column} was not dropped.");
        }

        $this->assertTrue(Schema::hasColumn('level_contents', 'video_url'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('level_contents', 'video_file_path'));
    }

    private function videoFileMigration(): Migration
    {
        return require database_path('migrations/2026_10_26_111701_add_video_file_columns_to_level_contents_table.php');
    }
}
