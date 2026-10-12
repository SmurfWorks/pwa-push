<?php

namespace App\Console\Commands;

use App\Models\PushApp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('push-apps:list')]
#[Description('List registered apps')]
class ListPushApps extends Command
{
    public function handle(): int
    {
        $apps = PushApp::query()->withCount('devices')->orderBy('name')->get();

        if ($apps->isEmpty()) {
            $this->info('No apps yet. Register one with push-apps:create.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Id', 'Origins', 'Devices'],
            $apps->map(fn (PushApp $app) => [
                $app->name,
                $app->id,
                implode(', ', $app->origins),
                $app->devices_count,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
