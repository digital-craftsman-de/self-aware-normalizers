<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\SerializerComparison;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence\ThrowingPropertyTypeExtractor;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\Other\Settings;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\AdditionalParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ArrayTypeMismatch;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Base;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Box;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Boxed;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\CollectionClass;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ConstructorTypeError;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Cycle;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\CycleBack;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\DefaultObject;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Generic;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Grove;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Hooked;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Inherited;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\IntersectionParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\IterableParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\KeyedCollections;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Kind;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Leaf;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Located;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Mutable;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\NameValue;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\NestedObjectCollections;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\NonPublicProperty;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\NoProperties;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ObjectParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Objects;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ObjectShape;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Optional;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ParameterWithoutProperty;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\PrivateConstructor;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ScalarLists;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Scalars;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\Tree;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\TypeMismatch;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\UnionParameter;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\ValueObjects;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\WithSettings;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\WithStrictName;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Count;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Flag;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\PlainStatus;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Point;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\StrictName;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ValueObject\Weight;
use DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\NullPropertyTypeExtractor;
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

require_once dirname(__DIR__, 2).'/Test/KnownConstructionNormalizer/Fixture/ReadModel/fixtures.php';

/**
 * Shapes, contexts and the plan cache of the KnownConstructionNormalizer with the fixture classes (Fixture/ReadModel/fixtures.php). Like
 * KnownConstructionNormalizerEquivalenceTest, the results are compared with the PropertyNormalizer alone.
 */
