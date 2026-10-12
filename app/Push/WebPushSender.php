<?php

namespace App\Push;

use App\Models\Alert;
use App\Models\Device;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushSender implements PushSender
{
    public function send(Device $device, Alert $alert): PushResult
    {
        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('push.vapid.subject'),
                    'publicKey' => (string) config('push.vapid.public_key'),
                    'privateKey' => (string) config('push.vapid.private_key'),
                ],
            ]);

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $device->endpoint,
                    'publicKey' => $device->public_key,
                    'authToken' => $device->auth_token,
                    'contentEncoding' => $device->content_encoding ?: 'aes128gcm',
                ]),
                json_encode([
                    'title' => $alert->title,
                    'body' => $alert->body,
                    'icon' => $alert->icon,
                    'url' => $alert->url,
                    'tag' => $alert->tag,
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ['TTL' => max(60, $alert->grace_minutes * 60), 'urgency' => 'high'],
            );
        } catch (Throwable $exception) {
            return PushResult::failed($exception->getMessage());
        }

        if ($report->isSuccess()) {
            return PushResult::sent();
        }

        if ($report->isSubscriptionExpired()) {
            return PushResult::gone();
        }

        return PushResult::failed($report->getReason());
    }
}
