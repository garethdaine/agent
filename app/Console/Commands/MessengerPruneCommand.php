<?php

namespace App\Console\Commands;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\MessengerEventDeduplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MessengerPruneCommand extends Command
{
    protected $signature = 'messenger:prune
        {--deduplication : Delete deduplication records older than 24 hours}
        {--sessions : Delete inactive sessions older than 30 days}
        {--messages : Delete messages older than retention period}
        {--attachments : Delete expired attachments and their files}
        {--dry-run : Preview what would be deleted without making changes}
        {--chunk=500 : Chunk size per deletion batch}';

    protected $description = 'Prune messenger data to maintain system hygiene';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $totalDeleted = 0;
        $operations = [];

        if ($this->option('deduplication')) {
            $operations['deduplication'] = $this->pruneDeduplication($dryRun, $chunkSize);
            $totalDeleted += $operations['deduplication'];
        }

        if ($this->option('sessions')) {
            $operations['sessions'] = $this->pruneSessions($dryRun, $chunkSize);
            $totalDeleted += $operations['sessions'];
        }

        if ($this->option('messages')) {
            $operations['messages'] = $this->pruneMessages($dryRun, $chunkSize);
            $totalDeleted += $operations['messages'];
        }

        if ($this->option('attachments')) {
            $operations['attachments'] = $this->pruneAttachments($dryRun, $chunkSize);
            $totalDeleted += $operations['attachments'];
        }

        if (empty($operations)) {
            $this->warn('No prune options specified. Use --deduplication, --sessions, --messages, or --attachments.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Messenger prune completed%s. Total: %d records.',
            $dryRun ? ' (dry run)' : '',
            $totalDeleted
        ));

        return self::SUCCESS;
    }

    private function pruneDeduplication(bool $dryRun, int $chunkSize): int
    {
        $threshold = now()->subHours(24);
        $query = MessengerEventDeduplication::where('expires_at', '<', $threshold);

        if ($dryRun) {
            $count = $query->count();
            $this->info("Would prune {$count} deduplication records older than 24 hours.");

            return $count;
        }

        $deleted = 0;
        do {
            $chunk = $query->limit($chunkSize)->delete();
            $deleted += $chunk;
        } while ($chunk === $chunkSize);

        $this->info("Pruned {$deleted} deduplication records.");

        return $deleted;
    }

    private function pruneSessions(bool $dryRun, int $chunkSize): int
    {
        $threshold = now()->subDays(30);
        $query = ChatSession::where('status', ChatSession::STATUS_ARCHIVED)
            ->where('updated_at', '<', $threshold);

        if ($dryRun) {
            $count = $query->count();
            $this->info("Would prune {$count} inactive sessions older than 30 days.");

            return $count;
        }

        $deleted = 0;
        do {
            $sessions = $query->limit($chunkSize)->pluck('id');
            if ($sessions->isEmpty()) {
                break;
            }
            $chunk = ChatSession::whereIn('id', $sessions)->delete();
            $deleted += $chunk;
        } while ($sessions->count() === $chunkSize);

        $this->info("Pruned {$deleted} sessions.");

        return $deleted;
    }

    private function pruneMessages(bool $dryRun, int $chunkSize): int
    {
        $retentionDays = (int) config('messenger.attachments.retention_days', 90);
        $threshold = now()->subDays($retentionDays);

        $query = ChatMessage::where('created_at', '<', $threshold);

        if ($dryRun) {
            $count = $query->count();
            $this->info("Would prune {$count} messages older than {$retentionDays} days.");

            return $count;
        }

        $deleted = 0;
        do {
            $messages = $query->limit($chunkSize)->pluck('id');
            if ($messages->isEmpty()) {
                break;
            }
            $chunk = ChatMessage::whereIn('id', $messages)->delete();
            $deleted += $chunk;
        } while ($messages->count() === $chunkSize);

        $this->info("Pruned {$deleted} messages.");

        return $deleted;
    }

    private function pruneAttachments(bool $dryRun, int $chunkSize): int
    {
        $query = ChatAttachment::where('expires_at', '<', now());

        if ($dryRun) {
            $count = $query->count();
            $this->info("Would prune {$count} expired attachments.");

            return $count;
        }

        $deleted = 0;
        $filesDeleted = 0;
        $disk = Storage::disk(config('messenger.attachment_config.storage_disk', 'local'));

        do {
            $attachments = $query->limit($chunkSize)->get();
            if ($attachments->isEmpty()) {
                break;
            }

            foreach ($attachments as $attachment) {
                if ($attachment->storage_path && $disk->exists($attachment->storage_path)) {
                    $disk->delete($attachment->storage_path);
                    $filesDeleted++;
                }
            }

            $chunk = ChatAttachment::whereIn('id', $attachments->pluck('id'))->delete();
            $deleted += $chunk;
        } while ($attachments->count() === $chunkSize);

        $this->info("Pruned {$deleted} attachments ({$filesDeleted} files deleted).");

        return $deleted;
    }
}
