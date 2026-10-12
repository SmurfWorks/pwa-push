<?php

namespace Tests\Unit;

use App\Support\DeliveryClock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeliveryClockTest extends TestCase
{
    public function test_a_clock_time_is_read_in_the_given_timezone(): void
    {
        $losAngeles = DeliveryClock::resolve('2026-10-12T09:00:00', 'America/Los_Angeles');
        $london = DeliveryClock::resolve('2026-10-12T09:00:00', 'Europe/London');

        $this->assertSame('2026-10-12T16:00:00Z', $losAngeles['instant']->utc()->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('2026-10-12T09:00:00', $losAngeles['local']);
        $this->assertSame('2026-10-12T08:00:00Z', $london['instant']->utc()->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('2026-10-12T09:00:00', $london['local']);
    }

    public function test_a_time_with_its_own_timezone_keeps_that_moment(): void
    {
        $resolved = DeliveryClock::resolve('2026-10-12T09:00:00-07:00', 'Europe/London');

        $this->assertSame('2026-10-12T16:00:00Z', $resolved['instant']->utc()->format('Y-m-d\TH:i:s\Z'));
        $this->assertNull($resolved['local']);
    }

    public function test_a_repeated_hour_uses_the_earlier_moment(): void
    {
        $resolved = DeliveryClock::resolve('2026-11-01T01:30:00', 'America/Los_Angeles');

        $this->assertSame('2026-11-01T08:30:00Z', $resolved['instant']->utc()->format('Y-m-d\TH:i:s\Z'));
    }

    public function test_a_skipped_hour_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("That time does not exist in this phone's timezone.");

        DeliveryClock::resolve('2026-03-08T02:30:00', 'America/Los_Angeles');
    }

    public function test_a_clock_time_without_a_timezone_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Register a timezone for this phone, or send a time that includes one.');

        DeliveryClock::resolve('2026-10-12T09:00:00', null);
    }
}
