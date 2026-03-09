<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Org;

use App\Actions\Organization\CreateNlOrgParseAttemptAction;
use App\Actions\Organization\FindNlOrgParseAttemptAction;
use App\Actions\Organization\MarkNlOrgParseAttemptAppliedAction;
use App\Http\Controllers\Controller;
use App\Support\NlOrg\NlOrgDiffApplier;
use App\Support\NlOrg\NlOrgParseResult;
use App\Support\NlOrg\NlOrgParserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NlOrgController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly NlOrgParserService $parser,
        private readonly NlOrgDiffApplier $applier,
        private readonly CreateNlOrgParseAttemptAction $createAttempt,
        private readonly FindNlOrgParseAttemptAction $findAttempt,
        private readonly MarkNlOrgParseAttemptAppliedAction $markAttemptApplied,
    ) {}

    public function parse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'input' => ['required', 'string', 'max:'.config('agent.nl_org.max_input_length', 2000)],
            'chat_history' => ['sometimes', 'array'],
            'chat_history.*.role' => ['required_with:chat_history', 'string', 'in:user,assistant'],
            'chat_history.*.content' => ['required_with:chat_history', 'string'],
        ]);

        $user = $request->user();
        $chatHistory = $validated['chat_history'] ?? [];

        $result = $this->parser->parse($user, $validated['input'], $chatHistory);

        $attempt = $this->createAttempt->execute($user->id, $validated['input'], $result);

        return response()->json([
            'parse_attempt_id' => $attempt->id,
            'changeset' => $result->changeset,
            'annotations' => $result->annotations,
            'confidence' => $result->confidence,
            'resolved_references' => $result->resolvedReferences,
            'error' => $result->error,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parse_attempt_id' => ['required', 'uuid', 'exists:nl_org_parse_attempts,id'],
        ]);

        $attempt = $this->findAttempt->execute($validated['parse_attempt_id']);

        $this->authorize('apply', $attempt);

        if ($attempt->applied_at !== null) {
            return response()->json([
                'error' => 'This parse attempt has already been applied.',
            ], 422);
        }

        $parsedData = $attempt->parsed_result;
        $result = new NlOrgParseResult(
            confidence: (float) ($parsedData['confidence'] ?? 0.0),
            changeset: $parsedData['changeset'] ?? [],
            annotations: $parsedData['annotations'] ?? [],
            resolvedReferences: $parsedData['resolved_references'] ?? [],
            error: $parsedData['error'] ?? null,
        );

        try {
            $this->applier->apply($request->user(), $result);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Failed to apply changes: '.$e->getMessage(),
            ], 422);
        }

        $attempt = $this->markAttemptApplied->execute($attempt);

        return response()->json([
            'success' => true,
            'applied_at' => $attempt->applied_at->toISOString(),
        ]);
    }
}
