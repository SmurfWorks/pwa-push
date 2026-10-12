<?php

namespace App\Push;

use App\Models\Alert;
use App\Models\Device;

interface PushSender
{
    public function send(Device $device, Alert $alert): PushResult;
}
