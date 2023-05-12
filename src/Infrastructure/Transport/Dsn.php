<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport;

use MichaelPetri\Git\Value\Directory;
use MichaelPetri\Git\Value\Duration;

/** @psalm-immutable */
final class Dsn
{
    private const DEFAULT_OPTIONS = [
        'timeout' => 60000,
        'backOffTime' => 60000,
    ];

    private const ERROR = 'The given file watcher DSN "%s" is invalid: %s';
    public const SCHEME = 'watch://';

    public function __construct(
        public readonly Directory $directory,
        public readonly Duration $timeout,
        public readonly Duration $backOffTime
    ) {
    }

    public static function fromString(string $dsn): self
    {
        // Verify dsn beginns with supported scheme.
        if (!\str_starts_with($dsn, self::SCHEME)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR, $dsn, 'Invalid scheme.'));
        }

        // File uri's don't require a host, so we just add one here to use php's internal parse url function.
        $dsnWithFakeHost = \str_replace(self::SCHEME, self::SCHEME . 'localhost', $dsn);

        if (false === $components = \parse_url($dsnWithFakeHost)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR, $dsn, 'Malformed string.'));
        }

        // Use parsed path or current directory as fallback.
        $path = $components['path'] ?? \realpath('.');

        // Resolve absolute real path.
        if ($realPath = \realpath($path)) {
            $path = $realPath;
        }

        $query = [];
        if (isset($components['query'])) {
            \parse_str($components['query'], $query);
        }

        // Use parsed timeout or default fallback.
        $timeout = (int) ($query['timeout'] ?? self::DEFAULT_OPTIONS['timeout']);

        // Use parsed backoff time or default fallback.
        $backOffTime = (int) ($query['backOffTime'] ?? self::DEFAULT_OPTIONS['backOffTime']);

        if (0 >= $timeout) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR, $dsn, 'Timeout options must be positive int.'));
        }

        if (0 >= $backOffTime) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR, $dsn, 'Back off time options must be positive int.'));
        }

        return new self(
            Directory::from($path),
            Duration::inMilliseconds($timeout),
            Duration::inMilliseconds($backOffTime)
        );
    }
}
