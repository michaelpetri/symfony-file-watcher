<?php

declare(strict_types=1);

namespace Tests\MichaelPetri\SymfonyFileWatcher\Unit\Infrastructure\Transport;

use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Dsn;
use PHPUnit\Framework\TestCase;

/** @covers \MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Dsn */
final class DsnTest extends TestCase
{
    /** @dataProvider validDsnProvider */
    public function testFromString(string $input, string $expectedPath, int $expectedTimeoutInSeconds = 60): void
    {
        self::assertEquals($expectedPath, Dsn::fromString($input)->directory->path);
        self::assertEquals($expectedTimeoutInSeconds, Dsn::fromString($input)->timeout->seconds);
    }

    /**
     * @psalm-return iterable<array-key, array{
     *    non-empty-string,
     *    non-empty-string,
     * }>|iterable<array-key, array{
     *     non-empty-string,
     *     non-empty-string,
     *     positive-int
     *  }>
     */
    public static function validDsnProvider(): iterable
    {
        $path = \realpath('.');

        if (!\is_string($path) || '' === $path) {
            throw new \LogicException('\realpath(\'.\') must be non-empty-string');
        }

        yield 'no explicit path will use current directory' => ['watch://', $path];
        yield 'root path ' => ['watch:///', '/'];
        yield 'absolute path will be resolved to absolute path' => ['watch:///tmp/../tmp', '/tmp'];
        yield 'with timeout option' => ['watch:///tmp?timeout=300000', '/tmp', 300];
    }

    /** @dataProvider invalidDsnProvider */
    public function testFromStringWithInvalidInput(string $input, \Exception $exception): void
    {
        $this->expectExceptionObject($exception);
        Dsn::fromString($input);
    }

    /**
     * @psalm-return iterable<array-key, array{
     *    non-empty-string,
     *    \InvalidArgumentException
     * }>
     */
    public static function invalidDsnProvider(): iterable
    {
        yield 'doctrine dsn' => [
            'mysql://user:password@localhost:3306/database',
            new \InvalidArgumentException('The given file watcher DSN "mysql://user:password@localhost:3306/database" is invalid: Invalid scheme.')
        ];
        yield 'invalid timeout' => [
            'watch:///tmp?timeout=0',
            new \InvalidArgumentException('The given file watcher DSN "watch:///tmp?timeout=0" is invalid: Timeout options must be positive int.')
        ];
    }
}
