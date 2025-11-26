<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\SearchWithOptionalLimit;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress InvalidArgument For some reason Psalm doesn't identify the trait as a trait-string.
 */
#[CoversTrait(NullableIntDenormalizableTrait::class)]
final class NullableIntDenormalizableTraitTest extends TestCase
{
    #[Test]
    public function denormalize_when_not_null_works_with_null(): void
    {
        // -- Arrange
        $search = new SearchWithOptionalLimit(
            searchTerm: new SearchTerm('tony'),
            limit: null,
        );
        $data = [
            'searchTerm' => 'tony',
            'limit' => null,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = SearchWithOptionalLimit::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }

    #[Test]
    public function denormalize_when_not_null_works_without_null(): void
    {
        // -- Arrange
        $search = new SearchWithOptionalLimit(
            searchTerm: new SearchTerm('tony'),
            limit: new Limit(50),
        );
        $data = [
            'searchTerm' => 'tony',
            'limit' => 50,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = SearchWithOptionalLimit::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }
}
