<?php

namespace App\Push;

use App\Models\PushApp;
use App\Support\Origins;
use Illuminate\Support\Str;

class PushAppRegistry
{
    /**
     * @param  list<string>  $origins
     * @return array{app: PushApp, token: string}
     */
    public function create(string $name, array $origins): array
    {
        $token = Str::random(48);
        $app = new PushApp([
            'name' => $name,
            'origins' => Origins::normalizeAll($origins),
        ]);
        $app->token_hash = hash('sha256', $token);
        $app->save();

        return ['app' => $app, 'token' => $token];
    }

    public function rotate(PushApp $app): string
    {
        $token = Str::random(48);
        $app->token_hash = hash('sha256', $token);
        $app->save();

        return $token;
    }
}
