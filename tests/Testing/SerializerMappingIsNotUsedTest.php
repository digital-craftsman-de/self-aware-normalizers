<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The test case like an app uses it, with the source of the bundle (without the test cases, which use the attributes on purpose).
 */
#[CoversClass(SerializerMappingIsNotUsedTestCase::class)]
final class SerializerMappingIsNotUsedTest extends SerializerMappingIsNotUsedTestCase
{
    #[\Override]
    protected static function sourceDirectories(): array
    {
        return [
            dirname(__DIR__, 2).'/src/DependencyInjection',
            dirname(__DIR__, 2).'/src/Doctrine',
            dirname(__DIR__, 2).'/src/Serializer',
        ];
    }
}
