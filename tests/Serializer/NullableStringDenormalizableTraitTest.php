<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\Search;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullableStringDenormalizableTraitTest extends TestCase
{
    #[Test]
    public function denormalize_when_not_null_works_with_null(): void
    {
        // -- Arrange
        $search = new Search(
            searchTerm: null,
            limit: new Limit(50),
        );
        $data = [
            'searchTerm' => null,
            'limit' => 50,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = Search::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }

    #[Test]
    public function denormalize_when_not_null_works_without_null(): void
    {
        // -- Arrange
        $search = new Search(
            searchTerm: new SearchTerm('tony'),
            limit: new Limit(50),
        );
        $data = [
            'searchTerm' => 'tony',
            'limit' => 50,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = Search::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }
}
