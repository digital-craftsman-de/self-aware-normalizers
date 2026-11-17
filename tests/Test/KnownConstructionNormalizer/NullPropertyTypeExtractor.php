<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * Type extractor without any type, for a case the extractor of the PropertyNormalizer doesn't produce for typed promoted properties.
 */
final readonly class NullPropertyTypeExtractor implements PropertyTypeExtractorInterface
{
    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function getType(
        string $class,
        string $property,
        array $context = [],
    ): ?Type {
        return null;
    }
}
