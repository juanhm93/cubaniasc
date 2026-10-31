<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DanceType;
use App\Models\Level;
use App\Models\LevelContent;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToListContents;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LevelContentVideoLibraryTest extends TestCase
{
    use RefreshDatabase;

    private const MP4_HEADER = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

    private const LINKED_KEY = 'salsa-casino/avanzado-3/el_kiwi.mp4';

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.r2.bucket' => 'cubania-videos-test']);
        Storage::fake('r2');
    }

    public function test_guests_cannot_browse_the_library(): void
    {
        $figure = $this->figureInLevel();

        $this->getJson(route('api.level-contents.video.library', $figure))->assertUnauthorized();
        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])->assertUnauthorized();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesWithoutContentAccess(): array
    {
        return [
            'staff' => ['staff'],
            'admin_staff' => ['admin_staff'],
        ];
    }

    #[DataProvider('rolesWithoutContentAccess')]
    public function test_roles_without_content_access_cannot_use_the_library(string $role): void
    {
        $figure = $this->figureInLevel();
        $this->putMp4(self::LINKED_KEY);
        $this->actingAs($this->createUserWithRole($role));

        $this->getJson(route('api.level-contents.video.library', $figure))->assertForbidden();
        $this->getJson(route('api.level-contents.video.preview-url', [$figure, 'key' => self::LINKED_KEY]))->assertForbidden();
        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])->assertForbidden();
        $this->getJson(route('api.levels.video-library', $figure->level))->assertForbidden();

        $this->assertFalse($figure->fresh()->hasVideoFile());
    }

    public function test_library_opens_the_folder_of_the_figure_level(): void
    {
        $figure = $this->figureInLevel();
        $this->putMp4('salsa-casino/avanzado-3/medianoche.mp4');
        $this->putMp4(self::LINKED_KEY);
        Storage::disk('r2')->put('salsa-casino/avanzado-3/notas.txt', 'no es un video');
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.level-contents.video.library', $figure))
            ->assertOk()
            ->assertJsonPath('folder', 'salsa-casino/avanzado-3')
            ->assertJsonPath('folders', [])
            ->assertJsonCount(2, 'files')
            ->assertJsonPath('files.0.key', self::LINKED_KEY)
            ->assertJsonPath('files.0.name', 'el_kiwi.mp4')
            ->assertJsonPath('files.0.size_bytes', strlen(self::MP4_HEADER) + 64)
            ->assertJsonPath('files.0.linked_to', [])
            ->assertJsonPath('files.1.name', 'medianoche.mp4');
    }

    public function test_an_empty_folder_is_the_root_and_hides_the_app_uploads(): void
    {
        $figure = $this->figureInLevel();
        $this->putMp4(self::LINKED_KEY);
        $this->putMp4('eventos/rueda.mp4');
        $this->putMp4("level-contents/{$figure->id}/".Str::uuid()->toString().'.mp4');
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.level-contents.video.library', [$figure, 'folder' => '']))
            ->assertOk()
            ->assertJsonPath('folder', '')
            ->assertJsonPath('folders', ['eventos', 'salsa-casino'])
            ->assertJsonPath('files', []);
    }

    public function test_a_folder_that_does_not_exist_comes_back_empty(): void
    {
        $figure = $this->figureInLevel();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.level-contents.video.library', [$figure, 'folder' => 'no/existe']))
            ->assertOk()
            ->assertJsonPath('folders', [])
            ->assertJsonPath('files', []);
    }

    public function test_library_says_which_figures_use_each_file(): void
    {
        $figure = $this->figureInLevel();
        $other = LevelContent::factory()->for($figure->level)->create(['name' => 'El kiwi doble']);
        $this->putMp4(self::LINKED_KEY);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.link', $other), ['key' => self::LINKED_KEY])->assertOk();

        $this->getJson(route('api.level-contents.video.library', $figure))
            ->assertOk()
            ->assertJsonPath('files.0.linked_to', [['id' => $other->id, 'name' => 'El kiwi doble']]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foldersThatCannotBeOpened(): array
    {
        return [
            'traversal' => ['salsa-casino/../eventos'],
            'leading slash' => ['/salsa-casino'],
            'trailing slash' => ['salsa-casino/'],
            'backslash' => ['salsa-casino\\..\\eventos'],
            'control character' => ["salsa-casino\x07"],
            'app uploads' => ['level-contents'],
            'inside app uploads' => ['level-contents/1'],
        ];
    }

    #[DataProvider('foldersThatCannotBeOpened')]
    public function test_library_refuses_unsafe_folders(string $folder): void
    {
        $figure = $this->figureInLevel();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.level-contents.video.library', [$figure, 'folder' => $folder]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder']);
    }

    public function test_storage_outages_answer_service_unavailable(): void
    {
        $figure = $this->figureInLevel();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('listContents')->andThrow(UnableToListContents::atLocation('salsa-casino', false, new \RuntimeException));
        Storage::set('r2', $disk);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->getJson(route('api.level-contents.video.library', $figure))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de videos no está disponible.']);
    }

    public function test_preview_url_is_signed_inline_with_an_ascii_filename(): void
    {
        $this->freezeTime();
        $figure = $this->figureInLevel();
        $this->captureSignedUrlOptions();
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->getJson(route('api.level-contents.video.preview-url', [$figure, 'key' => 'salsa-casino/básico 1/Dámela.mp4']))
            ->assertOk()
            ->assertJsonPath('expires_at', now()->addMinutes(5)->toIso8601String());

        parse_str((string) parse_url($response->json('url'), PHP_URL_QUERY), $query);
        $this->assertSame('video/mp4', $query['type']);
        $this->assertSame('inline; filename=damela.mp4', $query['disposition']);
    }

    public function test_linking_makes_the_file_the_figure_video_without_touching_it(): void
    {
        $this->freezeTime();
        $figure = $this->figureInLevel(['video_url' => 'https://www.youtube.com/watch?v=s4DT0BFxDEk']);
        $this->putMp4(self::LINKED_KEY, 2048);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY, 'duration_seconds' => 74])
            ->assertOk()
            ->assertJsonPath('id', $figure->id)
            ->assertJsonPath('video_url', 'https://www.youtube.com/watch?v=s4DT0BFxDEk')
            ->assertJsonPath('video_file.available', true)
            ->assertJsonPath('video_file.linked', true)
            ->assertJsonPath('video_file.size_bytes', strlen(self::MP4_HEADER) + 2048)
            ->assertJsonPath('video_file.duration_seconds', 74)
            ->assertJsonPath('video_file.version', 1)
            ->assertJsonMissingPath('video_file_path');

        $this->assertDatabaseHas('level_contents', [
            'id' => $figure->id,
            'video_file_path' => self::LINKED_KEY,
            'video_file_uploaded_at' => now(),
        ]);
        Storage::disk('r2')->assertExists(self::LINKED_KEY);
    }

    public function test_linking_does_not_need_a_declared_content_type(): void
    {
        $figure = $this->figureInLevel();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, self::MP4_HEADER);
        rewind($stream);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->with(self::LINKED_KEY)->andReturnTrue();
        $disk->shouldReceive('size')->with(self::LINKED_KEY)->andReturn(4096);
        $disk->shouldReceive('readStream')->with(self::LINKED_KEY)->andReturn($stream);
        $disk->shouldNotReceive('mimeType');
        Storage::set('r2', $disk);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])
            ->assertOk()
            ->assertJsonPath('video_file.linked', true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function keysThatCannotBeLinked(): array
    {
        return [
            'not an mp4 name' => ['salsa-casino/avanzado-3/notas.txt'],
            'traversal' => ['salsa-casino/../eventos/rueda.mp4'],
            'leading slash' => ['/salsa-casino/el_kiwi.mp4'],
            'app uploads' => ['level-contents/1/4f2a6f7e-0c44-4f6e-9f2e-111111111111.mp4'],
        ];
    }

    #[DataProvider('keysThatCannotBeLinked')]
    public function test_link_refuses_keys_outside_the_library(string $key): void
    {
        $figure = $this->figureInLevel();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => $key])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);

        $this->assertFalse($figure->fresh()->hasVideoFile());
    }

    public function test_link_validates_the_payload(): void
    {
        $figure = $this->figureInLevel();
        $this->putMp4(self::LINKED_KEY);
        $this->actingAs($this->createUserWithRole('teacher'));
        $route = route('api.level-contents.video.link', $figure);

        $this->postJson($route, [])->assertUnprocessable()->assertJsonValidationErrors(['key']);
        $this->postJson($route, ['key' => ['nope']])->assertUnprocessable()->assertJsonValidationErrors(['key']);
        $this->postJson($route, ['key' => str_repeat('a', 252).'.mp4'])->assertUnprocessable()->assertJsonValidationErrors(['key']);

        foreach ([0, 3601, 'largo'] as $duration) {
            $this->postJson($route, ['key' => self::LINKED_KEY, 'duration_seconds' => $duration])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['duration_seconds']);
        }
    }

    public function test_link_fails_when_the_file_is_not_in_the_bucket(): void
    {
        $figure = $this->figureInLevel();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key' => 'No encontramos ese video en el bucket.']);
    }

    public function test_a_file_over_the_size_limit_is_rejected_and_kept(): void
    {
        config(['cubania.figure_videos.max_upload_bytes' => 1024 * 1024]);
        $figure = $this->figureInLevel();
        $this->putMp4(self::LINKED_KEY, 1024 * 1024);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key' => 'El video no puede pesar más de 1 MB.']);

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
        $this->assertFalse($figure->fresh()->hasVideoFile());
    }

    public function test_a_file_that_is_not_an_mp4_is_rejected_and_kept(): void
    {
        $figure = $this->figureInLevel();
        Storage::disk('r2')->put(self::LINKED_KEY, 'esto no es un video');
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key' => 'El archivo debe ser un video MP4.']);

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
        $this->assertFalse($figure->fresh()->hasVideoFile());
    }

    public function test_removing_a_linked_video_only_unlinks_it(): void
    {
        $figure = $this->figureWithLinkedVideo();

        $this->deleteJson(route('api.level-contents.video.destroy', $figure))
            ->assertOk()
            ->assertJsonPath('video_file.available', false)
            ->assertJsonPath('video_file.linked', false)
            ->assertJsonPath('video_file.version', 2);

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
    }

    public function test_replacing_a_linked_video_with_an_upload_keeps_the_linked_file(): void
    {
        $figure = $this->figureWithLinkedVideo();
        $uploadKey = "level-contents/{$figure->id}/".Str::uuid()->toString().'.mp4';
        $this->putMp4($uploadKey);

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $uploadKey])
            ->assertOk()
            ->assertJsonPath('video_file.linked', false)
            ->assertJsonPath('video_file.version', 2);

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
        Storage::disk('r2')->assertExists($uploadKey);
    }

    public function test_replacing_a_linked_video_with_another_keeps_both_files(): void
    {
        $figure = $this->figureWithLinkedVideo();
        $this->putMp4('salsa-casino/avanzado-3/medianoche.mp4');

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => 'salsa-casino/avanzado-3/medianoche.mp4'])
            ->assertOk()
            ->assertJsonPath('video_file.version', 2);

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
        Storage::disk('r2')->assertExists('salsa-casino/avanzado-3/medianoche.mp4');
    }

    public function test_replacing_an_upload_with_a_linked_file_deletes_the_upload(): void
    {
        $figure = $this->figureInLevel();
        $uploadKey = "level-contents/{$figure->id}/".Str::uuid()->toString().'.mp4';
        $this->putMp4($uploadKey);
        $this->putMp4(self::LINKED_KEY);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $uploadKey])->assertOk();
        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])
            ->assertOk()
            ->assertJsonPath('video_file.linked', true);

        Storage::disk('r2')->assertMissing($uploadKey);
        Storage::disk('r2')->assertExists(self::LINKED_KEY);
    }

    public function test_linking_the_same_file_twice_changes_nothing(): void
    {
        $figure = $this->figureWithLinkedVideo();

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])
            ->assertOk()
            ->assertJsonPath('video_file.version', 1);

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
    }

    public function test_linking_again_after_the_file_was_overwritten_updates_the_figure(): void
    {
        $figure = $this->figureWithLinkedVideo();
        $this->putMp4(self::LINKED_KEY, 4096);

        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])
            ->assertOk()
            ->assertJsonPath('video_file.size_bytes', strlen(self::MP4_HEADER) + 4096)
            ->assertJsonPath('video_file.version', 2);

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
    }

    public function test_the_same_file_can_back_two_figures(): void
    {
        $figure = $this->figureWithLinkedVideo();
        $other = LevelContent::factory()->for($figure->level)->create();

        $this->postJson(route('api.level-contents.video.link', $other), ['key' => self::LINKED_KEY])->assertOk();
        $this->deleteJson(route('api.level-contents.video.destroy', $figure))->assertOk();

        Storage::disk('r2')->assertExists(self::LINKED_KEY);
        $this->assertTrue($other->fresh()->hasVideoFile());
    }

    public function test_a_linked_figure_can_be_played_and_downloaded(): void
    {
        $figure = $this->figureWithLinkedVideo();
        $this->captureSignedUrlOptions();

        $response = $this->getJson(route('api.level-contents.video.stream-url', $figure))->assertOk();
        $this->assertStringStartsWith('https://r2.test/'.self::LINKED_KEY, $response->json('url'));

        $this->assertStringStartsWith(
            'https://r2.test/'.self::LINKED_KEY,
            (string) $this->get(route('api.level-contents.video.download', $figure))->assertRedirect()->headers->get('Location'),
        );
    }

    public function test_level_library_suggests_a_figure_for_each_file(): void
    {
        $level = $this->level();
        $bacardi = LevelContent::factory()->for($level)->create(['name' => 'Bacardi limón']);
        $medianoche = LevelContent::factory()->for($level)->create(['name' => 'Media noche']);
        $arco = LevelContent::factory()->for($level)->create(['name' => 'Arco triple']);
        LevelContent::factory()->for($level)->create(['name' => 'Fernando']);
        $this->putMp4('salsa-casino/avanzado-3/bacardi_limon_avanzado_3.mp4');
        $this->putMp4('salsa-casino/avanzado-3/medianoche.mp4');
        $this->putMp4('salsa-casino/avanzado-3/el_arco_triple.mp4');
        $this->putMp4('salsa-casino/avanzado-3/el_arco_triple_fluido.mp4');
        $this->putMp4('salsa-casino/avanzado-3/damela_por_las_manos.mp4');
        $this->actingAs($this->createUserWithRole('teacher'));

        $files = collect($this->getJson(route('api.levels.video-library', $level))
            ->assertOk()
            ->assertJsonPath('folder', 'salsa-casino/avanzado-3')
            ->assertJsonPath('broken_links', [])
            ->json('files'))->keyBy('name');

        $this->assertSame($bacardi->id, $files['bacardi_limon_avanzado_3.mp4']['suggested_figure_id']);
        $this->assertSame('exact', $files['bacardi_limon_avanzado_3.mp4']['match']);
        $this->assertSame($medianoche->id, $files['medianoche.mp4']['suggested_figure_id']);
        $this->assertSame('similar', $files['medianoche.mp4']['match']);
        $this->assertSame($arco->id, $files['el_arco_triple.mp4']['suggested_figure_id']);
        $this->assertNull($files['el_arco_triple_fluido.mp4']['suggested_figure_id']);
        $this->assertNull($files['el_arco_triple_fluido.mp4']['match']);
        $this->assertNull($files['damela_por_las_manos.mp4']['suggested_figure_id']);
    }

    public function test_level_library_skips_figures_with_a_video_and_files_already_linked(): void
    {
        $level = $this->level();
        LevelContent::factory()->for($level)->withVideoFile()->create(['name' => 'Medianoche']);
        $kiwi = LevelContent::factory()->for($level)->create(['name' => 'El kiwi']);
        $this->putMp4('salsa-casino/avanzado-3/medianoche.mp4');
        $this->putMp4(self::LINKED_KEY);
        $this->actingAs($this->createUserWithRole('teacher'));
        $this->postJson(route('api.level-contents.video.link', $kiwi), ['key' => self::LINKED_KEY])->assertOk();

        $files = collect($this->getJson(route('api.levels.video-library', $level))->assertOk()->json('files'))->keyBy('name');

        $this->assertNull($files['medianoche.mp4']['suggested_figure_id']);
        $this->assertNull($files['el_kiwi.mp4']['suggested_figure_id']);
        $this->assertSame($kiwi->id, $files['el_kiwi.mp4']['linked_to'][0]['id']);
    }

    public function test_level_library_reports_links_that_no_longer_match_the_bucket(): void
    {
        $level = $this->level();
        $moved = LevelContent::factory()->for($level)->create(['name' => 'Movida']);
        $overwritten = LevelContent::factory()->for($level)->create(['name' => 'Sobrescrita']);
        $healthy = LevelContent::factory()->for($level)->create(['name' => 'Sana']);
        $uploaded = LevelContent::factory()->for($level)->withVideoFile()->create(['name' => 'Subida']);
        $this->putMp4('otra-carpeta/movida.mp4');
        $this->putMp4('otra-carpeta/sobrescrita.mp4');
        $this->putMp4('otra-carpeta/sana.mp4');
        $this->actingAs($this->createUserWithRole('teacher'));

        foreach ([$moved, $overwritten, $healthy] as $figure) {
            $key = 'otra-carpeta/'.Str::lower($figure->name).'.mp4';
            $this->postJson(route('api.level-contents.video.link', $figure), ['key' => $key])->assertOk();
        }

        Storage::disk('r2')->delete('otra-carpeta/movida.mp4');
        $this->putMp4('otra-carpeta/sobrescrita.mp4', 4096);

        $brokenLinks = collect($this->getJson(route('api.levels.video-library', $level))
            ->assertOk()
            ->assertJsonCount(2, 'broken_links')
            ->json('broken_links'))->keyBy('figure_id');

        $this->assertSame([
            'figure_id' => $moved->id,
            'name' => 'Movida',
            'key' => 'otra-carpeta/movida.mp4',
            'reason' => 'missing',
        ], $brokenLinks[$moved->id]);
        $this->assertSame('size_changed', $brokenLinks[$overwritten->id]['reason']);
        $this->assertFalse($brokenLinks->has($healthy->id));
        $this->assertFalse($brokenLinks->has($uploaded->id));
    }

    private function level(): Level
    {
        $danceType = DanceType::factory()->create(['slug' => 'salsa-casino']);

        return Level::factory()->for($danceType)->create(['name' => 'Avanzado 3', 'slug' => 'avanzado_3']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function figureInLevel(array $attributes = []): LevelContent
    {
        return LevelContent::factory()->for($this->level())->create($attributes);
    }

    private function figureWithLinkedVideo(): LevelContent
    {
        $figure = $this->figureInLevel();
        $this->putMp4(self::LINKED_KEY);
        $this->actingAs($this->createUserWithRole('teacher'));
        $this->postJson(route('api.level-contents.video.link', $figure), ['key' => self::LINKED_KEY])->assertOk();

        return $figure->fresh();
    }

    private function putMp4(string $key, int $paddingBytes = 64): void
    {
        Storage::disk('r2')->put($key, self::MP4_HEADER.str_repeat("\0", $paddingBytes));
    }

    /**
     * The fake disk ignores the S3 response overrides, so expose them in the URL.
     */
    private function captureSignedUrlOptions(): void
    {
        Storage::disk('r2')->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiration, array $options): string => 'https://r2.test/'.$path.'?'.http_build_query([
                'expires' => $expiration->getTimestamp(),
                'type' => $options['ResponseContentType'] ?? null,
                'disposition' => $options['ResponseContentDisposition'] ?? null,
            ]),
        );
    }
}
