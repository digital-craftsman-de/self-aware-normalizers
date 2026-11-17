<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\ThrowingPropertyTypeExtractor;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Described;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ReadModelFailed;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithCollectionClassDoc;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithIntOrIterableDoc;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithIterableDoc;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\NullPropertyTypeExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

require_once dirname(__DIR__, 2).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * Limitations the configured KnownConstructionNormalizer can't reach (classes out of its scope, type extractors other than the one of the
 * PropertyNormalizer). The limitations of the classes in scope are pinned by ListUnplannedClassesCommandTest.
 */
#[CoversClass(PlanFactory::class)]
#[CoversClass(Diagnosis\PlanDiagnosis::class)]
#[CoversClass(Diagnosis\PlanLimitation::class)]
#[CoversClass(Diagnosis\PlanLimitationReason::class)]
#[CoversClass(Exception\TypeCanNotBePlanned::class)]
final class PlanFactoryTest extends TestCase
{
    #[Test]
    public function limitations_of_classes_outside_of_the_scope_are_diagnosed_by_the_plan_factory(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new NullPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []);
        $hasPlan = static fn (string $class): bool => false;

        // -- Act
        $limitationOfInterface = $planFactory->diagnose(Described::class, $hasPlan)->limitation;
        $limitationOfInternalClass = $planFactory->diagnose(\ArrayObject::class, $hasPlan)->limitation;
        // The type extractor has no type
        $limitationOfUnresolvableType = $planFactory->diagnose(Leaf::class, $hasPlan)->limitation;

        // -- Assert
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::INTERFACE), $limitationOfInterface);
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::INTERNAL_CLASS), $limitationOfInternalClass);
        self::assertEquals(
            new Diagnosis\PlanLimitation(Diagnosis\PlanLimitationReason::TYPE_IS_NOT_RESOLVABLE, 'name', 'The type can not be expressed by a plan.'),
            $limitationOfUnresolvableType,
        );
        self::assertSame('type is not resolvable: $name (The type can not be expressed by a plan.)', $limitationOfUnresolvableType?->describe());
    }

    #[Test]
    public function exceptions_are_not_in_scope(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new NullPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []);

        // -- Act
        $isExceptionInScope = $planFactory->isInScope(ReadModelFailed::class);
        $isReadModelInScope = $planFactory->isInScope(Leaf::class);

        // -- Assert
        // Both in a ReadModel namespace
        self::assertFalse($isExceptionInScope);
        self::assertTrue($isReadModelInScope);
    }

    #[Test]
    public function classes_are_in_scope_through_the_pattern_or_a_parent_type(): void
    {
        // -- Arrange
        $planFactoryWithParentTypes = new PlanFactory(new NullPropertyTypeExtractor(), null, [\Countable::class, \IteratorAggregate::class]);
        $planFactoryWithPattern = new PlanFactory(new NullPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []);

        // -- Act & Assert
        self::assertTrue($planFactoryWithParentTypes->isInScope(\ArrayObject::class));
        self::assertTrue($planFactoryWithParentTypes->isInScope(\ArrayIterator::class));
        self::assertFalse($planFactoryWithParentTypes->isInScope(\DateTimeImmutable::class));
        // Not matched without a pattern
        self::assertFalse($planFactoryWithParentTypes->isInScope(Leaf::class));
        // Matched, but doesn't exist
        self::assertFalse($planFactoryWithPattern->isInScope('DigitalCraftsman\\SelfAwareNormalizers\\Test\\KnownConstructionNormalizer\\Fixture\\ReadModel\\DoesNotExist'));
        self::assertFalse($planFactoryWithPattern->isInScope(\ArrayObject::class));
    }

    #[Test]
    public function factory_with_another_type_extractor_keeps_the_scope(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new NullPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', [\Countable::class]);

        // -- Act
        $planFactoryWithOtherTypeExtractor = $planFactory->withPropertyTypeExtractor(new ThrowingPropertyTypeExtractor());

        // -- Assert
        self::assertTrue($planFactoryWithOtherTypeExtractor->isInScope(Leaf::class));
        self::assertTrue($planFactoryWithOtherTypeExtractor->isInScope(\ArrayObject::class));
        self::assertEquals(
            new Diagnosis\PlanLimitation(
                Diagnosis\PlanLimitationReason::TYPE_IS_NOT_RESOLVABLE,
                'name',
                sprintf('The type of %s::$name must not be extracted.', Leaf::class),
            ),
            $planFactoryWithOtherTypeExtractor->diagnose(Leaf::class, static fn (string $class): bool => false)->limitation,
        );
    }

    /**
     * Like AbstractObjectNormalizer::denormalizeParameter(): the native type array overrules a PHPDoc type unless that is an array, an
     * iterable or a collection.
     */
    #[Test]
    public function native_array_type_overrules_the_phpdoc_type_like_for_the_property_normalizer(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]), null, []);
        $hasPlan = static fn (string $class): bool => false;

        // -- Act
        $diagnoses = array_map(
            static fn (string $class): array => [
                $planFactory->diagnose($class, $hasPlan)->limitation?->describe(),
                $planFactory->diagnose($class, $hasPlan)->plan['parameters'][0][1] ?? null,
            ],
            [
                ArrayWithIterableDoc::class,
                ArrayWithCollectionClassDoc::class,
                ArrayWithIntOrIterableDoc::class,
            ],
        );

        // -- Assert
        self::assertSame(
            [
                [null, ParameterKind::ARRAY],
                ['collection is not an array: $values (ArrayObject<int, string>)', null],
                ['union type: $values (int|iterable)', null],
            ],
            $diagnoses,
        );
    }

    #[Test]
    public function type_that_can_not_be_resolved_leads_to_no_plan_instead_of_an_exception(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new ThrowingPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []);
        $hasPlan = static fn (string $class): bool => false;

        // -- Act
        $plan = $planFactory->create(Leaf::class, $hasPlan);
        $planDiagnosis = $planFactory->diagnose(Leaf::class, $hasPlan);

        // -- Assert
        self::assertNull($plan);
        self::assertNull($planDiagnosis->plan);
        self::assertEquals(
            new Diagnosis\PlanLimitation(
                Diagnosis\PlanLimitationReason::TYPE_IS_NOT_RESOLVABLE,
                'name',
                sprintf('The type of %s::$name must not be extracted.', Leaf::class),
            ),
            $planDiagnosis->limitation,
        );
    }
}
