<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;

/**
 * Stand-in for a PropertyNormalizer of a later version of the serializer with a required constructor parameter the
 * PlanNormalizerCompilerPass doesn't know.
 */
final class FuturePropertyNormalizerWithRequiredParameter
{
    public function __construct(
        public bool $requiredOption,
        public ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
    ) {
    }
}
