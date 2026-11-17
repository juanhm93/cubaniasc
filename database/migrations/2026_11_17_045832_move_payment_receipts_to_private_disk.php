<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Payment receipts used to be stored on the public disk, reachable by anyone with
 * the URL. This moves the existing files to the private disk. There is no SSH on the
 * hosting, so the move runs as a migration from the owner maintenance page. It is
 * idempotent: files already moved, or missing, are skipped.
 */
return new class extends Migration
{
    private const PUBLIC_DISK = 'public';

    private const PRIVATE_DISK = 'local';

    public function up(): void
    {
        $this->moveReceipts(from: self::PUBLIC_DISK, to: self::PRIVATE_DISK);
    }

    public function down(): void
    {
        $this->moveReceipts(from: self::PRIVATE_DISK, to: self::PUBLIC_DISK);
    }

    private function moveReceipts(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        DB::table('payments')
            ->whereNotNull('receipt_path')
            ->where('receipt_path', '!=', '')
            ->orderBy('id')
            ->pluck('receipt_path')
            ->unique()
            ->each(function (string $path) use ($source, $target): void {
                if (! $source->exists($path) || $target->exists($path)) {
                    return;
                }

                $stream = $source->readStream($path);

                if ($stream === null) {
                    return;
                }

                $target->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($target->exists($path)) {
                    $source->delete($path);
                }
            });
    }
};
