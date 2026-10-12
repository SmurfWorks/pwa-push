<?php

namespace App\Http\Middleware;

use App\Models\PushApp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePushApp
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $app = $token === ''
            ? null
            : PushApp::query()->where('token_hash', hash('sha256', $token))->first();

        if ($app === null) {
            abort(401, 'Unknown app token.');
        }

        $request->attributes->set('pushApp', $app);

        return $next($request);
    }
}
