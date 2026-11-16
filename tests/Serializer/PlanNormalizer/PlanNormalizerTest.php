<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\Other\Settings;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\AdditionalParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\ArrayTypeMismatch;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Base;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Box;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Boxed;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\CollectionClass;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\ConstructorTypeError;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Cycle;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\CycleBack;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\DefaultObject;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Generic;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Grove;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Hooked;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Inherited;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\IntersectionParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\IterableParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\KeyedCollections;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Kind;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Located;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Mutable;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\NameValue;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\NestedObjectCollections;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\NonPublicProperty;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\NoProperties;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\ObjectParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Objects;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\ObjectShape;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Optional;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\ParameterWithoutProperty;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\PrivateConstructor;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\ScalarLists;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Scalars;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\Tree;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\TypeMismatch;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\UnionParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\ValueObjects;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\WithSettings;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\WithStrictName;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\Count;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\Flag;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\PlainStatus;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\Point;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\StrictName;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ValueObject\Weight;
use DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\NullPropertyTypeExtractor;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\SerializerComparison;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\ThrowingPropertyTypeExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\Serializer;

require_once dirname(__DIR__, 2).'/Test/PlanNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * Shapes, contexts and the plan cache of the PlanNormalizer with the fixture classes (Fixture/ReadModel/fixtures.php). Like
 * PlanNormalizerEquivalenceTest, the results are compared with the PropertyNormalizer alone.
 */
#[CoversClass(PlanNormalizer::class)]
#[CoversClass(PlanFactory::class)]
#[CoversClass(Exception\FallbackIsRequired::class)]
#[CoversClass(Exception\TypeCanNotBePlanned::class)]
final class PlanNormalizerTest extends KernelTestCase
{
    #[Test]
    public function supported_shapes_are_handled_like_by_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $leaf = new Leaf('Example');
        /**
         * The inner levels behind a nullable level are keyed like the outer level, against their PHPDoc on purpose (see PlanFactory).
         *
         * @var array<string, mixed> $nestedObjectCollectionsArguments
         */
        $nestedObjectCollectionsArguments = [
            'leaves' => [[$leaf, new Leaf('Hain')], [], [$leaf]],
            'leavesByKey' => [
                'first' => [$leaf],
                'second' => [],
            ],
            'keyedLeaves' => [
                [
                    'first' => $leaf,
                ],
            ],
            'leavesByAnyKey' => [
                [
                    'first' => $leaf,
                    7 => $leaf,
                ],
            ],
            'cube' => [[[$leaf], []], []],
            'leavesWithGaps' => [[$leaf, null]],
            'optionalLeaves' => [[$leaf], null],
            // The keys of the inner level are checked against the key type of the outer level (see PlanFactory)
            'optionalLeavesByKey' => [
                'first' => [
                    'second' => $leaf,
                ],
                'third' => null,
            ],
            'optionalCube' => [[[$leaf], [new Leaf('Hain')]], null],
            // The keys of the innermost level are checked against the int keys of the outer level, not against its own string keys
            'optionalKeyedCube' => [[[$leaf, new Leaf('Hain')]]],
            'counts' => [[new Count(1), new Count(2)]],
            'moments' => [[new \DateTimeImmutable('2026-11-15 10:30:00')]],
            'generics' => [[new Generic($leaf)]],
            'anythings' => [['Example', 7], []],
        ];

