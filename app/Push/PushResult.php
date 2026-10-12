<?php

namespace App\Push;

final class PushResult
{
    private function __construct(
        public readonly string $outcome,
        public readonly ?string $error = null,
    ) {}

    public static function sent(): self
    {
        return new self('sent');
    }

    public static function gone(): self
    {
        return new self('gone');
    }

    public static function failed(string $error): self
    {
        return new self('failed', $error);
    }
}
