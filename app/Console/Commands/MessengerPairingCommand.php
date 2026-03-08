<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessengerIdentityLink;
use Illuminate\Console\Command;

class MessengerPairingCommand extends Command
{
    protected $signature = 'messenger:pairing
                            {action=list : list|approve|revoke}
                            {id? : Identity link UUID (for approve/revoke)}
                            {--status=pending : Filter by status (list only)}
                            {--json : Output as JSON}';

    protected $description = 'Manage messenger identity pairing (list pending, approve, revoke)';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'list' => $this->listPairings(),
            'approve' => $this->approvePairing(),
            'revoke' => $this->revokePairing(),
            default => $this->invalidAction(),
        };
    }

    private function listPairings(): int
    {
        $status = $this->option('status');
        $query = MessengerIdentityLink::query()
            ->with('connectorAccount:id,name,provider')
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $links = $query->get();

        if ($this->option('json')) {
            $this->line(json_encode(['pairings' => $links->toArray()], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($links->isEmpty()) {
            $this->info("No {$status} pairings found.");

            return self::SUCCESS;
        }

        $rows = $links->map(fn (MessengerIdentityLink $link) => [
            $link->id,
            $link->connectorAccount->provider ?? '—',
            $link->provider_username ?? $link->provider_user_id,
            $link->status,
            $link->created_at?->diffForHumans() ?? '—',
        ]);

        $this->table(['ID', 'Provider', 'User', 'Status', 'Created'], $rows->toArray());

        return self::SUCCESS;
    }

    private function approvePairing(): int
    {
        $link = $this->findLink();
        if (! $link) {
            return self::FAILURE;
        }

        if (! $link->isPending()) {
            $this->error("Cannot approve: link is {$link->status}.");

            return self::FAILURE;
        }

        $link->approve();
        $this->info("Approved pairing {$link->id}.");

        return self::SUCCESS;
    }

    private function revokePairing(): int
    {
        $link = $this->findLink();
        if (! $link) {
            return self::FAILURE;
        }

        if ($link->isRevoked()) {
            $this->error('Already revoked.');

            return self::FAILURE;
        }

        $link->revoke();
        $this->info("Revoked pairing {$link->id}.");

        return self::SUCCESS;
    }

    private function findLink(): ?MessengerIdentityLink
    {
        $id = $this->argument('id');
        if (! $id) {
            $this->error('ID argument is required for approve/revoke.');

            return null;
        }

        $link = MessengerIdentityLink::find($id);
        if (! $link) {
            $this->error("Pairing {$id} not found.");

            return null;
        }

        return $link;
    }

    private function invalidAction(): int
    {
        $this->error('Invalid action. Use: list, approve, or revoke.');

        return self::FAILURE;
    }
}