        $samples = [
            new Scalars(
                'Example',
                7,
                2.5,
                true,
                ['any' => 'thing'],
                null,
                [
                    'first' => 1,
                    'second' => 'two',
                ],
                [1, 'two'],
                PlainStatus::ARCHIVED,
                null,
                new \DateTimeImmutable('2026-11-15 10:30:00'),
            ),
            new ScalarLists(
                [true, false],
                [[1, 2], [3]],
                [[1.5, 2.0], null],
                ['Anna', null],
            ),
            new ValueObjects(
                new Count(3),
                new Weight(2.5),
                new Flag(true),
                null,
                [new Count(1), new Count(2)],
                [
                    'first' => new Flag(true),
                    'second' => new Flag(false),
                ],
                [
                    'first' => new Weight(1.5),
                    7 => new Weight(2.0),
                ],
            ),
            new Grove(
                'Hain',
                $leaf,
                [$leaf, $leaf],
                [
                    'first' => $leaf,
                ],
                [
                    'first' => $leaf,
                    7 => $leaf,
                ],
                new Tree('Krone', new Tree('Stamm', null, $leaf, [], [], []), $leaf, [], [], []),
            ),
            new WithStrictName(new StrictName('Example'), new StrictName('Example')),
            new Objects(
                new \ArrayObject(),
                [new \DateTimeImmutable('2026-11-15 10:30:00'), new \DateTimeImmutable('2026-11-16 10:30:00')],
            ),
            // The PHPDoc type is wrong on purpose
            new \ReflectionClass(TypeMismatch::class)->newInstance(2.5),
            new Optional('Example', 7),
            new ConstructorTypeError(7),
            new Generic(new Leaf('Example')),
            new Boxed(new Box(new Leaf('Example'))),
            new Located(new Point(1, 2), new Point(3, 4), [new Point(5, 6)], [new StrictName('Example')]),
            new Located(new Point(1, 2), null, [], []),
            new WithSettings(new Settings(), new Tree('Krone', null, new Leaf('Example'), [], [], [])),
            new WithSettings(null, null),
            new KeyedCollections(
                [
                    [
                        'first' => new Count(1),
                    ],
                ],
                [
                    'first' => $leaf,
                    7 => $leaf,
                ],
            ),
            new \ReflectionClass(ArrayTypeMismatch::class)->newInstance(['Example']),
        ];
        $samples[] = new NestedObjectCollections(...$nestedObjectCollectionsArguments);
        // Every nested collection on its own as well: the quirky key checks of one parameter would make the whole object fall back and
        // hide a plan that accepts more than the PropertyNormalizer in another one
        foreach ($nestedObjectCollectionsArguments as $name => $argument) {
            $samples[] = new NestedObjectCollections(...[$name => $argument] + array_fill_keys(array_keys($nestedObjectCollectionsArguments), []));
        }

