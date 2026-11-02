<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RecommendedSong;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecommendedSongAudioFileSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_song_without_a_file_only_has_the_old_link(): void
    {
        $song = RecommendedSong::factory()->withoutAudioFile()->create()->fresh();

        $this->assertFalse($song->hasAudioFile());
        $this->assertSame([
            'available' => false,
            'format' => null,
            'size_bytes' => null,
            'duration_seconds' => null,
            'version' => 0,
        ], $song->audio_file);
    }

    public function test_audio_file_describes_the_file_without_its_key(): void
    {
        $song = RecommendedSong::factory()->create([
            'audio_file_path' => 'musica-salsa-casino/Carita de Pasaporte.WAV',
            'audio_file_size_bytes' => 51000000,
            'audio_file_duration_seconds' => 300,
            'audio_file_version' => 2,
        ])->fresh();

        $this->assertSame([
            'available' => true,
            'format' => 'wav',
            'size_bytes' => 51000000,
            'duration_seconds' => 300,
            'version' => 2,
        ], $song->audio_file);
        $this->assertArrayNotHasKey('audio_file_path', $song->toArray());
        $this->assertArrayNotHasKey('audio_or_link_url', $song->toArray());
    }

    public function test_the_external_link_is_optional(): void
    {
        $song = RecommendedSong::factory()->create(['audio_or_link_url' => null]);

        $this->assertDatabaseHas('recommended_songs', ['id' => $song->id, 'audio_or_link_url' => null]);
    }

    public function test_the_migration_can_be_retried_and_keeps_the_links(): void
    {
        $song = RecommendedSong::factory()->withoutAudioFile()->create();
        $link = $song->audio_or_link_url;

        $migration = $this->migration();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('recommended_songs', 'audio_file_path'));
        $this->assertSame($link, DB::table('recommended_songs')->value('audio_or_link_url'));
    }

    public function test_the_migration_rolls_back_with_songs_that_have_no_link(): void
    {
        RecommendedSong::factory()->create(['audio_or_link_url' => null]);

        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('recommended_songs', 'audio_file_path'));
        $this->assertSame('', DB::table('recommended_songs')->value('audio_or_link_url'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('recommended_songs', 'audio_file_version'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_31_053210_add_audio_file_columns_to_recommended_songs_table.php');
    }
}
