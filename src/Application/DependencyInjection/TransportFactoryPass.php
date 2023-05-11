<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Application\DependencyInjection;

use MichaelPetri\Git\Value\Directory;
use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\FileWatcherFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class TransportFactoryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $basePathId = \sprintf('%s<%s>', Directory::class, 'symfony_file_watcher.base_path');

        $projectDir = Directory::from($container->getParameter('kernel.project_dir'));

        $container
            ->autowire($basePathId, Directory::class)
            ->setFactory([Directory::class, 'from'])
            ->setArguments([
                $projectDir->sub('var')->path
            ]);

        $container
            ->autowire(FileWatcherFactory::class, FileWatcherFactory::class)
            ->setArguments([
                new Reference($basePathId)
            ])
            ->addTag('messenger.transport_factory');
    }
}
