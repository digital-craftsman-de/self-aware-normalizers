<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Equivalence;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Exception\FallbackIsRequired;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\PlanFactory;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\EquivalenceTestCase;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\SampleContext;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\SampleProvider;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ArrayTypeMismatch;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ElementType;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Generic;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ImageElement;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithIntOrIterableDoc;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithIterableDoc;
use PHPUnit\Framework\Attributes\CoversClass;

require_once dirname(__DIR__, 3).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * The equivalence test case like an app uses it, with the fixture classes as source.
 */
#[CoversClass(KnownConstructionNormalizer::class)]
#[CoversClass(PlanFactory::class)]
#[CoversClass(FallbackIsRequired::class)]
final class KnownConstructionNormalizerEquivalenceTest extends EquivalenceTestCase
{
    #[\Override]
    protected static function sourceDirectories(): array
    {
        return [
            dirname(__DIR__, 3).'/Test/KnownConstructionNormalizer/Fixture',
        ];
    }

    #[\Override]
    protected static function minimumNumberOfPlannedClasses(): int
    {
        return 22;
    }

    /**
     * The template type of Generic is resolved as a class that doesn't exist, which the SampleFactory can't build.
     */
    #[\Override]
    protected static function samplesOfClasses(): array
    {
        return [
            Generic::class => static fn (): Generic => new Generic(new Leaf('Example')),
            // The SampleFactory builds the PHPDoc type (iterable), not the native one
            ArrayWithIterableDoc::class => static fn (): ArrayWithIterableDoc => new ArrayWithIterableDoc(['Example']),
            ArrayWithIntOrIterableDoc::class => static fn (): ArrayWithIntOrIterableDoc => new ArrayWithIntOrIterableDoc(['Example']),
            // The PHPDoc type (string) is wrong on purpose
            ArrayTypeMismatch::class => static fn (): ArrayTypeMismatch => new \ReflectionClass(ArrayTypeMismatch::class)->newInstance(['Example']),
        ];
    }

    /**
     * There is no implementation of \Countable in the source, and the constructor of ImageElement requires the case IMAGE of its type.
     */
    #[\Override]
    protected static function sampleProviders(): array
    {
        return [
            new class implements SampleProvider {
                #[\Override]
                public function sample(
                    string $class,
                    string $parameterName,
                    SampleContext $context,
                ): ?object {
                    return match (true) {
                        $class === \Countable::class => new \ArrayObject(array_fill(0, $context->numberOfElements, $context->sampleOf(Leaf::class))),
                        $class === ElementType::class && $context->classOfParameter === ImageElement::class => ElementType::IMAGE,
                        default => null,
                    };
                }

                #[\Override]
                public function string(string $parameterName): ?string
                {
                    return $parameterName === 'name'
                        ? 'Example name'
                        : null;
                }
            },
        ];
    }

    #[\Override]
    protected static function minimumNumberOfValueObjects(): int
    {
        return 4;
    }
}
