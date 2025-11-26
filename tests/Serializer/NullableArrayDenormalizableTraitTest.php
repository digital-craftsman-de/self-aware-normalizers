<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\GetUsersQuery;
use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\Search;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress InvalidArgument For some reason Psalm doesn't identify the trait as a trait-string.
 */
#[CoversTrait(NullableArrayDenormalizableTrait::class)]
final class NullableArrayDenormalizableTraitTest extends TestCase
{
    #[Test]
    public function denormalize_when_not_null_works_with_null(): void
    {
        // -- Arrange
        $query = new GetUsersQuery(
            userId: 5,
            search: null,
        );
        $data = [
            'userId' => 5,
            'search' => null,
        ];

        // -- Act
        $normalized = $query->normalize();
        $denormalized = GetUsersQuery::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($query, $denormalized);
    }

    #[Test]
    public function denormalize_when_not_null_works_without_null(): void
    {
        // -- Arrange
        $query = new GetUsersQuery(
            userId: 5,
            search: new Search(
                searchTerm: null,
                limit: new Limit(50),
            ),
        );
        $data = [
            'userId' => 5,
            'search' => [
                'searchTerm' => null,
                'limit' => 50,
            ],
        ];

        // -- Act
        $normalized = $query->normalize();
        $denormalized = GetUsersQuery::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($query, $denormalized);
    }
}
