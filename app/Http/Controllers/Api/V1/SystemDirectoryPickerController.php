<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Agent\ErrorEnvelope;
use App\Support\System\DirectoryPicker;
use App\Support\System\DirectoryPickerException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemDirectoryPickerController extends Controller
{
    public function store(Request $request, DirectoryPicker $directoryPicker): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_path' => ['nullable', 'string', 'max:1024'],
        ]);

        if ($validator->fails()) {
            /** @var array<string, mixed> $errors */
            $errors = $validator->errors()->toArray();

            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, $errors);
        }

        $currentPath = $validator->validated()['current_path'] ?? null;

        try {
            $path = $directoryPicker->pick(is_string($currentPath) ? $currentPath : null);
        } catch (DirectoryPickerException $exception) {
            return ErrorEnvelope::make(
                code: $exception->errorCode(),
                message: $exception->getMessage(),
                status: $exception->statusCode(),
                details: []
            );
        }

        return response()->json([
            'data' => [
                'path' => $path,
            ],
        ]);
    }
}
