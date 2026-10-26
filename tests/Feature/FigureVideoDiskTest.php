<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class FigureVideoDiskTest extends TestCase
{
    public function test_r2_disk_is_private_with_path_style_urls(): void
    {
        $disk = config('filesystems.disks.r2');

        $this->assertSame('s3', $disk['driver']);
        $this->assertSame('auto', $disk['region']);
        $this->assertTrue($disk['use_path_style_endpoint']);
        $this->assertSame('private', $disk['visibility']);
        $this->assertTrue($disk['throw']);
        $this->assertSame('r2', config('cubania.figure_videos.disk'));
        $this->assertSame(200 * 1024 * 1024, config('cubania.figure_videos.max_upload_bytes'));
    }

    public function test_tests_never_receive_real_r2_credentials(): void
    {
        $disk = config('filesystems.disks.r2');

        $this->assertEmpty($disk['key']);
        $this->assertEmpty($disk['secret']);
        $this->assertEmpty($disk['bucket']);
        $this->assertEmpty($disk['endpoint']);
    }

    /**
     * Only `host` is signed, so the browser can send its own `Content-Type: video/mp4`
     * (R2 stores it on the object) and no checksum the browser cannot compute is required.
     *
     * Runs in its own process: the S3 client keeps the SDK's API model in memory for the
     * rest of the process, which would push the suite past PHP's default memory limit.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_presigned_upload_url_only_signs_the_host_and_has_no_checksum(): void
    {
        $upload = $this->r2Disk()->temporaryUploadUrl(
            'level-contents/1/video.mp4',
            now()->addMinutes(30),
            ['ContentType' => 'video/mp4'],
        );

        $url = parse_url($upload['url']);
        parse_str($url['query'], $query);

        $this->assertSame('https', $url['scheme']);
        $this->assertSame('account.r2.cloudflarestorage.com', $url['host']);
        $this->assertSame('/test-bucket/level-contents/1/video.mp4', $url['path']);
        $this->assertEqualsWithDelta(1800, (int) $query['X-Amz-Expires'], 5);
        $this->assertSame('host', $query['X-Amz-SignedHeaders']);

        foreach ([...array_keys($query), ...array_keys($upload['headers'])] as $name) {
            $this->assertStringNotContainsStringIgnoringCase('checksum', $name);
        }
    }

    private function r2Disk(): FilesystemAdapter
    {
        return Storage::build([
            ...config('filesystems.disks.r2'),
            'key' => 'test-key',
            'secret' => 'test-secret',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://account.r2.cloudflarestorage.com',
        ]);
    }
}
