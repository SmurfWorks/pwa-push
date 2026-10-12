<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Push\PushSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('alerts:send')]
#[Description('Send notifications that are due, and skip ones that are too late')]
class SendAlerts extends Command
{
    public function handle(PushSender $sender): int
    {
        if ((string) config('push.vapid.public_key') === '' || (string) config('push.vapid.private_key') === '') {
            $this->error('Set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY. Run php artisan push:install.');

            return self::FAILURE;
        }

        $due = Alert::query()
            ->with('device')
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->where('deliver_at', '<=', now())
            ->orderBy('deliver_at')
            ->limit(500)
            ->get();

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $removed = 0;
        $gone = [];

        foreach ($due as $alert) {
            if (isset($gone[$alert->device_id]) || $alert->device === null) {
                continue;
            }

            $deadline = $alert->deliver_at->copy()->addMinutes($alert->grace_minutes);

            if (now()->greaterThan($deadline)) {
                $alert->forceFill(['skipped_at' => now()])->save();
                $skipped++;

                continue;
            }

            $result = $sender->send($alert->device, $alert);

            if ($result->outcome === 'sent') {
                $alert->forceFill([
                    'sent_at' => now(),
                    'last_error' => null,
                ])->save();
                $sent++;

                continue;
            }

            if ($result->outcome === 'gone') {
                $alert->device->delete();
                $gone[$alert->device_id] = true;
                $removed++;

                continue;
            }

            $alert->forceFill([
                'last_error' => Str::limit((string) $result->error, 500, ''),
            ])->save();
            $this->warn($alert->key.': '.$result->error);
            $failed++;
        }

        $this->info("Sent {$sent}, skipped {$skipped}, failed {$failed}, removed {$removed}.");

        return self::SUCCESS;
    }
}
