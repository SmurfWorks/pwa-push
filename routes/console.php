<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('alerts:send')->everyMinute()->withoutOverlapping();