#[CoversClass(KnownConstructionNormalizer::class)]
#[CoversClass(PlanFactory::class)]
#[CoversClass(Exception\FallbackIsRequired::class)]
#[CoversClass(Exception\TypeCanNotBePlanned::class)]
final class KnownConstructionNormalizerTest extends KernelTestCase
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
        $plan = new \ReflectionMethod(KnownConstructionNormalizer::class, 'plan');

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
        $planOfNestedObjectCollections = $plan->invoke($comparison->knownConstructionNormalizer, NestedObjectCollections::class);

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
        $isTreeSupported = $comparison->knownConstructionNormalizer->supportsDenormalization([], Tree::class);
        $isCycleSupported = $comparison->knownConstructionNormalizer->supportsDenormalization([], Cycle::class);
        $isCycleBackSupported = $comparison->knownConstructionNormalizer->supportsDenormalization([], CycleBack::class);
        $isGroveSupported = $comparison->knownConstructionNormalizer->supportsDenormalization([], Grove::class);

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
            'DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Fixture\ReadModel\DoesNotExist',
            Leaf::class.'[]',
        ];

        // -- Act
        $supportedClasses = array_values(array_filter(
            $classes,
            static fn (string $class): bool => $comparison->knownConstructionNormalizer->supportsDenormalization([], $class),
        ));

        // -- Assert
        self::assertSame([], $supportedClasses);
        self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization([], Leaf::class));
    }

    #[Test]
    public function class_with_property_without_type_is_left_to_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $knownConstructionNormalizer = new KnownConstructionNormalizer(
            new PlanFactory(new NullPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []),
            $comparison->knownConstructionNormalizer,
            null,
            'test',
            $comparison->defaultContext,
        );

        // -- Act
        $isSupported = $knownConstructionNormalizer->supportsDenormalization(['name' => 'Example'], Leaf::class);

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
        $isNormalizationSupported = $comparison->knownConstructionNormalizer->supportsNormalization($mutable);
        $isDenormalizationSupported = $comparison->knownConstructionNormalizer->supportsDenormalization(['name' => 'Example'], Mutable::class);
        $normalizedData = $comparison->knownConstructionNormalizer->normalize($mutable);

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
            [AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => false],
            // Not on the allowlist: an option the plan doesn't know (e.g. one of a later version of the serializer) ...
            ['option_of_a_later_version' => false],
            ['option_of_a_later_version' => null],
            // ... and the options of the circular reference handling, which the plan doesn't implement
            [AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => static fn (): null => null],
            [AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT => 2],
            // Options with an accepted value only with exactly that value
            [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => 1],
            [AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION => 0],
            // Unknown together with known options
            ['deserialization_path' => 'leaf', 'option_of_a_later_version' => true],
        ];
        $supportedContexts = [
            [],
            [AbstractObjectNormalizer::SKIP_NULL_VALUES => true],
            [AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true],
            [AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY => ['option']],
            // Options with an accepted value, with that value or null (like not set)
            [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true],
            [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => null],
            [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => false],
            [AbstractNormalizer::FILTER_BOOL => false],
            [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false],
            [AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION => false],
            [AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => false],
            [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => false],
            [AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => true],
            [Serializer::EMPTY_ARRAY_AS_OBJECT => false],
            // Internal options of the serializer and its normalizers
            [
                'cache_key' => 'key',
                'deserialization_path' => 'leaf',
                'key_type' => null,
                'value_type' => null,
                '_read_attributes' => false,
                'circular_reference_limit_counters' => [],
                'debug_trace_id' => 'id',
                'collect_denormalization_errors' => true,
                'collect_extra_attributes_errors' => true,
            ],
            // Options of other normalizers, encoders and decoders
            [
                'datetime_format' => 'Y-m-d',
                'datetime_timezone' => 'UTC',
                'json_encode_options' => JSON_PRESERVE_ZERO_FRACTION,
                'json_decode_detailed_errors' => true,
                'allow_invalid_values' => true,
                'messenger_serialization' => true,
                'xml_root_node_name' => 'response',
                'yaml_inline' => 2,
            ],
        ];
        $knownConstructionNormalizerWithUnsupportedDefaultContext = new KnownConstructionNormalizer(
            $comparison->planFactory,
            $comparison->knownConstructionNormalizer,
            null,
            'test',
            [AbstractNormalizer::GROUPS => ['group']],
        );

        // -- Act & Assert
        foreach ($unsupportedContexts as $context) {
            self::assertFalse($comparison->knownConstructionNormalizer->supportsDenormalization($data, Leaf::class, null, $context), (string) json_encode(array_keys($context)));
            self::assertFalse($comparison->knownConstructionNormalizer->supportsNormalization($leaf, null, $context), (string) json_encode(array_keys($context)));
        }
        foreach ($supportedContexts as $context) {
            self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization($data, Leaf::class, null, $context), (string) json_encode(array_keys($context)));
            self::assertTrue($comparison->knownConstructionNormalizer->supportsNormalization($leaf, null, $context), (string) json_encode(array_keys($context)));
        }

        // XML and CSV enable the type conversion of the PropertyNormalizer
        self::assertFalse($comparison->knownConstructionNormalizer->supportsDenormalization($data, Leaf::class, 'xml'));
        self::assertFalse($comparison->knownConstructionNormalizer->supportsDenormalization($data, Leaf::class, 'csv'));
        self::assertTrue($comparison->knownConstructionNormalizer->supportsDenormalization($data, Leaf::class, 'json'));

        self::assertFalse($comparison->knownConstructionNormalizer->supportsDenormalization('Example', Leaf::class));
        self::assertFalse($comparison->knownConstructionNormalizer->supportsNormalization('Example'));
        self::assertFalse($knownConstructionNormalizerWithUnsupportedDefaultContext->supportsDenormalization($data, Leaf::class));
        self::assertSame(['object' => false], $comparison->knownConstructionNormalizer->getSupportedTypes(null));
    }

    #[Test]
    public function options_of_the_default_context_are_accepted_like_the_ones_of_the_context(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $data = ['name' => 'Example'];

        // -- Act & Assert
        // Unknown options make the plan unsupported for every context
        self::assertFalse(self::knownConstructionNormalizerWithContextOptions($comparison, ['option_of_a_later_version' => true], [])->supportsDenormalization($data, Leaf::class));
        self::assertFalse(self::knownConstructionNormalizerWithContextOptions($comparison, ['option_of_a_later_version' => true], [])->supportsNormalization(new Leaf('Example')));
        self::assertFalse(self::knownConstructionNormalizerWithContextOptions($comparison, ['option_of_a_later_version' => true, 'deserialization_path' => 'leaf'], [])->supportsDenormalization($data, Leaf::class));
        self::assertFalse(self::knownConstructionNormalizerWithContextOptions($comparison, ['deserialization_path' => 'leaf', 'option_of_a_later_version' => true], [])->supportsDenormalization($data, Leaf::class));
        // Known options are accepted, the ones with an accepted value with that value or null
        self::assertTrue(self::knownConstructionNormalizerWithContextOptions($comparison, ['datetime_format' => 'Y-m-d', AbstractNormalizer::FILTER_BOOL => false], [])->supportsDenormalization($data, Leaf::class));
        self::assertTrue(self::knownConstructionNormalizerWithContextOptions($comparison, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => null], [])->supportsDenormalization($data, Leaf::class));
        self::assertTrue(self::knownConstructionNormalizerWithContextOptions($comparison, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true], [])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => null]));
        // A context option that is null takes the value of the default context
        self::assertFalse(self::knownConstructionNormalizerWithContextOptions($comparison, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false], [])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => null]));
        // Configured context keys are accepted in the context and in the default context
        self::assertTrue(self::knownConstructionNormalizerWithContextOptions($comparison, ['app_option' => true], ['app_option'])->supportsDenormalization($data, Leaf::class, null, ['app_option' => false]));
        self::assertTrue(self::knownConstructionNormalizerWithContextOptions($comparison, [], ['app_option', 'other_app_option'])->supportsNormalization(new Leaf('Example'), null, ['other_app_option' => 1]));
        self::assertFalse(self::knownConstructionNormalizerWithContextOptions($comparison, [], ['app_option'])->supportsNormalization(new Leaf('Example'), null, ['other_app_option' => 1]));
    }

    #[Test]
    public function only_options_the_plan_implements_and_options_without_accepted_value_are_not_acceptable(): void
    {
        // -- Act & Assert
        self::assertTrue(KnownConstructionNormalizer::isContextOptionAcceptable('app_option'));
        self::assertTrue(KnownConstructionNormalizer::isContextOptionAcceptable(AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER));
        self::assertFalse(KnownConstructionNormalizer::isContextOptionAcceptable(AbstractNormalizer::GROUPS));
        self::assertFalse(KnownConstructionNormalizer::isContextOptionAcceptable('_union_member'));
        self::assertFalse(KnownConstructionNormalizer::isContextOptionAcceptable(AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES));
        self::assertFalse(KnownConstructionNormalizer::isContextOptionAcceptable(Serializer::EMPTY_ARRAY_AS_OBJECT));
    }

    #[Test]
    public function direct_calls_with_data_the_plan_does_not_handle_use_the_fallback(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $knownConstructionNormalizer = $comparison->knownConstructionNormalizer;

        // -- Act & Assert
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->denormalize('Example', Optional::class)),
            SerializerComparison::outcome(static fn () => $knownConstructionNormalizer->denormalize('Example', Optional::class)),
        );
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->denormalize(['name' => 'Example'], Inherited::class)),
            SerializerComparison::outcome(static fn () => $knownConstructionNormalizer->denormalize(['name' => 'Example'], Inherited::class)),
        );
        self::assertSame(
            SerializerComparison::outcome(static fn () => $comparison->reference->normalize(new Inherited('Example'))),
            SerializerComparison::outcome(static fn () => $knownConstructionNormalizer->normalize(new Inherited('Example'))),
        );
        // Not through a serializer, which handles scalars itself: like calling the fallback directly
        $fallbackNormalizer = new \ReflectionProperty(KnownConstructionNormalizer::class, 'fallbackNormalizer')->getValue($knownConstructionNormalizer);
        self::assertInstanceOf(PropertyNormalizer::class, SerializerComparison::unwrap($fallbackNormalizer));
        self::assertSame(
            SerializerComparison::outcome(static fn () => $fallbackNormalizer->normalize('Example')),
            SerializerComparison::outcome(static fn () => $knownConstructionNormalizer->normalize('Example')),
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
        $cacheDirectory = sprintf('%s/known-construction-normalizer-test-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
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
                static fn (PlanFactory $planFactory, array $defaultContext): KnownConstructionNormalizer => new KnownConstructionNormalizer($planFactory, $comparison->knownConstructionNormalizer, $planCache, 'test', $defaultContext),
            );
            foreach ($samples as $sample) {
                $writingSerializer->denormalize($comparison->reference->normalize($sample), $sample::class);
            }

            $readingKnownConstructionNormalizers = [];
            $readingSerializer = $comparison->serializerWith(
                static function (PlanFactory $planFactory, array $defaultContext) use ($comparison, $planCache, &$readingKnownConstructionNormalizers): KnownConstructionNormalizer {
                    $knownConstructionNormalizer = new KnownConstructionNormalizer(
                        new PlanFactory(new ThrowingPropertyTypeExtractor(), '/\\\\Fixture\\\\ReadModel\\\\/', []),
                        $comparison->knownConstructionNormalizer,
                        $planCache,
                        'test',
                        $defaultContext,
                    );
                    $readingKnownConstructionNormalizers[] = $knownConstructionNormalizer;

                    return $knownConstructionNormalizer;
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
            $isUncachedClassSupported = $readingKnownConstructionNormalizers[0]->supportsDenormalization([], Optional::class);
            // Only possible with the plans from the cache
            $areCachedClassesSupported = array_map(
                static fn (object $sample): bool => $readingKnownConstructionNormalizers[0]->supportsDenormalization([], $sample::class),
                $samples,
            );
        } finally {
            new Filesystem()->remove($cacheDirectory);
        }

        // -- Assert
        // The type extractor of the reading normalizer throws, so the plans must come from the cache
        self::assertCount(1, $readingKnownConstructionNormalizers);
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
            $knownConstructionNormalizer = new KnownConstructionNormalizer($comparison->planFactory, $comparison->knownConstructionNormalizer, null, 'test', $defaultContext);
            self::assertEquals($expectedResult, $knownConstructionNormalizer->normalize(new Optional(null, null), null, $context), (string) json_encode([$defaultContext, $context]));
        }

        // Values after a skipped null value are kept
        $knownConstructionNormalizer = new KnownConstructionNormalizer($comparison->planFactory, $comparison->knownConstructionNormalizer, null, 'test', []);
        self::assertSame(['anything' => 'Example'], $knownConstructionNormalizer->normalize(new Optional(null, 'Example'), null, [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]));
    }

    #[Test]
    public function unsupported_options_of_the_default_context_are_overruled_by_the_context(): void
    {
        // -- Arrange
        $comparison = $this->comparison();
        $data = ['name' => 'Example'];
        $knownConstructionNormalizer = static fn (array $defaultContext): KnownConstructionNormalizer => new KnownConstructionNormalizer($comparison->planFactory, $comparison->knownConstructionNormalizer, null, 'test', $defaultContext);

        // -- Act & Assert
        self::assertFalse($knownConstructionNormalizer([AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true])->supportsDenormalization($data, Leaf::class));
        self::assertTrue($knownConstructionNormalizer([AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => false]));
        self::assertFalse($knownConstructionNormalizer([AbstractNormalizer::REQUIRE_ALL_PROPERTIES => false])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::REQUIRE_ALL_PROPERTIES => true]));
        self::assertFalse($knownConstructionNormalizer([AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])->supportsDenormalization($data, Leaf::class));
        self::assertTrue($knownConstructionNormalizer([AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true]));
        self::assertFalse($knownConstructionNormalizer([AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true])->supportsDenormalization($data, Leaf::class, null, [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false]));
    }

    // -- Helpers

    /**
     * @param array<string, mixed> $defaultContext
     * @param list<string>         $acceptedContextKeys
     */
    private static function knownConstructionNormalizerWithContextOptions(
        SerializerComparison $comparison,
        array $defaultContext,
        array $acceptedContextKeys = [],
    ): KnownConstructionNormalizer {
        return new KnownConstructionNormalizer($comparison->planFactory, $comparison->knownConstructionNormalizer, null, 'test', $defaultContext, $acceptedContextKeys);
    }

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
