<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizableNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\SomeTrait;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Diagnosis\ParameterPassedByReference;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Diagnosis\UntypedParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Diagnosis\VariadicParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\ThrowingPropertyTypeExtractor;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\AdditionalParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Base;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Boxed;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Described;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Grove;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Hooked;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Inherited;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\IntersectionParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Kind;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\NonPublicProperty;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ParameterWithoutProperty;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\PrivateConstructor;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ReadModelFailed;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ValueObjects;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithCollectionClassDoc;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithIntOrIterableDoc;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Sample\ArrayWithIterableDoc;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\NullPropertyTypeExtractor;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Union\WithStaticProperty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;

require_once dirname(__DIR__, 2).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';
require_once dirname(__DIR__, 2).'/Test/KnownConstructionNormalizer/Union/fixtures.php';
require_once dirname(__DIR__, 2).'/Test/KnownConstructionNormalizer/Diagnosis/fixtures.php';

/**
 * The plans and limitations the PlanFactory diagnoses (which the profiler panel shows), including the ones the configured
 * KnownConstructionNormalizer can't reach (type extractors other than the one of the PropertyNormalizer).
 */
#[CoversClass(PlanFactory::class)]
#[CoversClass(Diagnosis\PlanDiagnosis::class)]
#[CoversClass(Diagnosis\PlanLimitation::class)]
#[CoversClass(Diagnosis\PlanLimitationReason::class)]
#[CoversClass(Exception\TypeCanNotBePlanned::class)]
final class PlanFactoryTest extends TestCase
{
    #[Test]
    public function limitations_of_kinds_of_classes_are_diagnosed_by_the_plan_factory(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new NullPropertyTypeExtractor());
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
    public function classes_that_are_no_read_models_get_no_plan(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new NullPropertyTypeExtractor());
        $hasPlan = static fn (string $class): bool => false;
        $anonymousClass = new readonly class('Example') {
            public function __construct(
                public string $name,
            ) {
            }
        };

