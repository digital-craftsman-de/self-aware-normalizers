<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;

/**
 * Stand-in for a PropertyNormalizer of a later version of the serializer with a required constructor parameter the
 * KnownConstructionNormalizerCompilerPass doesn't know.
 */
final class FuturePropertyNormalizerWithRequiredParameter
{
    public function __construct(
        public bool $requiredOption,
        public ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
    ) {
    }
}
