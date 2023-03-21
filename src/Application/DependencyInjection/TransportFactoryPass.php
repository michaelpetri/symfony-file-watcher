<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Application\DependencyInjection;

use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\FileWatcherFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TransportFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container
            ->autowire(FileWatcherFactory::class, FileWatcherFactory::class)
            ->addTag('messenger.transport_factory');
    }
}
