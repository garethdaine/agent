<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Organization\FindNlScheduleParseAttemptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\NlSchedule\ParseScheduleRequest;
use App\Support\NlSchedule\NlScheduleParserService;
use Illuminate\Http\JsonResponse;

class NlScheduleController extends Controller
{
    public function __construct(
        private NlScheduleParserService $parserService
    ) {}

    public function store(ParseScheduleRequest $request): JsonResponse
    {
        $result = $this->parserService->parse(
            $request->user(),
            $request->validated('input'),
            $request->validated('timezone')
        );

        return response()->json($result);
    }

    public function show(string $parseAttemptId, FindNlScheduleParseAttemptAction $findAttempt): JsonResponse
    {
        $attempt = $findAttempt->execute($parseAttemptId);

        if ($attempt->user_id !== auth()->id()) {
            abort(403, 'Access denied');
        }

        $response = [
            'status' => $attempt->status,
            'parse_attempt_id' => $attempt->id,
        ];

        if ($attempt->status === 'completed') {
            $response['result'] = [
                'cron_expression' => $attempt->cron_result,
                'timezone' => $attempt->timezone,
                'confidence' => $attempt->confidence,
                'active_hours' => $attempt->active_hours_result,
                'parser_path' => $attempt->parser_path,
            ];
        }

        if ($attempt->status === 'failed') {
            $response['error'] = $attempt->error_message;
        }

        return response()->json($response);
    }
}