        // -- Act & Assert
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::EXCEPTION), $planFactory->diagnose(ReadModelFailed::class, $hasPlan)->limitation);
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::ANONYMOUS_CLASS), $planFactory->diagnose($anonymousClass::class, $hasPlan)->limitation);
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::INTERNAL_CLASS), $planFactory->diagnose(\Closure::class, $hasPlan)->limitation);
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::INTERNAL_CLASS), $planFactory->diagnose(\Generator::class, $hasPlan)->limitation);
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::INTERNAL_CLASS), $planFactory->diagnose(\DateTimeImmutable::class, $hasPlan)->limitation);
        self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::INTERNAL_CLASS), $planFactory->diagnose(\stdClass::class, $hasPlan)->limitation);
        foreach (['int', 'Example[]', 'DigitalCraftsman\\SelfAwareNormalizers\\DoesNotExist', SomeTrait::class] as $type) {
            /** @var class-string $type */
            self::assertEquals(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::NOT_A_CLASS), $planFactory->diagnose($type, $hasPlan)->limitation, $type);
        }
    }

    /**
     * The first limitation found is the one reported, so each shape is diagnosed with the reason that matches it (and not with a later one
     * that would catch it as well).
     */
    #[Test]
    public function every_shape_of_a_class_without_plan_is_diagnosed_with_its_reason(): void
    {
        // -- Arrange
        // The BackedEnumNormalizer supports every enum, but an enum is reported as enum and not as claimed
        $planFactory = new PlanFactory(
            new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
            new NormalizerChain([
                'serializer.normalizer.backed_enum' => new BackedEnumNormalizer(),
            ]),
        );
        $hasPlan = static fn (string $class): bool => false;

        // -- Act
        $descriptions = [];
        foreach ([
            Kind::class,
            Base::class,
            PrivateConstructor::class,
            NonPublicProperty::class,
            ParameterWithoutProperty::class,
            Hooked::class,
            Inherited::class,
            VariadicParameter::class,
            ParameterPassedByReference::class,
            AdditionalParameter::class,
            UntypedParameter::class,
            IntersectionParameter::class,
        ] as $class) {
            $descriptions[$class] = $planFactory->diagnose($class, $hasPlan)->limitation?->describe();
        }

        // -- Assert
        self::assertSame(
            [
                Kind::class => 'enum',
                Base::class => 'abstract class',
                PrivateConstructor::class => 'constructor is not public',
                NonPublicProperty::class => 'property is not public: $secret',
                ParameterWithoutProperty::class => 'property is not promoted: $upperCaseName',
                Hooked::class => 'property has hooks: $name',
                Inherited::class => sprintf('property is inherited: $name (declared in %s)', Base::class),
                VariadicParameter::class => 'parameter is variadic: $names',
                ParameterPassedByReference::class => 'parameter is passed by reference: $reference',
                AdditionalParameter::class => 'parameter is not promoted: $unused',
                UntypedParameter::class => 'parameter has no type: $name',
                IntersectionParameter::class => 'intersection type: $value (Countable&Traversable)',
            ],
            $descriptions,
        );
    }

    /**
     * Value objects are handled inline and classes with a plan are denormalized through it, the profiler panel lists only the other
     * classes as delegated to the serializer.
     */
    #[Test]
    public function parameters_are_planned_by_the_kind_of_their_type(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
        $hasPlan = static fn (string $class): bool => true;

        // -- Act
        $kinds = [];
        foreach ([ValueObjects::class, Grove::class, Boxed::class] as $class) {
            foreach ($planFactory->create($class, $hasPlan)['parameters'] ?? [] as [$name, $kind]) {
                $kinds[$class][$name] = $kind;
            }
        }

        // -- Assert
        self::assertSame(
            [
                ValueObjects::class => [
                    'count' => ParameterKind::VALUE_OBJECT_INT,
                    'weight' => ParameterKind::VALUE_OBJECT_FLOAT,
                    'flag' => ParameterKind::VALUE_OBJECT_BOOL,
                    'optionalWeight' => ParameterKind::VALUE_OBJECT_FLOAT,
                    'counts' => ParameterKind::VALUE_OBJECT_LIST,
                    'flagsByKey' => ParameterKind::VALUE_OBJECT_LIST,
                    'weightsByAnyKey' => ParameterKind::VALUE_OBJECT_LIST,
                ],
                Grove::class => [
                    'name' => ParameterKind::STRING,
                    'leaf' => ParameterKind::NESTED,
                    'leaves' => ParameterKind::NESTED_LIST,
                    'leavesByKey' => ParameterKind::NESTED_LIST,
                    'leavesByAnyKey' => ParameterKind::NESTED_LIST,
                    'tree' => ParameterKind::NESTED,
                ],
                // The generic type is unwrapped to its class
                Boxed::class => [
                    'box' => ParameterKind::NESTED,
                ],
            ],
            $kinds,
        );
    }

    #[Test]
    public function static_properties_are_no_part_of_the_plan(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));

        // -- Act
        $plan = $planFactory->create(WithStaticProperty::class, static fn (string $class): bool => false);

        // -- Assert
        self::assertSame(['name'], $plan['properties'] ?? null);
    }

    #[Test]
    public function factory_with_another_type_extractor_keeps_the_normalizer_chain(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new NullPropertyTypeExtractor(), new NormalizerChain([
            'app.normalizer' => new StringNormalizableNormalizer(),
        ]));

        // -- Act
        $planFactoryWithOtherTypeExtractor = $planFactory->withPropertyTypeExtractor(new ThrowingPropertyTypeExtractor());

        // -- Assert
        self::assertEquals(
            new \ReflectionProperty(PlanFactory::class, 'normalizerChain')->getValue($planFactory),
            new \ReflectionProperty(PlanFactory::class, 'normalizerChain')->getValue($planFactoryWithOtherTypeExtractor),
        );
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
        $planFactory = new PlanFactory(new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
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
                // The PHPDoc type is a union, whose values the PropertyNormalizer tries member by member: falls back when set
                [null, ParameterKind::UNION],
            ],
            $diagnoses,
        );
    }

    #[Test]
    public function type_that_can_not_be_resolved_leads_to_no_plan_instead_of_an_exception(): void
    {
        // -- Arrange
        $planFactory = new PlanFactory(new ThrowingPropertyTypeExtractor());
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
