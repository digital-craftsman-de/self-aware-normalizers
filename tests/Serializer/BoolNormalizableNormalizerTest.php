<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\AcceptedTermsOfService;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\ProjectId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoolNormalizableNormalizer::class)]
final class BoolNormalizableNormalizerTest extends TestCase
{
    #[Test]
    public function supports_normalization_works(): void
    {
        // -- Arrange
        $normalizer = new BoolNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsNormalization(new AcceptedTermsOfService(true)));
        self::assertFalse($normalizer->supportsNormalization(ProjectId::generateRandom()));
    }

    #[Test]
    public function supports_denormalization_works(): void
    {
        // -- Arrange
        $normalizer = new BoolNormalizableNormalizer();

        // -- Act & Assert
        self::assertTrue($normalizer->supportsDenormalization(null, AcceptedTermsOfService::class));
        self::assertFalse($normalizer->supportsDenormalization(null, ProjectId::class));
    }

    #[Test]
    public function int_normalize_and_denormalizer_works(): void
    {
        // -- Arrange
        $normalizer = new BoolNormalizableNormalizer();

        $acceptedTermsOfService = new AcceptedTermsOfService(false);

        // -- Act
        $normalized = $normalizer->normalize($acceptedTermsOfService);
        $denormalized = $normalizer->denormalize($normalized, AcceptedTermsOfService::class);

        // -- Assert
        self::assertEquals($acceptedTermsOfService, $denormalized);
    }

    #[Test]
    public function int_normalize_and_denormalizer_works_with_null(): void
    {
        // -- Arrange
        $normalizer = new BoolNormalizableNormalizer();

        $acceptedTermsOfService = null;

        // -- Act
        $normalized = $normalizer->normalize($acceptedTermsOfService);
        $denormalized = $normalizer->denormalize($normalized, AcceptedTermsOfService::class);

        // -- Assert
        self::assertEquals($acceptedTermsOfService, $denormalized);
    }
}
