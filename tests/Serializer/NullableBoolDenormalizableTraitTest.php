<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\SearchWithOptionalAcceptedTermsOfService;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\AcceptedTermsOfService;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress InvalidArgument For some reason Psalm doesn't identify the trait as a trait-string.
 */
#[CoversTrait(NullableBoolDenormalizableTrait::class)]
final class NullableBoolDenormalizableTraitTest extends TestCase
{
    #[Test]
    public function denormalize_when_not_null_works_with_null(): void
    {
        // -- Arrange
        $search = new SearchWithOptionalAcceptedTermsOfService(
            searchTerm: new SearchTerm('tony'),
            acceptedTermsOfService: null,
        );
        $data = [
            'searchTerm' => 'tony',
            'acceptedTermsOfService' => null,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = SearchWithOptionalAcceptedTermsOfService::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }

    #[Test]
    public function denormalize_when_not_null_works_without_null(): void
    {
        // -- Arrange
        $search = new SearchWithOptionalAcceptedTermsOfService(
            searchTerm: new SearchTerm('tony'),
            acceptedTermsOfService: new AcceptedTermsOfService(false),
        );
        $data = [
            'searchTerm' => 'tony',
            'acceptedTermsOfService' => false,
        ];

        // -- Act
        $normalized = $search->normalize();
        $denormalized = SearchWithOptionalAcceptedTermsOfService::denormalize($data);

        // -- Assert
        self::assertEquals($data, $normalized);
        self::assertEquals($search, $denormalized);
    }
}
