<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis;

/**
 * Parameter of a planned class whose value (or the elements of whose collection) is (de)normalized through the serializer, as the target
 * class has no plan and isn't a self-aware value object.
 */
final readonly class DelegatedParameter
{
    /**
     * @param class-string $targetClass
     * @param string       $typeSuffix  "[]" per level of a (nested) collection, empty for a single object
     */
    public function __construct(
        public string $name,
        public string $targetClass,
        public string $typeSuffix,
    ) {
    }
}
