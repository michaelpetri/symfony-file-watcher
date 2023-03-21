<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Application;

use MichaelPetri\SymfonyFileWatcher\Application\DependencyInjection\TransportFactoryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

final class FilesystemEventBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        try {
            $p = new Process(['which', 'git']);
            $p->mustRun();
        } catch (RuntimeException $e) {
            throw new \RuntimeException(
                \sprintf('The bundle "%s" requires the cli tool "git" to be installed on the system.', self::class),
                0,
                $e
            );
        }

        $container->addCompilerPass(new TransportFactoryPass());
    }
}
