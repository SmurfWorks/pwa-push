<?php

namespace Tests\Fakes;

use App\Models\Alert;
use App\Models\Device;
use App\Push\PushResult;
use App\Push\PushSender;

class FakePushSender implements PushSender
{
    /** @var list<array{0: string, 1: string}> */
    public array $sent = [];

    public string $outcome = 'sent';

    public int $failsRemaining = 0;

    public function send(Device $device, Alert $alert): PushResult
    {
        $this->sent[] = [$device->id, $alert->key];

        if ($this->failsRemaining > 0) {
            $this->failsRemaining--;

            return PushResult::failed('temporary');
        }

        return match ($this->outcome) {
            'gone' => PushResult::gone(),
            'failed' => PushResult::failed('nope'),
            default => PushResult::sent(),
        };
    }
}
