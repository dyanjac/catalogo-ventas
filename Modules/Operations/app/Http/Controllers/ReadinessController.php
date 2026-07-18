<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Operations\Services\ReadinessService;

final class ReadinessController extends Controller
{
    public function __invoke(ReadinessService $readiness): JsonResponse
    {
        $result = $readiness->inspect();

        return response()->json([
            'ready' => $result['ready'],
            'checks' => collect($result['checks'])->map(fn (array $check): array => ['ok' => $check['ok']])->all(),
        ], $result['ready'] ? 200 : 503);
    }
}
