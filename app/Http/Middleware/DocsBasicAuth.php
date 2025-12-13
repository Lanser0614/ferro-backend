<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocsBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = config('services.bitrix_webhook.domain');
        $expectedPassword = config('services.bitrix_webhook.application_token');

        $username = $request->getUser();
        $password = $request->getPassword();

        if (
            empty($expectedUser) ||
            empty($expectedPassword) ||
            $username !== $expectedUser ||
            $password !== $expectedPassword
        ) {
            return response('Unauthorized.', Response::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Basic realm="Ferro API Docs"',
            ]);
        }

        return $next($request);
    }
}
