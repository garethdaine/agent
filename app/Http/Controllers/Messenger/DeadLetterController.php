<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Messenger\Reliability\DeadLetterManager;
use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for dead-letter queue management.
 *
 * Provides views for listing, inspecting, and retrying failed messages
 * that have been moved to the dead-letter queue.
 */
class DeadLetterController extends Controller
{
    public function __construct(
        private readonly DeadLetterManager $manager
    ) {}

    /**
     * Display paginated list of dead letters.
     *
     * GET /messenger/dead-letters
     *
     * Supports filtering by connector via ?connector={id} query param.
     */
    public function index(Request $request): InertiaResponse
    {
        $deadLetters = MessengerDeadLetter::query()
            ->when(
                $request->query('connector'),
                fn ($query, $connectorId) => $query->where('connector_account_id', $connectorId)
            )
            ->with('connectorAccount')
            ->orderByDesc('failed_at')
            ->paginate(25)
            ->withQueryString();

        $connectors = ConnectorAccount::query()
            ->orderBy('name')
            ->get(['id', 'name', 'provider'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'provider' => $c->provider,
            ]);

        return Inertia::render('Messenger/DeadLetters/Index', [
            'deadLetters' => $deadLetters,
            'connectors' => $connectors,
            'currentConnector' => $request->query('connector'),
        ]);
    }

    /**
     * Display full details for a single dead letter.
     *
     * GET /messenger/dead-letters/{id}
     */
    public function show(int $id): InertiaResponse
    {
        $deadLetter = MessengerDeadLetter::with('connectorAccount')
            ->findOrFail($id);

        return Inertia::render('Messenger/DeadLetters/Show', [
            'deadLetter' => $deadLetter,
        ]);
    }

    /**
     * Retry a single dead letter message.
     *
     * POST /messenger/dead-letters/{id}/retry
     *
     * Redirects back to index with success/error flash message.
     */
    public function retry(int $id): RedirectResponse
    {
        $success = $this->manager->retry($id);

        return redirect()
            ->route('messenger.dead-letters.index')
            ->with(
                $success ? 'success' : 'error',
                $success ? 'Message retried successfully' : 'Retry failed'
            );
    }

    /**
     * Retry multiple dead letter messages in bulk.
     *
     * POST /messenger/dead-letters/retry-bulk
     *
     * Expects JSON body: { "ids": [1, 2, 3] }
     * Returns JSON response with results for each ID.
     */
    public function retryBulk(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $results = $this->manager->retryBulk($request->input('ids'));

        return response()->json($results);
    }

    /**
     * Delete a dead letter record.
     *
     * DELETE /messenger/dead-letters/{id}
     *
     * Redirects back to index with success flash message.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->manager->delete($id);

        return redirect()
            ->route('messenger.dead-letters.index')
            ->with('success', 'Dead letter deleted');
    }
}
