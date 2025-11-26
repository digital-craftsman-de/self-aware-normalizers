<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\SearchWithOptionalRange;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Range;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress InvalidArgument For some reason Psalm doesn't identify the trait as a trait-string.
 */
#[CoversTrait(NullableFloatDenormalizableTrait::class)]
final class NullableFloatDenormalizableTraitTest extends TestCase
{
    #[Test]
    public function denormalize_when_not_null_works_with_null(): void
    {
        // -- Arrange
        $search = new SearchWithOptionalRange(
            searchTerm: new SearchTerm('tony'),
            range: null,
        );
        $data = [
            'searchTerm' => 'tony',
            'range' => null,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = SearchWithOptionalRange::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }

    #[Test]
    public function denormalize_when_not_null_works_without_null(): void
    {
        // -- Arrange
        $search = new SearchWithOptionalRange(
            searchTerm: new SearchTerm('tony'),
            range: new Range(0.5),
        );
        $data = [
            'searchTerm' => 'tony',
            'range' => 0.5,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = SearchWithOptionalRange::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }
}
