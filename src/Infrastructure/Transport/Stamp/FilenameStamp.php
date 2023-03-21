<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/** @psalm-immutable */
final class FilenameStamp implements StampInterface
{
    /** @psalm-param non-empty-string $filename */
    public function __construct(
        public readonly string $filename
    ) {
    }
}
