<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport;

use MichaelPetri\Git\GitRepository;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class FileWatcherFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options = [], ?SerializerInterface $serializer = null): TransportInterface
    {
        $dsn = Dsn::fromString($dsn);

        return new EventReceiver(
            new GitRepository(
                $dsn->directory,
                $dsn->timeout
            )
        );
    }

    public function supports(string $dsn, array $options = []): bool
    {
        return str_starts_with($dsn, Dsn::SCHEME);
    }
}