        // -- Act
        $differences = [];
        foreach ($samples as $sample) {
            $class = $sample::class;
            $differences[] = self::differenceOfNormalization($comparison, $sample);

            $normalizedData = $comparison->reference->normalize($sample);
            self::assertIsArray($normalizedData);
            $jsonData = json_decode(json_encode($normalizedData, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($jsonData);
            foreach (['normalized' => $normalizedData, 'json' => $jsonData] as $dataName => $data) {
                foreach ([null, JsonEncoder::FORMAT] as $format) {
                    $differences[] = self::differenceOfDenormalization($comparison, $data, $class, $format, $dataName);
                    foreach (array_keys($data) as $key) {
                        foreach (SerializerComparison::mutations($data, (string) $key) as $mutationName => $mutatedData) {
                            $differences[] = self::differenceOfDenormalization($comparison, $mutatedData, $class, $format, sprintf('%s $%s %s', $dataName, $key, $mutationName));
                        }
                    }
                }
            }
        }

        // -- Assert
        self::assertSame([], array_values(array_filter($differences)));
    }

    #[Test]
    public function valid_data_is_handled_by_the_plan_itself(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $leaf = new Leaf('Example');
        /**
         * The inner levels behind a nullable level are keyed like the outer level, against their PHPDoc on purpose (see PlanFactory).
         *
         * @var array<string, mixed> $nestedObjectCollectionsArguments
         */
        $nestedObjectCollectionsArguments = [
            'leaves' => [[$leaf], []],
            'leavesByKey' => [
                'first' => [$leaf],
            ],
            'keyedLeaves' => [
                [
                    'first' => $leaf,
                ],
            ],
            'leavesByAnyKey' => [
                [
                    'first' => $leaf,
                    7 => $leaf,
                ],
            ],
            'cube' => [[[$leaf]]],
            'leavesWithGaps' => [[$leaf]],
            'optionalLeaves' => [[$leaf]],
            'optionalLeavesByKey' => [
                'first' => [
                    'second' => $leaf,
                ],
            ],
            'optionalCube' => [[[$leaf]]],
            'optionalKeyedCube' => [[[$leaf]]],
            'counts' => [[new Count(1)]],
            'moments' => [[new \DateTimeImmutable('2026-11-15 10:30:00')]],
            'generics' => [[new Generic($leaf)]],
            'anythings' => [['Example', 7]],
        ];

        $samples = [
            new ScalarLists([true], [[1]], [[1.5], null], ['Anna', null]),
            new ValueObjects(
                new Count(3),
                new Weight(2.5),
                new Flag(true),
                new Weight(3.5),
                [new Count(1)],
                [
                    'first' => new Flag(true),
                ],
                [
                    'first' => new Weight(1.5),
                    7 => new Weight(2.0),
                ],
            ),
            new Grove('Hain', $leaf, [$leaf], ['first' => $leaf], [7 => $leaf], null),
            new NestedObjectCollections(...$nestedObjectCollectionsArguments),
        ];

        // -- Act & Assert
        foreach ($samples as $sample) {
            $normalizedData = $comparison->planOnly->normalize($sample);
            self::assertEquals($sample, $comparison->planOnly->denormalize($normalizedData, $sample::class));
            self::assertEquals($sample, $comparison->planOnly->denormalize(
                json_decode(json_encode($normalizedData, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
                $sample::class,
                JsonEncoder::FORMAT,
            ));
        }
    }

    #[Test]
    public function serializer_exceptions_of_value_objects_lead_to_the_same_result_as_with_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $variants = [];
        foreach (['unsupported', 'not normalizable', 'invalid argument', 'missing arguments', 'extra attributes'] as $invalidName) {
            $variants[] = [
                'name' => $invalidName,
                'otherName' => 'Example',
            ];
            $variants[] = [
                'name' => 'Example',
                'otherName' => $invalidName,
            ];
        }

        // -- Act
        $differences = [];
        foreach ($variants as $data) {
            $differences[] = self::differenceOfDenormalization($comparison, $data, WithStrictName::class, null, json_encode($data, JSON_THROW_ON_ERROR));
        }

        // -- Assert
        self::assertSame([], array_values(array_filter($differences)));
        // For the nullable parameter, the PropertyNormalizer turns the UnsupportedException into its own exception
        self::assertSame(
            ['exception', NotNormalizableValueException::class],
            array_slice(SerializerComparison::outcome(static fn () => $comparison->plan->denormalize($variants[0], WithStrictName::class)), 0, 2),
        );
    }

    #[Test]
    public function nested_collections_are_planned_with_key_checks_per_level(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $plan = new \ReflectionMethod(PlanNormalizer::class, 'plan');

        // -- Act
        /**
         * @var array{
         *   parameters: list<list{
         *     0: string,
         *     1: int,
         *     2: ?class-string,
         *     3: bool,
         *     4: bool,
         *     5: mixed,
         *     6: mixed,
         *   }>,
         *   properties: list<string>,
         *   isNormalizable: bool,
         * }|false $planOfNestedObjectCollections
         */
        $planOfNestedObjectCollections = $plan->invoke($comparison->planNormalizer, NestedObjectCollections::class);

        // -- Assert
        self::assertIsArray($planOfNestedObjectCollections);
        self::assertTrue($planOfNestedObjectCollections['isNormalizable']);
        self::assertSame(
            [
                'leaves' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::INT]],
                'leavesByKey' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::STRING, KeyCheck::INT]],
                'keyedLeaves' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::STRING]],
                'leavesByAnyKey' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::UNCHECKED]],
                'cube' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::INT, KeyCheck::INT]],
                'leavesWithGaps' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::INT]],
                // From a nullable inner level on, the ArrayDenormalizer checks every level against the key type of the level before
                'optionalLeaves' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::INT]],
                'optionalLeavesByKey' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::STRING, KeyCheck::STRING]],
                'optionalCube' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::INT, KeyCheck::INT]],
                // The innermost level would be STRING if the key types were taken per level
                'optionalKeyedCube' => [ParameterKind::NESTED_LIST, Leaf::class, [KeyCheck::INT, KeyCheck::INT, KeyCheck::INT]],
                'counts' => [ParameterKind::OBJECT_LIST, Count::class, '[][]'],
                'moments' => [ParameterKind::OBJECT_LIST, \DateTimeImmutable::class, '[][]'],
                'generics' => [ParameterKind::NESTED_LIST, Generic::class, [KeyCheck::INT, KeyCheck::INT]],
                'anythings' => [ParameterKind::ARRAY, null, null],
            ],
            array_combine(
                array_column($planOfNestedObjectCollections['parameters'], 0),
                array_map(
                    static fn (array $parameterPlan): array => [
                        $parameterPlan[1],
                        $parameterPlan[2],
                        $parameterPlan[1] === ParameterKind::OBJECT_LIST
                            ? $parameterPlan[6][2]
                            : $parameterPlan[6],
                    ],
                    $planOfNestedObjectCollections['parameters'],
                ),
            ),
        );
    }

    #[Test]
    public function recursive_classes_are_left_to_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $leaf = new Leaf('Example');
        $tree = new Tree('Krone', new Tree('Stamm', new Tree('Wurzel', null, $leaf, [], [], []), $leaf, [], [], []), $leaf, [], [], []);
        $data = $comparison->reference->normalize(new Grove('Hain', $leaf, [], [], [], $tree));
        self::assertIsArray($data);
        // Invalid only deep down
        $data['tree']['parent']['parent']['leaf']['name'] = 7;

        // -- Act
        $isTreeSupported = $comparison->planNormalizer->supportsDenormalization([], Tree::class);
        $isCycleSupported = $comparison->planNormalizer->supportsDenormalization([], Cycle::class);
        $isCycleBackSupported = $comparison->planNormalizer->supportsDenormalization([], CycleBack::class);
        $isGroveSupported = $comparison->planNormalizer->supportsDenormalization([], Grove::class);

        // -- Assert
        self::assertFalse($isTreeSupported);
        self::assertFalse($isCycleSupported);
        self::assertFalse($isCycleBackSupported);
        // A class referencing a recursive class is planned, the recursive class is denormalized through the serializer
        self::assertTrue($isGroveSupported);
        self::assertNull(self::differenceOfDenormalization($comparison, $data, Grove::class, null, 'invalid deep down'));
    }

    #[Test]
    public function an_object_that_is_already_denormalized_is_kept(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $countable = new \ArrayObject(['Example']);
        $data = [
            'countable' => $countable,
            'moments' => [],
        ];

        // -- Act
        $objects = $comparison->planOnly->denormalize($data, Objects::class);

        // -- Assert
        self::assertInstanceOf(Objects::class, $objects);
        self::assertSame($countable, $objects->countable);
    }

    #[Test]
    public function unsupported_shapes_are_left_to_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $classes = [
            PrivateConstructor::class,
            NonPublicProperty::class,
            Base::class,
            Inherited::class,
            ParameterWithoutProperty::class,
            AdditionalParameter::class,
            UnionParameter::class,
            IntersectionParameter::class,
            DefaultObject::class,
            NoProperties::class,
            ObjectParameter::class,
            IterableParameter::class,
            CollectionClass::class,
            Hooked::class,
            Tree::class,
            Cycle::class,
            CycleBack::class,
            ObjectShape::class,
            NameValue::class,
            Kind::class,
            \DateTimeImmutable::class,
            'DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\ReadModel\DoesNotExist',
            Leaf::class.'[]',
        ];

        // -- Act
        $supportedClasses = array_values(array_filter(
            $classes,
            static fn (string $class): bool => $comparison->planNormalizer->supportsDenormalization([], $class),
        ));

        // -- Assert
        self::assertSame([], $supportedClasses);
        self::assertTrue($comparison->planNormalizer->supportsDenormalization([], Leaf::class));
    }

    #[Test]
    public function class_with_property_without_type_is_left_to_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $planNormalizer = new PlanNormalizer(
            new PlanFactory(new NullPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []),
            $comparison->planNormalizer,
            null,
            'test',
            $comparison->defaultContext,
        );

        // -- Act
        $isSupported = $planNormalizer->supportsDenormalization(['name' => 'Example'], Leaf::class);

        // -- Assert
        self::assertFalse($isSupported);
    }

    #[Test]
    public function mutable_class_is_only_denormalized_by_the_plan(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $mutable = new Mutable('Example');

        // -- Act
        $isNormalizationSupported = $comparison->planNormalizer->supportsNormalization($mutable);
        $isDenormalizationSupported = $comparison->planNormalizer->supportsDenormalization(['name' => 'Example'], Mutable::class);
        $normalizedData = $comparison->planNormalizer->normalize($mutable);

        // -- Assert
        self::assertFalse($isNormalizationSupported);
        self::assertTrue($isDenormalizationSupported);
        // Through the fallback
        self::assertSame(['name' => 'Example'], $normalizedData);
    }

    #[Test]
    public function contexts_and_formats_the_plan_does_not_implement_are_left_to_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $leaf = new Leaf('Example');
        $data = ['name' => 'Example'];
        $unsupportedContexts = [
            [AbstractNormalizer::OBJECT_TO_POPULATE => $leaf],
            [AbstractNormalizer::GROUPS => ['group']],
            [AbstractNormalizer::ATTRIBUTES => ['name']],
            [AbstractNormalizer::IGNORED_ATTRIBUTES => ['name']],
            [AbstractNormalizer::CALLBACKS => ['name' => static fn (): string => 'Hof']],
            [AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [Leaf::class => ['name' => 'Hof']]],
            [AbstractObjectNormalizer::MAX_DEPTH_HANDLER => static fn (): null => null],
            [PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC],
            [UnwrappingDenormalizer::UNWRAP_PATH => '[data]'],
            ['not_normalizable_value_exceptions' => []],
            ['extra_attributes_exceptions' => []],
            ['_union_member' => true],
            [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true],
            [AbstractNormalizer::FILTER_BOOL => true],
            [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true],
            [AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION => true],
            [AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => true],
            [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true],
            [Serializer::EMPTY_ARRAY_AS_OBJECT => true],
            [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        ];
        $supportedContexts = [
            [],
            [AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION => false],
            [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true],
            [AbstractObjectNormalizer::SKIP_NULL_VALUES => true],
            ['deserialization_path' => 'leaf'],
        ];
        $planNormalizerWithUnsupportedDefaultContext = new PlanNormalizer(
            $comparison->planFactory,
            $comparison->planNormalizer,
            null,
            'test',
            [AbstractNormalizer::GROUPS => ['group']],
        );

        // -- Act & Assert
        foreach ($unsupportedContexts as $context) {
            self::assertFalse($comparison->planNormalizer->supportsDenormalization($data, Leaf::class, null, $context), (string) json_encode(array_keys($context)));
            self::assertFalse($comparison->planNormalizer->supportsNormalization($leaf, null, $context), (string) json_encode(array_keys($context)));
        }
        foreach ($supportedContexts as $context) {
            self::assertTrue($comparison->planNormalizer->supportsDenormalization($data, Leaf::class, null, $context), (string) json_encode(array_keys($context)));
            self::assertTrue($comparison->planNormalizer->supportsNormalization($leaf, null, $context), (string) json_encode(array_keys($context)));
        }

        // XML and CSV enable the type conversion of the PropertyNormalizer
        self::assertFalse($comparison->planNormalizer->supportsDenormalization($data, Leaf::class, 'xml'));
        self::assertFalse($comparison->planNormalizer->supportsDenormalization($data, Leaf::class, 'csv'));
        self::assertTrue($comparison->planNormalizer->supportsDenormalization($data, Leaf::class, 'json'));

        self::assertFalse($comparison->planNormalizer->supportsDenormalization('Example', Leaf::class));
        self::assertFalse($comparison->planNormalizer->supportsNormalization('Example'));
        self::assertFalse($planNormalizerWithUnsupportedDefaultContext->supportsDenormalization($data, Leaf::class));
        self::assertSame(['object' => false], $comparison->planNormalizer->getSupportedTypes(null));
    }

    #[Test]
    public function direct_calls_with_data_the_plan_does_not_handle_use_the_fallback(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $planNormalizer = $comparison->planNormalizer;

        // -- Act & Assert
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->denormalize('Example', Optional::class)),
            SerializerComparison::outcome(static fn () => $planNormalizer->denormalize('Example', Optional::class)),
        );
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->denormalize(['name' => 'Example'], Inherited::class)),
            SerializerComparison::outcome(static fn () => $planNormalizer->denormalize(['name' => 'Example'], Inherited::class)),
        );
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->normalize(new Inherited('Example'))),
            SerializerComparison::outcome(static fn () => $planNormalizer->normalize(new Inherited('Example'))),
        );
        // Not through a serializer, which handles scalars itself: like calling the fallback directly
        $fallbackNormalizer = new \ReflectionProperty(PlanNormalizer::class, 'fallbackNormalizer')->getValue($planNormalizer);
        self::assertInstanceOf(PropertyNormalizer::class, SerializerComparison::unwrap($fallbackNormalizer));
        self::assertSame(
            SerializerComparison::outcome(static fn () => $fallbackNormalizer->normalize('Example')),
            SerializerComparison::outcome(static fn () => $planNormalizer->normalize('Example')),
        );
    }

    #[Test]
    public function null_values_are_skipped_and_empty_objects_are_preserved_like_by_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $optional = new Optional(null, null);
        $partlyOptional = new Optional('Example', null);
        $context = [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
        ];

        // -- Act & Assert
        foreach ([$optional, $partlyOptional] as $sample) {
            self::assertSame(
                SerializerComparison::outcome(static fn () => $comparison->reference->normalize($sample, null, $context)),
                SerializerComparison::outcome(static fn () => $comparison->planOnly->normalize($sample, null, $context)),
            );
        }
        self::assertInstanceOf(\ArrayObject::class, $comparison->planOnly->normalize($optional, null, $context));
    }

    #[Test]
    public function values_that_can_not_be_normalized_fail_like_with_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $resource = fopen('php://memory', 'rb');
        $optional = new Optional('Example', $resource);

        // -- Act
        $referenceOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->normalize($optional));
        $planOutcome = SerializerComparison::outcome(static fn () => $comparison->plan->normalize($optional));

        // -- Assert
        self::assertSame('exception', $referenceOutcome[0]);
        self::assertSame($referenceOutcome, $planOutcome);
    }

    #[Test]
    public function plans_are_cached_across_instances(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $cacheDirectory = sprintf('%s/plan-normalizer-test-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
        // Same adapter as on prod (var/tmp), so the plans must be exportable by the VarExporter
        $planCache = new PhpFilesAdapter('plans', 0, $cacheDirectory);
        $leaf = new Leaf('Example');
        $samples = [
            new Grove('Hain', $leaf, [$leaf], ['first' => $leaf], [7 => $leaf], null),
            new Objects(new \ArrayObject(), [new \DateTimeImmutable('2026-11-15 10:30:00')]),
            new Scalars('Example', 7, 2.5, true, null, null, [], [], PlainStatus::ACTIVE, null, new \DateTimeImmutable('2026-11-15 10:30:00')),
        ];

        try {
            $writingSerializer = $comparison->serializerWith(
                static fn (PlanFactory $planFactory, array $defaultContext): PlanNormalizer => new PlanNormalizer($planFactory, $comparison->planNormalizer, $planCache, 'test', $defaultContext),
            );
            foreach ($samples as $sample) {
                $writingSerializer->denormalize($comparison->reference->normalize($sample), $sample::class);
            }

            $readingPlanNormalizers = [];
            $readingSerializer = $comparison->serializerWith(
                static function (PlanFactory $planFactory, array $defaultContext) use ($comparison, $planCache, &$readingPlanNormalizers): PlanNormalizer {
                    $planNormalizer = new PlanNormalizer(
                        new PlanFactory(new ThrowingPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []),
                        $comparison->planNormalizer,
                        $planCache,
                        'test',
                        $defaultContext,
                    );
                    $readingPlanNormalizers[] = $planNormalizer;

                    return $planNormalizer;
                },
            );

            // -- Act
            $outcomes = [];
            foreach ($samples as $sample) {
                $outcomes[] = SerializerComparison::outcome(static fn () => $readingSerializer->denormalize($comparison->reference->normalize($sample), $sample::class));
                $outcomes[] = SerializerComparison::outcome(static fn () => $readingSerializer->normalize($sample));
            }
            $referenceOutcomes = [];
            foreach ($samples as $sample) {
                $referenceOutcomes[] = SerializerComparison::outcome(static fn () => $comparison->reference->denormalize($comparison->reference->normalize($sample), $sample::class));
                $referenceOutcomes[] = SerializerComparison::outcome(static fn () => $comparison->reference->normalize($sample));
            }
            // A class that isn't cached can't be planned with the throwing type extractor, so it's left to the PropertyNormalizer
            $isUncachedClassSupported = $readingPlanNormalizers[0]->supportsDenormalization([], Optional::class);
            // Only possible with the plans from the cache
            $areCachedClassesSupported = array_map(
                static fn (object $sample): bool => $readingPlanNormalizers[0]->supportsDenormalization([], $sample::class),
                $samples,
            );
        } finally {
            new Filesystem()->remove($cacheDirectory);
        }

        // -- Assert
        // The type extractor of the reading normalizer throws, so the plans must come from the cache
        self::assertCount(1, $readingPlanNormalizers);
        self::assertSame($referenceOutcomes, $outcomes);
        self::assertFalse($isUncachedClassSupported);
        self::assertSame([true, true, true], $areCachedClassesSupported);
    }

    #[Test]
    public function valid_data_with_missing_optional_values_and_ints_for_floats_is_handled_by_the_plan_itself(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $cases = [
            // A missing nullable value (null) in front of another value
            [Optional::class, ['anything' => 'Example'], null],
            // A missing value with default in front of another value with default
            [Scalars::class, [
                'name' => 'Example',
                'count' => 7,
                'amount' => 2.5,
                'isActive' => true,
                'anything' => null,
                'comment' => null,
                'map' => [],
                'mixedList' => [],
                'status' => 'ACTIVE',
                'optionalStatus' => null,
                'createdAt' => '2026-11-15T10:30:00+00:00',
                'withDefault' => 'Other',
            ], null],
            // JSON has no distinction between int and float
            [Scalars::class, [
                'name' => 'Example',
                'count' => 7,
                'amount' => 3,
                'isActive' => true,
                'anything' => null,
                'comment' => null,
                'map' => [],
                'mixedList' => [],
                'status' => 'ACTIVE',
                'optionalStatus' => null,
                'createdAt' => '2026-11-15T10:30:00+00:00',
            ], JsonEncoder::FORMAT],
        ];

        // -- Act & Assert
        foreach ($cases as [$class, $data, $format]) {
            $referenceOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->denormalize($data, $class, $format));
            self::assertSame('result', $referenceOutcome[0]);
            self::assertSame($referenceOutcome, SerializerComparison::outcome(static fn () => $comparison->planOnly->denormalize($data, $class, $format)));
        }
    }

    #[Test]
    public function null_values_and_empty_objects_follow_the_default_context_unless_the_context_overrules_it(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $cases = [
            // Default context => context => expected result
            [[], [], ['comment' => null, 'anything' => null]],
            [[AbstractObjectNormalizer::SKIP_NULL_VALUES => true], [], []],
            [[AbstractObjectNormalizer::SKIP_NULL_VALUES => true], [AbstractObjectNormalizer::SKIP_NULL_VALUES => false], ['comment' => null, 'anything' => null]],
            [[AbstractObjectNormalizer::SKIP_NULL_VALUES => true, AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true], [], new \ArrayObject()],
            [[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => false], [AbstractObjectNormalizer::SKIP_NULL_VALUES => true, AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true], new \ArrayObject()],
            [[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true], [AbstractObjectNormalizer::SKIP_NULL_VALUES => true, AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => false], []],
        ];

        // -- Act & Assert
        foreach ($cases as [$defaultContext, $context, $expectedResult]) {
            $planNormalizer = new PlanNormalizer($comparison->planFactory, $comparison->planNormalizer, null, 'test', $defaultContext);
            self::assertEquals($expectedResult, $planNormalizer->normalize(new Optional(null, null), null, $context), (string) json_encode([$defaultContext, $context]));
        }

        // Values after a skipped null value are kept
        $planNormalizer = new PlanNormalizer($comparison->planFactory, $comparison->planNormalizer, null, 'test', []);
        self::assertSame(['anything' => 'Example'], $planNormalizer->normalize(new Optional(null, 'Example'), null, [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]));
    }

    #[Test]
    public function unsupported_options_of_the_default_context_are_overruled_by_the_context(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $data = ['name' => 'Example'];
        $planNormalizer = static fn (array $defaultContext): PlanNormalizer => new PlanNormalizer($comparison->planFactory, $comparison->planNormalizer, null, 'test', $defaultContext);

        // -- Act & Assert
        self::assertFalse($planNormalizer([AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true])->supportsDenormalization($data, Leaf::class));
        self::assertTrue($planNormalizer([AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => false]));
        self::assertFalse($planNormalizer([AbstractNormalizer::REQUIRE_ALL_PROPERTIES => false])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true]));
        self::assertFalse($planNormalizer([AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])->supportsDenormalization($data, Leaf::class));
        self::assertTrue($planNormalizer([AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true]));
        self::assertFalse($planNormalizer([AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false]));
    }

    // -- Helpers

    private function comparison(): SerializerComparison
    {
        self::bootKernel();

        $serializer = self::getContainer()->get('serializer');
        self::assertInstanceOf(DenormalizerInterface::class, $serializer);

        return SerializerComparison::fromSerializer($serializer);
    }

    /**
     * @return string|null Description of the difference
     */
    private static function differenceOfNormalization(
        SerializerComparison $comparison,
        object $sample,
    ): ?string {
        $referenceOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->normalize($sample));
        $planOutcome = SerializerComparison::outcome(static fn () => $comparison->plan->normalize($sample));

        return $referenceOutcome !== $planOutcome
            ? sprintf('%s: normalize differs: %s vs %s', $sample::class, json_encode($referenceOutcome, JSON_THROW_ON_ERROR), json_encode($planOutcome, JSON_THROW_ON_ERROR))
            : null;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param class-string            $class
     *
     * @return string|null Description of the difference
     */
    private static function differenceOfDenormalization(
        SerializerComparison $comparison,
        array $data,
        string $class,
        ?string $format,
        string $variant,
    ): ?string {
        $referenceOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->denormalize($data, $class, $format));
        $planOutcome = SerializerComparison::outcome(static fn () => $comparison->plan->denormalize($data, $class, $format));

        return $referenceOutcome !== $planOutcome
            ? sprintf('%s (%s, format %s): denormalize differs: %s vs %s', $class, $variant, $format ?? 'null', json_encode($referenceOutcome, JSON_THROW_ON_ERROR), json_encode($planOutcome, JSON_THROW_ON_ERROR))
            : null;
    }
}
