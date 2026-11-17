<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer;

use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Stand-in for a PropertyNormalizer of a later version of the serializer, whose constructor got a parameter the
 * PlanNormalizerCompilerPass doesn't know (the PropertyNormalizer itself is final).
 */
class FuturePropertyNormalizer
{
    /**
     * @param array<string, mixed> $defaultContext
     */
    public function __construct(
        public ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        public ?NameConverterInterface $nameConverter = null,
        public ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        public ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        public mixed $objectClassResolver = null,
        public array $defaultContext = [],
        public bool $newOption = false,
    ) {
    }
}
