<?php

namespace App\Console\Commands;

use App\Models\CustomerRequestAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off migration of customer uploads from the public disk (reachable at
 * /storage/customer-requests/... without login) to the private disk that
 * only the authenticated admin download route reads from. Safe to re-run:
 * files already on the private disk are skipped, nothing is deleted until
 * the copy has been verified.
 */
class MoveAttachmentsToPrivateDisk extends Command
{
    protected $signature = 'attachments:move-to-private {--dry-run : Only report what would be moved}';

    protected $description = 'Move customer request attachments from the public disk to the private disk';

    public function handle(): int
    {
        $private = Storage::disk(CustomerRequestAttachment::DISK);
        $public = Storage::disk(CustomerRequestAttachment::LEGACY_DISK);
        $dryRun = (bool) $this->option('dry-run');

        $moved = 0;
        $skipped = 0;
        $missing = 0;
        $failed = 0;

        CustomerRequestAttachment::query()->orderBy('id')->chunkById(200, function ($attachments) use ($private, $public, $dryRun, &$moved, &$skipped, &$missing, &$failed): void {
            foreach ($attachments as $attachment) {
                $path = (string) $attachment->path;

                if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'customer-requests/')) {
                    $this->warn("#{$attachment->id}: unexpected path '{$path}' skipped");
                    $failed++;

                    continue;
                }

                if ($private->exists($path)) {
                    $skipped++;

                    continue;
                }

                if (! $public->exists($path)) {
                    $this->warn("#{$attachment->id}: file missing on both disks ({$path})");
                    $missing++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("would move {$path}");
                    $moved++;

                    continue;
                }

                $stream = $public->readStream($path);

                if ($stream === null || ! $private->writeStream($path, $stream)) {
                    $this->error("#{$attachment->id}: copy failed ({$path})");
                    $failed++;

                    continue;
                }

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($private->size($path) !== $public->size($path)) {
                    $this->error("#{$attachment->id}: size mismatch after copy, public copy kept ({$path})");
                    $failed++;

                    continue;
                }

                $public->delete($path);
                $moved++;
            }
        });

        $this->info(($dryRun ? 'Would move' : 'Moved') . " {$moved}, already private {$skipped}, missing {$missing}, failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
