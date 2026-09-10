<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Reaps abandoned CSV uploads.
 *
 * The importer deletes the file once it finishes, and the preview step deletes
 * it when parsing fails — but an upload that previews cleanly and is then never
 * imported is left behind forever. At 200MB per file that fills a disk quickly,
 * and there is no per-user quota in front of it.
 */
class PruneTempUploads extends Command
{
    protected $signature = 'contacts:prune-temp-uploads {--hours=2 : Delete uploads older than this}';

    protected $description = 'Delete abandoned CSV uploads from storage/app/private/csv_temp';

    public function handle(): int
    {
        $disk = Storage::disk('local');

        if (! $disk->exists('csv_temp')) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;
        $freed = 0;

        foreach ($disk->files('csv_temp') as $file) {
            if ($disk->lastModified($file) > $cutoff) {
                continue;
            }

            $freed += $disk->size($file);
            $disk->delete($file);
            $deleted++;
        }

        $this->info(sprintf('Pruned %d abandoned upload(s), freeing %s.', $deleted, $this->humanBytes($freed)));

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return $bytes.' B';
    }
}
