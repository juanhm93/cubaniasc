<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RecommendedSong;
use App\Services\Content\SongAudioStorage;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToListContents;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SongLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.r2.bucket' => 'cubania-videos-test']);
        Storage::fake('r2');
        Storage::disk('r2')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration, array $options): string => 'https://r2.test/'.rawurlencode($path).'?'.http_build_query([
                'expires' => $expiration->getTimestamp(),
                'type' => $options['ResponseContentType'] ?? null,
                'disposition' => $options['ResponseContentDisposition'] ?? null,
            ]),
        );
    }

    public function test_guests_cannot_use_the_library(): void
    {
        $song = RecommendedSong::factory()->create();

        $this->getJson(route('api.song-library.index'))->assertUnauthorized();
        $this->getJson(route('api.song-library.preview-url', ['key' => 'musica-salsa-casino/a.mp3']))->assertUnauthorized();
        $this->getJson(route('api.recommended-songs.stream-url', $song))->assertUnauthorized();
        $this->getJson(route('api.recommended-songs.download', $song))->assertUnauthorized();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesWithoutContentAccess(): array
    {
        return [
            'admin_staff' => ['admin_staff'],
            'staff' => ['staff'],
        ];
    }

    #[DataProvider('rolesWithoutContentAccess')]
    public function test_roles_without_content_access_cannot_use_the_library(string $role): void
    {
        $song = RecommendedSong::factory()->create();
        $this->actingAs($this->createUserWithRole($role));

        $this->getJson(route('api.song-library.index'))->assertForbidden();
        $this->getJson(route('api.song-library.preview-url', ['key' => 'musica-salsa-casino/a.mp3']))->assertForbidden();
        $this->getJson(route('api.recommended-songs.stream-url', $song))->assertForbidden();
        $this->getJson(route('api.recommended-songs.download', $song))->assertForbidden();
    }

    public function test_the_library_lists_the_songs_of_the_folder_and_which_are_in_the_catalog(): void
    {
        Storage::disk('r2')->put('musica-salsa-casino/10 - Diez.mp3', 'ID3-diez');
        Storage::disk('r2')->put('musica-salsa-casino/2 - Dos.WAV', 'RIFF-dos');
        Storage::disk('r2')->put('musica-salsa-casino/notas.txt', 'no es música');
        Storage::disk('r2')->put('salsa-casino/basico-1/guapea.mp3', 'otra carpeta');
        $song = RecommendedSong::factory()->create([
            'title' => 'Diez',
            'audio_file_path' => 'musica-salsa-casino/10 - Diez.mp3',
        ]);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.index'))
            ->assertOk()
            ->assertExactJson([
                'folder' => 'musica-salsa-casino',
                'files' => [
                    [
                        'key' => 'musica-salsa-casino/2 - Dos.WAV',
                        'name' => '2 - Dos.WAV',
                        'size_bytes' => 8,
                        'song' => null,
                    ],
                    [
                        'key' => 'musica-salsa-casino/10 - Diez.mp3',
                        'name' => '10 - Diez.mp3',
                        'size_bytes' => 8,
                        'song' => ['id' => $song->id, 'title' => 'Diez'],
                    ],
                ],
            ]);
    }

    public function test_the_file_of_a_deleted_song_shows_as_free_in_the_library(): void
    {
        Storage::disk('r2')->put('musica-salsa-casino/a.mp3', 'ID3');
        RecommendedSong::factory()->create(['audio_file_path' => 'musica-salsa-casino/a.mp3'])->delete();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.index'))->assertJsonPath('files.0.song', null);
    }

    public function test_the_library_is_empty_when_the_folder_does_not_exist(): void
    {
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.index'))->assertOk()->assertJsonPath('files', []);
    }

    public function test_the_folder_comes_from_the_configuration(): void
    {
        config(['cubania.songs.folder' => 'otra-musica/']);
        Storage::disk('r2')->put('otra-musica/a.mp3', 'ID3');
        Storage::disk('r2')->put('musica-salsa-casino/b.mp3', 'ID3');
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.index'))
            ->assertJsonPath('folder', 'otra-musica')
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.name', 'a.mp3');
    }

    public function test_storage_outages_answer_service_unavailable(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('listContents')->andThrow(UnableToListContents::atLocation('musica-salsa-casino', true, new RuntimeException));
        Storage::set('r2', $disk);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.index'))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de música no está disponible.']);
    }

    public function test_the_library_is_unavailable_without_a_bucket(): void
    {
        config(['filesystems.disks.r2.bucket' => null]);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.index'))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de música no está disponible.']);
    }

    public function test_preview_url_is_short_and_inline_with_an_ascii_filename(): void
    {
        $this->freezeTime();
        $key = "musica-salsa-casino/Dayme\u{0301} Arocena - La Rumba.mp3";
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->getJson(route('api.song-library.preview-url', ['key' => $key]))
            ->assertOk()
            ->assertJsonPath('expires_at', now()->addMinutes(5)->toIso8601String());

        $query = $this->signedUrlQuery($response->json('url'));

        $this->assertStringStartsWith('https://r2.test/'.rawurlencode($key), $response->json('url'));
        $this->assertSame('inline; filename=dayme-arocena-la-rumba.mp3', $query['disposition']);
        $this->assertSame('audio/mpeg', $query['type']);
        $this->assertSame(now()->addMinutes(5)->getTimestamp(), (int) $query['expires']);
    }

    public function test_a_wav_preview_is_served_as_wav(): void
    {
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->getJson(route('api.song-library.preview-url', ['key' => 'musica-salsa-casino/02 carita de pasaporte.wav']))
            ->assertOk();

        $this->assertSame('audio/wav', $this->signedUrlQuery($response->json('url'))['type']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function keysThatCannotBePreviewed(): array
    {
        return [
            'another folder' => ['salsa-casino/basico-1/guapea.mp3'],
            'a video' => ['musica-salsa-casino/video.mp4'],
            'climbing out' => ['musica-salsa-casino/../level-contents/1/a.mp3'],
            'backslashes' => ['musica-salsa-casino\\a.mp3'],
            'control characters' => ["musica-salsa-casino/a\x00.mp3"],
        ];
    }

    #[DataProvider('keysThatCannotBePreviewed')]
    public function test_only_files_of_the_folder_can_be_previewed(string $key): void
    {
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.preview-url', ['key' => $key]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_preview_requires_a_key(): void
    {
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.song-library.preview-url'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_staff_can_play_a_song(): void
    {
        $this->freezeTime();
        $song = RecommendedSong::factory()->create();
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->getJson(route('api.recommended-songs.stream-url', $song))
            ->assertOk()
            ->assertJsonPath('expires_at', now()->addMinutes(60)->toIso8601String());

        $this->assertStringStartsWith('inline', $this->signedUrlQuery($response->json('url'))['disposition']);
    }

    public function test_staff_download_redirects_to_a_short_attachment_url_with_a_readable_name(): void
    {
        $this->freezeTime();
        $song = RecommendedSong::factory()->create([
            'artist' => 'Los Van Van',
            'title' => 'Temba, tumba, timba',
            'audio_file_path' => 'musica-salsa-casino/01 temba.mp3',
        ]);
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->get(route('api.recommended-songs.download', $song))->assertRedirect();

        $query = $this->signedUrlQuery((string) $response->headers->get('Location'));

        $this->assertSame('attachment; filename=los-van-van-temba-tumba-timba.mp3', $query['disposition']);
        $this->assertSame(now()->addMinutes(5)->getTimestamp(), (int) $query['expires']);
    }

    public function test_a_song_without_a_file_has_nothing_to_play_or_download(): void
    {
        $song = RecommendedSong::factory()->withoutAudioFile()->create();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.recommended-songs.stream-url', $song))->assertNotFound();
        $this->getJson(route('api.recommended-songs.download', $song))->assertNotFound();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function fileSignatures(): array
    {
        return [
            'mp3 with ID3 tags' => ['a.mp3', "ID3\x04\x00\x00\x00\x00\x00\x00\x00\x00"],
            'mp3 starting with a frame' => ['a.mp3', "\xFF\xFB\x90\x64\x00\x00\x00\x00\x00\x00\x00\x00"],
            'wav' => ['a.wav', "RIFF\x24\x08\x00\x00WAVEfmt "],
            'uppercase extension' => ['a.MP3', "ID3\x04\x00\x00\x00\x00\x00\x00\x00\x00"],
        ];
    }

    #[DataProvider('fileSignatures')]
    public function test_real_audio_files_are_recognized_by_their_bytes(string $name, string $bytes): void
    {
        Storage::disk('r2')->put("musica-salsa-casino/{$name}", $bytes);

        $this->assertTrue(app(SongAudioStorage::class)->inspect("musica-salsa-casino/{$name}")['is_audio']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function fakeFiles(): array
    {
        return [
            'mp4 renamed to mp3' => ['a.mp3', "\x00\x00\x00\x20ftypisom\x00\x00\x00\x00"],
            'wav renamed to mp3' => ['a.mp3', "RIFF\x24\x08\x00\x00WAVEfmt "],
            'mp3 renamed to wav' => ['a.wav', "ID3\x04\x00\x00\x00\x00\x00\x00\x00\x00"],
            'another RIFF format' => ['a.wav', "RIFF\x24\x08\x00\x00WEBPVP8 "],
            'too short' => ['a.mp3', 'ID3'],
            'empty' => ['a.mp3', ''],
        ];
    }

    #[DataProvider('fakeFiles')]
    public function test_files_that_are_not_what_they_say_are_refused(string $name, string $bytes): void
    {
        Storage::disk('r2')->put("musica-salsa-casino/{$name}", $bytes);

        $this->assertFalse(app(SongAudioStorage::class)->inspect("musica-salsa-casino/{$name}")['is_audio']);
    }

    public function test_keys_with_accents_in_either_unicode_form_belong_to_the_library(): void
    {
        $storage = app(SongAudioStorage::class);

        $this->assertTrue($storage->isLibraryKey("musica-salsa-casino/Dayme\u{0301} Arocena.mp3"));
        $this->assertTrue($storage->isLibraryKey('musica-salsa-casino/Daymé Arocena.mp3'));
        $this->assertTrue($storage->isLibraryKey('musica-salsa-casino/Conteo musical 1-2-3 .. salsa timing.mp3'));
    }

    /**
     * @return array<string, string>
     */
    private function signedUrlQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
