<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParameterKind::class)]
final class ParameterKindTest extends TestCase
{
    /**
     * The value objects the plan handles inline, which the profiler panel lists.
     */
    #[Test]
    public function kinds_of_value_objects_are_the_ones_of_the_five_interfaces_and_their_lists(): void
    {
        // -- Act
        $kindsOfValueObjects = array_keys(array_filter(
            new \ReflectionClass(ParameterKind::class)->getConstants(),
            static fn (mixed $kind): bool => is_int($kind)
                && ParameterKind::isValueObject($kind),
        ));

        // -- Assert
        self::assertSame(
            [
                'VALUE_OBJECT_STRING',
                'VALUE_OBJECT_INT',
                'VALUE_OBJECT_FLOAT',
                'VALUE_OBJECT_BOOL',
                'VALUE_OBJECT_ARRAY',
                'VALUE_OBJECT_LIST',
            ],
            $kindsOfValueObjects,
        );
    }
}
