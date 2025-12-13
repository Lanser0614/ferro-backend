<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBitrixWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedDomain = config('services.bitrix_webhook.domain');
        $expectedToken = config('services.bitrix_webhook.application_token');

        $incomingDomain = data_get($request->all(), 'auth.domain');
        $incomingToken = data_get($request->all(), 'auth.application_token');

        if (
            empty($expectedDomain) ||
            empty($expectedToken) ||
            $incomingDomain !== $expectedDomain ||
            $incomingToken !== $expectedToken
        ) {
            return new JsonResponse([
                'message' => 'Bitrix webhook authentication failed.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
