<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class VapidKeyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $key = (string) config('push.vapid.public_key');

        if ($key === '') {
            abort(503, 'Set VAPID_PUBLIC_KEY. Run php artisan push:install.');
        }

        return response()->json([
            'publicKey' => $key,
        ]);
    }
}
