<?php

declare(strict_types=1);

namespace Tests\MichaelPetri\SymfonyFileWatcher\Unit\Infrastructure\Transport;

use MichaelPetri\Git\Exception\FileNotCommitted;
use MichaelPetri\Git\GitRepositoryInterface;
use MichaelPetri\Git\Value\Duration;
use MichaelPetri\Git\Value\File;
use MichaelPetri\SymfonyFileWatcher\Domain\Event\FileCreated;
use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\EventReceiver;
use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Stamp\FilenameStamp;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

final class EventReceiverTest extends TestCase
{
    private GitRepositoryInterface&MockObject $repository;
    private EventReceiver $receiver;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(GitRepositoryInterface::class);

        $this->receiver = new EventReceiver(
            $this->repository,
            Duration::inMilliseconds(1)
        );
    }

    public function testAckResetsFileOnFailure(): void
    {
        $file = File::from('bar.baz');

        $this->repository
            ->expects(self::once())
            ->method('commit')
            ->with(
                'Successfully processed file',
                $file
            )
            ->willThrowException(
                FileNotCommitted::fromDirectoryAndFiles(
                    $file->directory,
                    [$file]
                )
            );

        $this->repository
            ->expects(self::once())
            ->method('reset')
            ->with($file);

        $this->receiver->ack(
            Envelope::wrap(
                new FileCreated($file->name)
            )->with(
                new FilenameStamp($file->name)
            )
        );
    }
}
