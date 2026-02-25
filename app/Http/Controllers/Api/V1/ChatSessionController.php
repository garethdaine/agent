<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatActionResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatSessionResource;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ChatSession::query()
            ->where('user_id', $request->user()->id);

        $status = $request->string('status')->toString();
        if (in_array($status, [ChatSession::STATUS_ACTIVE, ChatSession::STATUS_ARCHIVED], true)) {
            $query->where('status', $status);
        }

        $provider = $request->string('provider')->toString();
        if ($provider !== '') {
            $query->where('provider', $provider);
        }

        $connectorId = $request->string('connector_account_id')->toString();
        if ($connectorId !== '') {
            $query->where('connector_account_id', $connectorId);
        }

        $sort = $request->string('sort', 'updated_at')->toString();
        $dir = strtolower($request->string('dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, ['created_at', 'updated_at'], true)) {
            $sort = 'updated_at';
        }

        $query->orderBy($sort, $dir);

        if ($request->boolean('with_connector')) {
            $query->with('connectorAccount');
        }

        if ($request->boolean('with_message_count')) {
            $query->withCount('messages');
        }

        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $sessions = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => ChatSessionResource::collection($sessions->items()),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ],
            'links' => [
                'first' => $sessions->url(1),
                'last' => $sessions->url($sessions->lastPage()),
                'prev' => $sessions->previousPageUrl(),
                'next' => $sessions->nextPageUrl(),
            ],
            'filters' => [
                'status' => $request->input('status'),
                'provider' => $request->input('provider'),
                'connector_account_id' => $request->input('connector_account_id'),
            ],
            'sort' => [
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $query = ChatSession::query()
            ->where('user_id', $request->user()->id);

        if ($request->boolean('with_connector')) {
            $query->with('connectorAccount');
        }

        $session = $query->findOrFail($id);

        return response()->json([
            'data' => new ChatSessionResource($session),
        ]);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $query = $session->messages()->newQuery();

        $direction = $request->string('direction')->toString();
        if (in_array($direction, ['inbound', 'outbound'], true)) {
            $query->where('direction', $direction);
        }

        $sort = $request->string('sort', 'created_at')->toString();
        $dir = strtolower($request->string('dir', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';

        if ($sort !== 'created_at') {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $dir);

        if ($request->boolean('with_actions')) {
            $query->with('actions');
        }

        if ($request->boolean('with_attachments')) {
            $query->with('attachments');
        }

        $perPage = min(100, max(1, $request->integer('per_page', 50)));
        $messages = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => ChatMessageResource::collection($messages->items()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
                'session_id' => $session->id,
            ],
            'links' => [
                'first' => $messages->url(1),
                'last' => $messages->url($messages->lastPage()),
                'prev' => $messages->previousPageUrl(),
                'next' => $messages->nextPageUrl(),
            ],
        ]);
    }

    public function actions(Request $request, string $id): JsonResponse
    {
        $session = ChatSession::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $messageIds = $session->messages()->pluck('id');

        $query = \App\Models\ChatAction::query()
            ->whereIn('chat_message_id', $messageIds);

        $status = $request->string('status')->toString();
        $validStatuses = [
            \App\Models\ChatAction::STATUS_PENDING,
            \App\Models\ChatAction::STATUS_EXECUTING,
            \App\Models\ChatAction::STATUS_COMPLETED,
            \App\Models\ChatAction::STATUS_FAILED,
            \App\Models\ChatAction::STATUS_CANCELLED,
            \App\Models\ChatAction::STATUS_TIMEOUT,
        ];
        if (in_array($status, $validStatuses, true)) {
            $query->where('status', $status);
        }

        $actionType = $request->string('action_type')->toString();
        if ($actionType !== '') {
            $query->where('action_type', $actionType);
        }

        $sort = $request->string('sort', 'created_at')->toString();
        $dir = strtolower($request->string('dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        if ($sort !== 'created_at') {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $dir);

        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $actions = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => ChatActionResource::collection($actions->items()),
            'meta' => [
                'current_page' => $actions->currentPage(),
                'per_page' => $actions->perPage(),
                'total' => $actions->total(),
                'last_page' => $actions->lastPage(),
                'session_id' => $session->id,
            ],
            'links' => [
                'first' => $actions->url(1),
                'last' => $actions->url($actions->lastPage()),
                'prev' => $actions->previousPageUrl(),
                'next' => $actions->nextPageUrl(),
            ],
        ]);
    }
}
