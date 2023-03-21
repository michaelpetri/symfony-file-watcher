<?php

declare(strict_types=1);

namespace Tests\MichaelPetri\SymfonyFileWatcher\Unit\Infrastructure\Transport;

use MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Dsn;
use PHPUnit\Framework\TestCase;

/** @covers \MichaelPetri\SymfonyFileWatcher\Infrastructure\Transport\Dsn */
final class DsnTest extends TestCase
{
    /** @dataProvider validDsnProvider */
    public function testFromString(string $input, string $expectedPath): void
    {
        self::assertEquals($expectedPath, Dsn::fromString($input)->directory->path);
    }

    public static function validDsnProvider(): iterable
    {
        yield 'no explicit path will use current directory' => ['watch://', \realpath('.')];
        yield 'root path ' => ['watch:///', '/'];
        yield 'relative path will be resolved to absolute path' => ['watch://src/Domain/..', \realpath('src')];
        yield 'absolute path will be resolved to absolute path' => ['watch:///tmp/../tmp', '/tmp'];
    }

    /** @dataProvider invalidDsnProvider */
    public function testFromStringWithInvalidInput(string $input, \Exception $exception): void
    {
        $this->expectExceptionObject($exception);
        Dsn::fromString($input);
    }

    public static function invalidDsnProvider(): iterable
    {
        yield 'doctrine dsn' => [
            'mysql://user:password@localhost:3306/database',
            new \Exception('The given file watcher DSN "mysql://user:password@localhost:3306/database" is invalid: Invalid scheme.')
        ];
    }
}
