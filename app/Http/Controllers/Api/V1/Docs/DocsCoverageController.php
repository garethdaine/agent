<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Docs;

use App\Http\Controllers\Controller;
use App\Support\Documentation\DocsCatalog;
use Illuminate\Http\JsonResponse;

class DocsCoverageController extends Controller
{
    public function __construct(
        private readonly DocsCatalog $catalog
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->coverage(),
        ]);
    }
}
