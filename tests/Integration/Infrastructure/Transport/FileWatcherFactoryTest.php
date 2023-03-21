<?php

declare(strict_types=1);

namespace Tests\MichaelPetri\SymfonyFileWatcher\Integration\Infrastructure\Transport;

use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\EventReceiver;
use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\FileWatcherFactory;
use PHPUnit\Framework\TestCase;

/** @covers \MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\FileWatcherFactory */
final class FileWatcherFactoryTest extends TestCase
{
    private FileWatcherFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FileWatcherFactory();
    }

    public function testSupports(): void
    {
        self::assertTrue($this->factory->supports('watch://tmp'));
    }

    public function testCreateTransport(): void
    {
        self::assertInstanceOf(
            EventReceiver::class,
            $this->factory->createTransport('watch:///tmp')
        );
    }

    public function testSupportsHandlesUnsupportedDsn(): void
    {
        self::assertFalse($this->factory->supports('mysql://'));
    }

    public function testCreateTransportFailsWithUnsupportedDsn(): void
    {
        $this->expectExceptionObject(
            new \Exception('The given file watcher DSN "mysql://user:password@localhost:3306/database" is invalid: Invalid scheme.')
        );

        $this->factory->createTransport('mysql://user:password@localhost:3306/database');
    }
}
