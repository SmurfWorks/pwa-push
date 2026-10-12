<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('push.admin_token');

        if ($configured === '') {
            abort(503, 'Set PUSH_ADMIN_TOKEN before registering apps.');
        }

        $given = (string) $request->bearerToken();

        if ($given === '' || ! hash_equals($configured, $given)) {
            abort(401, 'Unknown admin token.');
        }

        return $next($request);
    }
}
