<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachObservabilityContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = (string) $request->header('X-Request-ID');
        $requestId = Str::isUuid($requested) ? $requested : (string) Str::uuid();
        $user = $request->hasSession() ? $request->user() : null;
        $context = array_filter([
            'request_id' => $requestId,
            'organization_id' => $user?->organization_id,
            'user_id' => $user?->id,
        ], fn ($value): bool => $value !== null);
        Context::add($context);
        Log::withContext($context);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
