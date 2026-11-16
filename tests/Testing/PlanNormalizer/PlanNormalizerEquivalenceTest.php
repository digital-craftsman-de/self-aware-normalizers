<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\Exception\FallbackIsRequired;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanFactory;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Generic;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Leaf;
use PHPUnit\Framework\Attributes\CoversClass;

require_once dirname(__DIR__, 2).'/Test/PlanNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * The equivalence test case like an app uses it, with the fixture classes as source.
 */
#[CoversClass(PlanNormalizerEquivalenceTestCase::class)]
#[CoversClass(SampleFactory::class)]
#[CoversClass(SampleContext::class)]
#[CoversClass(SerializerComparison::class)]
#[CoversClass(PlanNormalizer::class)]
#[CoversClass(PlanFactory::class)]
#[CoversClass(FallbackIsRequired::class)]
final class PlanNormalizerEquivalenceTest extends PlanNormalizerEquivalenceTestCase
{
    #[\Override]
    protected static function sourceDirectories(): array
    {
        return [
            dirname(__DIR__, 2).'/Test/PlanNormalizer/Fixture',
        ];
    }

    #[\Override]
    protected static function minimumNumberOfPlannedClasses(): int
    {
        return 16;
    }

    /**
     * The template type of Generic is resolved as a class that doesn't exist, which the SampleFactory can't build.
     */
    #[\Override]
    protected static function samplesOfClasses(): array
    {
        return [
            Generic::class => static fn (): Generic => new Generic(new Leaf('Example')),
        ];
    }

    /**
     * There is no implementation of \Countable in the source.
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
                    return $class === \Countable::class
                        ? new \ArrayObject(array_fill(0, $context->numberOfElements, $context->sampleOf(Leaf::class)))
                        : null;
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
