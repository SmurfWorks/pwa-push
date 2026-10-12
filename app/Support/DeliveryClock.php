<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class DeliveryClock
{
    /**
     * Turn a requested time into the UTC moment it should be sent.
     * A value with no timezone is read on the phone. A value that already
     * includes one is that exact moment.
     *
     * @return array{instant: Carbon, local: ?string}
     */
    public static function resolve(string $value, ?string $timezone): array
    {
        $value = trim($value);

        if (self::hasExplicitOffset($value)) {
            return [
                'instant' => Carbon::parse($value)->utc()->startOfSecond(),
                'local' => null,
            ];
        }

        if ($timezone === null || $timezone === '') {
            throw new InvalidArgumentException('Register a timezone for this phone, or send a time that includes one.');
        }

        try {
            $parsed = new DateTimeImmutable($value, new DateTimeZone($timezone));
        } catch (Exception) {
            throw new InvalidArgumentException('Send a time such as 2026-10-12T09:00:00.');
        }

        $requested = self::wallClock($value);

        if ($requested !== null && $parsed->format('Y-m-d\TH:i:s') !== $requested) {
            throw new InvalidArgumentException("That time does not exist in this phone's timezone.");
        }

        return [
            'instant' => Carbon::instance($parsed)->utc()->startOfSecond(),
            'local' => $parsed->format('Y-m-d\TH:i:s'),
        ];
    }

    public static function hasExplicitOffset(string $value): bool
    {
        return preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', trim($value)) === 1;
    }

    private static function wallClock(string $value): ?string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::(\d{2}))?(?:\.\d+)?$/', trim($value), $match) !== 1) {
            return null;
        }

        return $match[1].'T'.$match[2].':'.($match[3] ?? '00');
    }
}
