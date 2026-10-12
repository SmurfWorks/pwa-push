<?php

namespace App\Support;

use InvalidArgumentException;

class Origins
{
    /**
     * @throws InvalidArgumentException
     */
    public static function normalize(string $origin): string
    {
        $parts = parse_url(trim($origin));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Enter an origin like https://example.com.');
        }

        if ((isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Enter the site origin without a path.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Use http or https.');
        }

        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test');

        if ($scheme === 'http' && ! $local) {
            throw new InvalidArgumentException('Use https for this address.');
        }

        $port = $parts['port'] ?? null;

        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = null;
        }

        return $scheme.'://'.$host.($port !== null ? ':'.$port : '');
    }

    /**
     * @param  list<string>  $origins
     * @return list<string>
     */
    public static function normalizeAll(array $origins): array
    {
        return array_values(array_unique(array_map(
            fn (string $origin) => self::normalize($origin),
            $origins,
        )));
    }

    /**
     * @param  list<string>  $origins
     */
    public static function matches(string $url, array $origins): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        try {
            $origin = self::normalize($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));
        } catch (InvalidArgumentException) {
            return false;
        }

        return in_array($origin, $origins, true);
    }
}
