<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport;

use MichaelPetri\Git\Value\Directory;

/** @psalm-immutable */
final class Dsn
{
    private const ERROR = 'The given file watcher DSN "%s" is invalid: %s';
    public const SCHEME = 'watch://';

    public function __construct(
        public readonly Directory $directory
    ) {
    }

    public static function fromString(string $dsn): self
    {
        // Verify dsn beginns with supported scheme.
        if (!\str_starts_with($dsn, self::SCHEME)) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR, $dsn, 'Invalid scheme.'));
        }

        // Cut off scheme to get path.
        $path = \substr($dsn, \strlen(self::SCHEME));

        // Resolve absolute real path.
        if ($realPath = \realpath($path)) {
            $path = $realPath;
        }

        try {
            return new self(
                Directory::from($path)
            );
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\sprintf(self::ERROR, $dsn, 'Invalid path.'), 0, $e);
        }
    }
}
