<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DanceType;
use App\Models\Level;
use App\Models\LevelContent;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteFile;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LevelContentVideoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Smallest content `finfo` recognises as MP4, so the fake disk reports `video/mp4`.
     */
    private const MP4_HEADER = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.r2.bucket' => 'cubania-videos-test']);
        Storage::fake('r2');
    }

    public function test_guests_cannot_request_an_upload_url(): void
    {
        $figure = LevelContent::factory()->create();

        $this->postJson(route('api.level-contents.video.upload-url', $figure), $this->uploadPayload())
            ->assertUnauthorized();
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
    public function test_roles_without_content_access_cannot_manage_figure_videos(string $role): void
    {
        $figure = $this->figureWithStoredVideo();
        $this->actingAs($this->createUserWithRole($role));

        $this->postJson(route('api.level-contents.video.upload-url', $figure), $this->uploadPayload())->assertForbidden();
        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $this->keyFor($figure)])->assertForbidden();
        $this->getJson(route('api.level-contents.video.stream-url', $figure))->assertForbidden();
        $this->get(route('api.level-contents.video.download', $figure))->assertForbidden();
        $this->deleteJson(route('api.level-contents.video.destroy', $figure))->assertForbidden();

        $this->assertTrue($figure->fresh()->hasVideoFile());
    }

    public function test_teacher_gets_a_presigned_upload_url_for_the_figure(): void
    {
        $this->freezeTime();
        $figure = LevelContent::factory()->create();
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->postJson(route('api.level-contents.video.upload-url', $figure), $this->uploadPayload())
            ->assertOk()
            ->assertJsonPath('headers', ['Content-Type' => 'video/mp4'])
            ->assertJsonPath('expires_at', now()->addMinutes(30)->toIso8601String());

        $this->assertMatchesRegularExpression(
            '#^level-contents/'.$figure->id.'/[0-9a-f-]{36}\.mp4$#',
            $response->json('key'),
        );
        $this->assertStringContainsString($response->json('key'), $response->json('url'));
    }

    public function test_upload_url_requires_an_mp4_within_the_size_limit(): void
    {
        config(['cubania.figure_videos.max_upload_bytes' => 10 * 1024 * 1024]);
        $figure = LevelContent::factory()->create();
        $this->actingAs($this->createUserWithRole('teacher'));
        $route = route('api.level-contents.video.upload-url', $figure);

        $this->postJson($route, ['content_type' => 'video/quicktime', 'size_bytes' => 1000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content_type' => 'El archivo debe ser un video MP4.']);

        $this->postJson($route, ['content_type' => 'video/mp4', 'size_bytes' => 10 * 1024 * 1024 + 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size_bytes' => 'El video no puede pesar más de 10 MB.']);

        $this->postJson($route, ['content_type' => 'video/mp4'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size_bytes']);

        $this->postJson($route, ['content_type' => 'video/mp4', 'size_bytes' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size_bytes']);
    }

    public function test_upload_url_is_unavailable_when_the_bucket_is_not_configured(): void
    {
        config(['filesystems.disks.r2.bucket' => null]);
        $figure = LevelContent::factory()->create();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.upload-url', $figure), $this->uploadPayload())
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de videos no está disponible.']);
    }

    public function test_confirming_an_upload_makes_it_the_figure_video(): void
    {
        $this->freezeTime();
        $figure = LevelContent::factory()->create(['video_url' => 'https://www.youtube.com/watch?v=s4DT0BFxDEk']);
        $key = $this->keyFor($figure);
        $this->putMp4($key, 2048);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $key, 'duration_seconds' => 74])
            ->assertOk()
            ->assertJsonPath('id', $figure->id)
            ->assertJsonPath('video_url', 'https://www.youtube.com/watch?v=s4DT0BFxDEk')
            ->assertJsonPath('video_file.available', true)
            ->assertJsonPath('video_file.size_bytes', strlen(self::MP4_HEADER) + 2048)
            ->assertJsonPath('video_file.duration_seconds', 74)
            ->assertJsonPath('video_file.version', 1)
            ->assertJsonMissingPath('video_file_path');

        $this->assertDatabaseHas('level_contents', [
            'id' => $figure->id,
            'video_url' => 'https://www.youtube.com/watch?v=s4DT0BFxDEk',
            'video_file_path' => $key,
            'video_file_size_bytes' => strlen(self::MP4_HEADER) + 2048,
            'video_file_duration_seconds' => 74,
            'video_file_version' => 1,
            'video_file_uploaded_at' => now(),
        ]);
    }

    public function test_replacing_the_video_bumps_the_version_and_deletes_the_old_object(): void
    {
        $figure = $this->figureWithStoredVideo();
        $oldKey = $figure->video_file_path;
        $newKey = $this->keyFor($figure);
        $this->putMp4($newKey);
        $this->actingAs($this->createUserWithRole('admin'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $newKey])
            ->assertOk()
            ->assertJsonPath('video_file.version', 2)
            ->assertJsonPath('video_file.duration_seconds', null);

        $this->assertSame($newKey, $figure->fresh()->video_file_path);
        Storage::disk('r2')->assertMissing($oldKey);
        Storage::disk('r2')->assertExists($newKey);
    }

    public function test_confirming_the_same_upload_twice_changes_nothing(): void
    {
        $figure = LevelContent::factory()->create();
        $key = $this->keyFor($figure);
        $this->putMp4($key);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $key])->assertOk();
        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $key])
            ->assertOk()
            ->assertJsonPath('video_file.version', 1);

        Storage::disk('r2')->assertExists($key);
    }

    public function test_confirm_rejects_keys_that_do_not_belong_to_the_figure(): void
    {
        $figure = LevelContent::factory()->create();
        $otherFigure = LevelContent::factory()->create();
        $otherKey = $this->keyFor($otherFigure);
        $this->putMp4($otherKey);
        $this->actingAs($this->createUserWithRole('teacher'));
        $route = route('api.level-contents.video.store', $figure);

        foreach ([$otherKey, "level-contents/{$figure->id}/video.mp4", "level-contents/{$figure->id}/../{$otherFigure->id}/x.mp4"] as $key) {
            $this->postJson($route, ['key' => $key])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['key' => 'El video subido no corresponde a esta figura.']);
        }

        $this->postJson($route, [])->assertUnprocessable()->assertJsonValidationErrors(['key']);
        $this->postJson($route, ['key' => ['nope']])->assertUnprocessable()->assertJsonValidationErrors(['key']);

        $this->assertFalse($figure->fresh()->hasVideoFile());
        Storage::disk('r2')->assertExists($otherKey);
    }

    public function test_confirm_validates_the_duration(): void
    {
        $figure = LevelContent::factory()->create();
        $key = $this->keyFor($figure);
        $this->putMp4($key);
        $this->actingAs($this->createUserWithRole('teacher'));

        foreach ([0, 3601, 'largo'] as $duration) {
            $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $key, 'duration_seconds' => $duration])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['duration_seconds']);
        }
    }

    public function test_confirm_fails_when_the_object_was_not_uploaded(): void
    {
        $figure = LevelContent::factory()->create();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $this->keyFor($figure)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key' => 'No encontramos el video subido. Vuelve a subirlo.']);

        $this->assertFalse($figure->fresh()->hasVideoFile());
    }

    public function test_confirm_deletes_an_object_over_the_size_limit(): void
    {
        config(['cubania.figure_videos.max_upload_bytes' => 1024 * 1024]);
        $figure = LevelContent::factory()->create();
        $key = $this->keyFor($figure);
        $this->putMp4($key, 1024 * 1024);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $key])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key' => 'El video no puede pesar más de 1 MB.']);

        Storage::disk('r2')->assertMissing($key);
        $this->assertFalse($figure->fresh()->hasVideoFile());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function objectsThatAreNotMp4(): array
    {
        return [
            'text declared as mp4' => ['esto no es un video'],
            'png' => ["\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR"],
        ];
    }

    #[DataProvider('objectsThatAreNotMp4')]
    public function test_confirm_deletes_an_object_that_is_not_an_mp4(string $contents): void
    {
        $figure = LevelContent::factory()->create();
        $key = $this->keyFor($figure);
        Storage::disk('r2')->put($key, $contents);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $key])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key' => 'El archivo debe ser un video MP4.']);

        Storage::disk('r2')->assertMissing($key);
        $this->assertFalse($figure->fresh()->hasVideoFile());
    }

    public function test_storage_outages_answer_service_unavailable(): void
    {
        $figure = LevelContent::factory()->create();
        $key = $this->keyFor($figure);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andThrow(UnableToCheckExistence::forLocation($key));
        Storage::set('r2', $disk);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $key])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'El almacenamiento de videos no está disponible.']);
    }

    public function test_a_failed_delete_of_the_replaced_object_is_reported_without_breaking_the_upload(): void
    {
        Exceptions::fake();
        $figure = LevelContent::factory()->withVideoFile()->create()->fresh();
        $newKey = $this->keyFor($figure);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, self::MP4_HEADER);
        rewind($stream);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->with($newKey)->andReturnTrue();
        $disk->shouldReceive('size')->with($newKey)->andReturn(4096);
        $disk->shouldReceive('mimeType')->with($newKey)->andReturn('video/mp4');
        $disk->shouldReceive('readStream')->with($newKey)->andReturn($stream);
        $disk->shouldReceive('delete')->with($figure->video_file_path)
            ->andThrow(UnableToDeleteFile::atLocation($figure->video_file_path));
        Storage::set('r2', $disk);
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->postJson(route('api.level-contents.video.store', $figure), ['key' => $newKey])
            ->assertOk()
            ->assertJsonPath('video_file.version', 2);

        $this->assertSame($newKey, $figure->fresh()->video_file_path);
        Exceptions::assertReported(UnableToDeleteFile::class);
    }

    public function test_removing_the_video_clears_it_keeps_youtube_and_deletes_the_object(): void
    {
        $figure = $this->figureWithStoredVideo(['video_url' => 'https://www.youtube.com/watch?v=s4DT0BFxDEk']);
        $key = $figure->video_file_path;
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->deleteJson(route('api.level-contents.video.destroy', $figure))
            ->assertOk()
            ->assertJsonPath('video_url', 'https://www.youtube.com/watch?v=s4DT0BFxDEk')
            ->assertJsonPath('video_file', [
                'available' => false,
                'linked' => false,
                'size_bytes' => null,
                'duration_seconds' => null,
                'version' => 2,
                'uploaded_at' => null,
            ]);

        $this->assertDatabaseHas('level_contents', [
            'id' => $figure->id,
            'video_file_path' => null,
            'video_file_size_bytes' => null,
            'video_file_version' => 2,
        ]);
        Storage::disk('r2')->assertMissing($key);
    }

    public function test_video_routes_return_not_found_when_the_figure_has_no_video(): void
    {
        $figure = LevelContent::factory()->create();
        $this->actingAs($this->createUserWithRole('teacher'));

        $this->deleteJson(route('api.level-contents.video.destroy', $figure))
            ->assertNotFound()
            ->assertJsonPath('message', 'Esta figura no tiene video.');
        $this->getJson(route('api.level-contents.video.stream-url', $figure))->assertNotFound();
        $this->get(route('api.level-contents.video.download', $figure))->assertNotFound();
    }

    public function test_stream_url_is_signed_inline_for_an_hour(): void
    {
        $this->freezeTime();
        $figure = $this->figureWithStoredVideo(['name' => 'Dile que no']);
        $this->captureSignedUrlOptions();
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->getJson(route('api.level-contents.video.stream-url', $figure))
            ->assertOk()
            ->assertJsonPath('expires_at', now()->addMinutes(60)->toIso8601String());

        $query = $this->signedUrlQuery($response->json('url'));
        $this->assertStringStartsWith('https://r2.test/'.$figure->video_file_path, $response->json('url'));
        $this->assertSame('video/mp4', $query['type']);
        $this->assertSame('inline; filename=dile-que-no-'.$figure->id.'-v1.mp4', $query['disposition']);
        $this->assertSame(now()->addMinutes(60)->getTimestamp(), (int) $query['expires']);
    }

    public function test_download_redirects_to_a_short_lived_attachment_url(): void
    {
        $this->freezeTime();
        $figure = $this->figureWithStoredVideo(['name' => 'Enchufla doble']);
        $this->captureSignedUrlOptions();
        $this->actingAs($this->createUserWithRole('teacher'));

        $response = $this->get(route('api.level-contents.video.download', $figure))->assertRedirect();

        $query = $this->signedUrlQuery($response->headers->get('Location'));
        $this->assertSame('attachment; filename=enchufla-doble-'.$figure->id.'-v1.mp4', $query['disposition']);
        $this->assertSame(now()->addMinutes(5)->getTimestamp(), (int) $query['expires']);
    }

    public function test_level_page_shares_whether_uploads_are_enabled(): void
    {
        $this->withoutVite();
        config(['cubania.figure_videos.max_upload_bytes' => 200 * 1024 * 1024]);
        $danceType = DanceType::factory()->create();
        $level = Level::factory()->for($danceType)->create();
        $this->actingAs($this->createUserWithRole('teacher'));
        $route = route('content.levels.show', ['danceType' => $danceType->id, 'level' => $level->id]);

        $this->get($route)->assertInertia(fn (Assert $page) => $page
            ->where('figureVideo.enabled', true)
            ->where('figureVideo.maxSizeBytes', 200 * 1024 * 1024));

        config(['filesystems.disks.r2.bucket' => '']);

        $this->get($route)->assertInertia(fn (Assert $page) => $page->where('figureVideo.enabled', false));
    }

    /**
     * @return array{content_type: string, size_bytes: int}
     */
    private function uploadPayload(): array
    {
        return ['content_type' => 'video/mp4', 'size_bytes' => 48_211_234];
    }

    private function keyFor(LevelContent $figure): string
    {
        return "level-contents/{$figure->id}/".Str::uuid()->toString().'.mp4';
    }

    private function putMp4(string $key, int $paddingBytes = 64): void
    {
        Storage::disk('r2')->put($key, self::MP4_HEADER.str_repeat("\0", $paddingBytes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function figureWithStoredVideo(array $attributes = []): LevelContent
    {
        $figure = LevelContent::factory()->withVideoFile()->create($attributes)->fresh();
        $this->putMp4($figure->video_file_path);

        return $figure;
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

    /**
     * @return array<string, string>
     */
    private function signedUrlQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
