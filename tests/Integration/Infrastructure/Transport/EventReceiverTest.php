<?php

declare(strict_types=1);

namespace Tests\MichaelPetri\SymfonyFileWatcher\Integration\Infrastructure\Transport;

use MichaelPetri\Git\GitRepository;
use MichaelPetri\Git\GitRepositoryInterface;
use MichaelPetri\Git\Value\Change;
use MichaelPetri\Git\Value\Directory;
use MichaelPetri\Git\Value\Duration;
use MichaelPetri\Git\Value\File;
use MichaelPetri\Git\Value\Status;
use MichaelPetri\SymfonyFileWatcher\Domain\Event\FileChanged;
use MichaelPetri\SymfonyFileWatcher\Domain\Event\FileCreated;
use MichaelPetri\SymfonyFileWatcher\Domain\Event\FileDeleted;
use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\EventReceiver;
use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Stamp\FilenameStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Process\Process;

/** @covers \MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\EventReceiver */
final class EventReceiverTest extends TestCase
{
    private const BACK_OFF_TIME_IN_MILLISECONDS = 50;

    private Directory $directory;
    private GitRepositoryInterface $repository;
    private EventReceiver $receiver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = Directory::from(\sys_get_temp_dir())->sub('EventReceiverTest');

        $this->delete($this->directory);

        $p = new Process(['mkdir', '-p', $this->directory->path]);
        $p->mustRun();

        $this->repository = new GitRepository(
            $this->directory,
            $this->directory->sub('.git'),
            Duration::inSeconds(60)
        );
        $this->receiver = new EventReceiver(
            $this->repository,
            Duration::inMilliseconds(self::BACK_OFF_TIME_IN_MILLISECONDS)
        );

        $this->receiver->setup();
    }

    public function testSetup(): void
    {
        $gitDirectory = $this->directory->sub('.git');

        // Remove git file generated by test setup
        $this->delete($gitDirectory);

        self::assertDirectoryDoesNotExist($gitDirectory->path);

        $this->receiver->setup();

        self::assertDirectoryExists($gitDirectory->path);
    }

    public function testGetCreatedEvent(): void
    {
        // Ensure we start without any events
        self::assertNoEvents($this->receiver);

        // Create a new file
        $file = $this->create('untracked-file');

        // Ensure we see a file created event
        self::assertEvents(
            [
                Envelope::wrap(
                    new FileCreated($file->toString())
                )->with(
                    new FilenameStamp($file->toString())
                )
            ],
            $this->receiver
        );

        // Ensure we don't see the event twice
        self::assertNoEvents($this->receiver);
    }

    public function testBackOffTimeIsUsed(): void
    {
        $start = \microtime(true);
        self::assertEmpty([...$this->receiver->get()]);
        $stop = \microtime(true);

        self::assertGreaterThanOrEqual(self::BACK_OFF_TIME_IN_MILLISECONDS / 1000, $stop - $start);
    }

    public function testGetUpdatedEvent(): void
    {
        // Write and commit new file
        $file = $this->create('tracked-file');
        $this->repository->add($file);
        $this->repository->commit('Initial commit');

        // Ensure we start without any events
        self::assertNoEvents($this->receiver);

        // Update the file
        $this->write($file, 'This is an updated file');

        // Ensure we see a file changed event
        self::assertEvents(
            [
                Envelope::wrap(
                    new FileChanged($file->toString())
                )->with(
                    new FilenameStamp($file->toString())
                )
            ],
            $this->receiver
        );

        // Ensure we don't see the event twice
        self::assertNoEvents($this->receiver);
    }

    public function testGetDeletedEvent(): void
    {
        // Write and commit existing file
        $file = $this->create('tracked-file');
        $this->repository->add($file);
        $this->repository->commit('Initial commit');

        // Ensure we start without any events
        self::assertNoEvents($this->receiver);

        // Delete the file
        $this->delete($file);

        // Ensure we see a file deleted event
        self::assertEvents(
            [
                $event = Envelope::wrap(
                    new FileDeleted($file->toString())
                )->with(
                    new FilenameStamp($file->toString())
                )
            ],
            $this->receiver
        );

        // Ensure we don't see the event twice
        self::assertNoEvents($this->receiver);
    }

    public function testAck(): void
    {
        // Simulate a file received from receiver.
        $file = $this->create('untracked-file');
        $this->repository->add($file);

        // Assert changes gets captured by repository
        self::assertEquals(
            [
                new Change($file, Status::ADDED, Status::UNMODIFIED)
            ],
            $this->repository->status()->toArray()
        );

        $this->receiver->ack(
            Envelope::wrap(
                new FileCreated($file->toString())
            )->with(
                new FilenameStamp($file->toString())
            )
        );

        // Assert acknowledge commits this changes
        self::assertEmpty(
            $this->repository->status()->toArray()
        );
    }

    public function testReject(): void
    {
        // Simulate a file received from receiver.
        $file = $this->create('untracked-file');
        $this->repository->add($file);

        // Assert changes gets captured by repository
        self::assertEquals(
            [
                new Change($file, Status::ADDED, Status::UNMODIFIED)
            ],
            $this->repository->status()->toArray()
        );

        $this->receiver->reject(
            Envelope::wrap(
                new FileCreated($file->toString())
            )->with(
                new FilenameStamp($file->toString())
            )
        );

        // Assert file is untracked again
        self::assertEquals(
            [
                new Change($file, Status::UNTRACKED, Status::UNTRACKED)
            ],
            $this->repository->status()->toArray()
        );
    }

    /** @psalm-param FileCreated $events */
    public static function assertEvents(array $events, EventReceiver $receiver): void
    {
        self::assertEquals($events, [...$receiver->get()], 'Failed to assert that receiver has events.');
    }

    public static function assertNoEvents(EventReceiver $receiver): void
    {
        self::assertEmpty([...$receiver->get()], 'Failed to assert that receiver has no events.');
    }

    /** @psalm-param non-empty-string $name */
    private function create(string $name): File
    {
        $path = $this->directory->path.\DIRECTORY_SEPARATOR.$name;

        \file_put_contents($path, '');

        return File::from($path);
    }

    /** @psalm-param non-empty-string $name */
    private function write(File $file, string $content = ''): void
    {
        \file_put_contents($file->toString(), $content);
    }

    /** @psalm-param non-empty-string $name */
    private function delete(File|Directory $target): void
    {
        if ($target instanceof File) {
            \unlink($target->toString());
        } else {
            $p = new Process(['rm', '-rf', $target->path]);
            $p->mustRun();
        }
    }
}
