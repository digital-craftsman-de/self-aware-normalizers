<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * Type extractor with the same type for every property, for types the extractor of the PropertyNormalizer doesn't produce (like a
 * plain iterable that isn't a collection).
 */
final readonly class FixedPropertyTypeExtractor implements PropertyTypeExtractorInterface
{
    public function __construct(
        private Type $type,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function getType(
        string $class,
        string $property,
        array $context = [],
    ): Type {
        return $this->type;
    }
}
