<?php

namespace App\Console\Commands;

use App\Models\PushApp;
use App\Push\PushAppRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('push-apps:rotate {name}')]
#[Description('Replace an app token and print the new one')]
class RotatePushAppToken extends Command
{
    public function handle(PushAppRegistry $registry): int
    {
        $app = PushApp::query()->where('name', $this->argument('name'))->first();

        if ($app === null) {
            $this->error('No app is registered with that name.');

            return self::FAILURE;
        }

        $token = $registry->rotate($app);

        $this->info('Rotated the token for '.$app->name.'.');
        $this->line('Token: '.$token);
        $this->comment('The previous token no longer works.');

        return self::SUCCESS;
    }
}
