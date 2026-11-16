<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;

/**
 * Proves that plans are read from the cache: building a plan would need the type extractor. Throws an exception of type-info (a type
 * that can't be resolved), so a class without cached plan is left to the PropertyNormalizer.
 */
final readonly class ThrowingPropertyTypeExtractor implements PropertyTypeExtractorInterface
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
        throw new LogicException(sprintf('The type of %s::$%s must not be extracted.', $class, $property));
    }
}
