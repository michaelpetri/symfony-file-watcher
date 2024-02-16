<?php

declare(strict_types=1);

namespace MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport;

use MichaelPetri\Git\Exception\FileNotCommitted;
use MichaelPetri\Git\GitRepositoryInterface;
use MichaelPetri\Git\Value\Duration;
use MichaelPetri\Git\Value\File;
use MichaelPetri\Git\Value\Status;
use MichaelPetri\SymfonyFileWatcher\Domain\Event\FileChanged;
use MichaelPetri\SymfonyFileWatcher\Domain\Event\FileCreated;
use MichaelPetri\SymfonyFileWatcher\Domain\Event\FileDeleted;
use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Stamp\FilenameStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

use function usleep;

final class EventReceiver implements TransportInterface, SetupableTransportInterface
{
    public function __construct(
        public readonly GitRepositoryInterface $repository,
        public readonly Duration $backOffTime
    ) {
    }

    /** @psalm-return iterable<integer, Envelope> */
    public function get(): iterable
    {
        $needToSleep = true;

        foreach ($this->repository->status()->toArray() as $change) {
            $needToSleep = false;

            switch ($change->index) {
                case Status::ADDED:
                case Status::MODIFIED:
                case Status::DELETED:
                    // As soon we added the changes to index we treat them as queued.
                    break;
                case Status::UNTRACKED:
                    $filename = $change->file->toString();

                    // Mark as queued by adding to index
                    $this->repository->add($change->file);


                    yield Envelope::wrap(
                        new FileCreated($filename)
                    )->with(
                        new FilenameStamp($filename)
                    );
                    break;
                case Status::UNMODIFIED:
                    // A file has been modified in working tree but is still unmodified in index
                    switch ($change->workingTree) {
                        case Status::MODIFIED:
                            $filename = $change->file->toString();

                            // Mark as queued by adding to index
                            $this->repository->add($change->file);

                            yield Envelope::wrap(
                                new FileChanged(
                                    $filename
                                )
                            )->with(
                                new FilenameStamp($filename)
                            );
                            break;

                        case Status::DELETED:
                            $filename = $change->file->toString();

                            // Mark as queued by adding to index
                            $this->repository->add($change->file);

                            yield Envelope::wrap(
                                new FileDeleted($filename)
                            )->with(
                                new FilenameStamp($filename)
                            );
                            break;
                        default:
                            throw new \LogicException(
                                \sprintf('Unknown git working tree status "%s"', $change->workingTree->name)
                            );
                    }
                    break;
                default:
                    throw new \LogicException(
                        \sprintf('Unknown git index status "%s"', $change->index->name)
                    );
            }
        }

        if ($needToSleep) {
            $microseconds = self::coercePositiveInt((int) ($this->backOffTime->seconds  * 1000000));
            usleep($microseconds);
        }
    }

    public function ack(Envelope $envelope): void
    {
        $stamp = self::getStampFromEnvelope(FilenameStamp::class, $envelope);

        $file = File::from($stamp->filename);

        try {
            $this->repository->commit(
                'Successfully processed file',
                $file
            );
        } catch (FileNotCommitted) {
            $this->repository->reset($file);
        }
    }

    public function reject(Envelope $envelope): void
    {
        $stamp = self::getStampFromEnvelope(FilenameStamp::class, $envelope);

        $this->repository->remove(
            File::from($stamp->filename),
            cached: true
        );
    }

    public function send(Envelope $envelope): Envelope
    {
        // We implement the TransportInterface (SenderInterface) only to allow an easy configuration interface through
        // the messenger component.
        throw new \LogicException('This transport is readonly and can not be used to send messages.');
    }

    public function setup(): void
    {
        $this->repository->init();
    }

    /**
     * @template TStamp of StampInterface
     *
     * @psalm-param class-string<TStamp> $stampClass
     *
     * @psalm-return TStamp
     */
    private static function getStampFromEnvelope(string $stampClass, Envelope $envelope): StampInterface
    {
        $stamp = $envelope->last($stampClass);

        if (!$stamp instanceof $stampClass) {
            throw new \InvalidArgumentException(\sprintf('Could not get stamp of type "%s" from envelope.', $stampClass));
        }

        return $stamp;
    }

    /** @psalm-return positive-int */
    private static function coercePositiveInt(int $value): int
    {
        if (0 >= $value) {
            return 1;
        }

        return $value;
    }
}
