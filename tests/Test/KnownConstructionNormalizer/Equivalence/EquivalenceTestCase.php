<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence;

use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\ConstructFinder;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizableNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\BoolNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\BoolNormalizableNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizableNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizableNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\ParameterKind;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\PlanFactory;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizableNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Safety net of the KnownConstructionNormalizer of an app: for every class of the source directories that it claims to support (generic discovery:
 * supportsDenormalization() of the configured normalizer), it must produce the same results as the PropertyNormalizer alone (see
 * SerializerComparison for the serializers and how outcomes are compared).
 *
 * Usage: extend it in the tests of the app (requires phpunit/phpunit 12 or 13 and a kernel for KernelTestCase), implement
 * sourceDirectories() and minimumNumberOfPlannedClasses(), and add samples (samplesOfClasses(), sampleProviders()) for the classes the
 * SampleFactory can't build on its own. The serializer is the service "serializer" of the container (see serializer()).
 *
 * - Data: every class is constructed by the SampleFactory (generated values of the parameter types, value objects and nested classes
 *   constructed recursively through their constructors), with every nullable value set and with every nullable value null. The data to
 *   denormalize is the normalized sample, as is and after a JSON round trip (like data from the database or a request), denormalized
 *   without format and with the JSON format.
 * - Valid data must be handled by the plan itself, not only through the fallback.
 * - Invalid data: for every constructor parameter, the key is removed, set to null, set to a value of the wrong type, set to an invalid
 *   value, and for a list gets a wrong / null / nested wrong element and other keys. Both serializers must succeed with the same object or
 *   fail with the same exception (that's how command and query DTOs keep their error responses).
 * - Selection: the serializer must pick the KnownConstructionNormalizer for every planned class, and for every value object in the samples the
 *   self-aware normalizer the plan calls directly instead.
 *
 * A new class whose constructor validation needs a combination of values the SampleFactory doesn't generate fails the first test; add a
 * sample to samplesOfClasses() or a SampleProvider to sampleProviders().
 */
abstract class EquivalenceTestCase extends KernelTestCase
{
    /**
     * Shared by all tests of the class, built on first use and released after the class: the plans of all classes (in the KnownConstructionNormalizer
     * of the serializers) and the samples take a large part of the time of every test. Every test still runs all its comparisons. The serializers only warm the memos of their plans, which plannedClasses() already warmed in every test before, and
     * the samples are only normalized.
     */
    private static ?SerializerComparison $comparison = null;
    /**
     * @var list<class-string>|null
     */
    private static ?array $plannedClasses = null;
    private static ?SampleFactory $sampleFactory = null;
    /**
     * Sample or the exception of building it, by class and variant.
     *
     * @var array<string, object|\Throwable>
     */
    private static array $samples = [];

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        self::$comparison = null;
        self::$plannedClasses = null;
        self::$sampleFactory = null;
        self::$samples = [];

        parent::tearDownAfterClass();
    }

    #[Test]
    public function planned_classes_are_normalized_and_denormalized_like_by_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = self::comparison();
        $plannedClasses = self::plannedClasses();

        // -- Act
        $differences = [];
        $classesWithoutSample = [];
        $numberOfComparisons = 0;
        foreach ($plannedClasses as $class) {
            foreach ([false, true] as $areNullableValuesNull) {
                try {
                    $sample = self::sample($class, $areNullableValuesNull);
                } catch (\Throwable $exception) {
                    // A class that can't be built with its nullable values null (e.g. one of two values must be set) is still covered
                    // by the variant with all values
                    if (!$areNullableValuesNull) {
                        $classesWithoutSample[] = sprintf('%s: %s: %s', $class, $exception::class, $exception->getMessage());
                    }
                    continue;
                }

                $variant = $areNullableValuesNull
                    ? 'nulls'
                    : 'filled';

                ++$numberOfComparisons;
                $referenceNormalizationOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->normalize($sample));
                $planNormalizationOutcome = SerializerComparison::outcome(static fn () => $comparison->plan->normalize($sample));
                if ($referenceNormalizationOutcome !== $planNormalizationOutcome) {
                    $differences[] = sprintf('%s (%s): normalize differs: %s vs %s', $class, $variant, json_encode($referenceNormalizationOutcome, JSON_THROW_ON_ERROR), json_encode($planNormalizationOutcome, JSON_THROW_ON_ERROR));
                    continue;
                }

                // E.g. a sample with a resource, which can't be normalized either way
                if ($referenceNormalizationOutcome[0] === 'exception') {
                    continue;
                }

                // The plan itself (not its fallback) handles the normalization
                if ($referenceNormalizationOutcome !== SerializerComparison::outcome(static fn () => $comparison->planOnly->normalize($sample))) {
                    $differences[] = sprintf('%s (%s): normalize through the plan only differs', $class, $variant);
                }

                $normalizedData = $comparison->reference->normalize($sample);
                $jsonData = json_decode(json_encode($normalizedData, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
                foreach (['normalized' => $normalizedData, 'json' => $jsonData] as $dataName => $data) {
                    foreach ([null, JsonEncoder::FORMAT] as $format) {
                        ++$numberOfComparisons;
                        $referenceOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->denormalize($data, $class, $format));
                        $planOutcome = SerializerComparison::outcome(static fn () => $comparison->plan->denormalize($data, $class, $format));
                        if ($referenceOutcome !== $planOutcome) {
                            $differences[] = sprintf('%s (%s, %s data, format %s): denormalize differs: %s vs %s', $class, $variant, $dataName, $format ?? 'null', json_encode($referenceOutcome, JSON_THROW_ON_ERROR), json_encode($planOutcome, JSON_THROW_ON_ERROR));
                        }
                    }
                }

                // The plan itself (not its fallback) handles valid data (JSON round trip with the JSON format), except for the parameters
                // that fall back by design when set (unions with other members than scalars)
                if ($comparison->knownConstructionNormalizer->diagnose($class)->fallbackParameters !== []) {
                    continue;
                }
                $referenceOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->denormalize($jsonData, $class, JsonEncoder::FORMAT));
                $planOnlyOutcome = SerializerComparison::outcome(static fn () => $comparison->planOnly->denormalize($jsonData, $class, JsonEncoder::FORMAT));
                if ($referenceOutcome[0] === 'result'
                    && $planOnlyOutcome !== $referenceOutcome
                ) {
                    $differences[] = sprintf('%s (%s): denormalize through the plan only differs: %s', $class, $variant, json_encode($planOnlyOutcome, JSON_THROW_ON_ERROR));
                }
            }
        }

        // -- Assert
        self::assertGreaterThanOrEqual(static::minimumNumberOfPlannedClasses(), count($plannedClasses));
        self::assertSame([], $differences);
        self::assertSame([], $classesWithoutSample, 'Add a sample to samplesOfClasses() or a SampleProvider to sampleProviders() for these classes.');
        self::assertGreaterThanOrEqual(static::minimumNumberOfPlannedClasses() * 5, $numberOfComparisons);
    }

    #[Test]
    public function planned_classes_are_handled_like_by_the_property_normalizer_with_cached_plans(): void
    {
        // -- Arrange
        $comparison = self::comparison();
        $plannedClasses = self::plannedClasses();

        // A file based adapter like the one of cache.system: the plans must survive the VarExporter. The plans are only read (see
        // SerializerComparison::serializerWithCachedPlans()).
        $cacheDirectory = sprintf('%s/known-construction-normalizer-equivalence-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
        try {
            $cachedPlanSerializer = $comparison->serializerWithCachedPlans(
                new PhpFilesAdapter('plans', 0, $cacheDirectory),
                $plannedClasses,
            );

            // -- Act
            $differences = [];
            $numberOfComparisons = 0;
            foreach ($plannedClasses as $class) {
                try {
                    $sample = self::sample($class, false);
                    $normalizedData = $comparison->reference->normalize($sample);
                } catch (\Throwable) {
                    // Covered by the first test
                    continue;
                }

                $jsonData = json_decode(json_encode($normalizedData, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
                $calls = [
                    'normalize' => [
                        static fn () => $comparison->reference->normalize($sample),
                        static fn () => $cachedPlanSerializer->normalize($sample),
                    ],
                    'denormalize with JSON format' => [
                        static fn () => $comparison->reference->denormalize($jsonData, $class, JsonEncoder::FORMAT),
                        static fn () => $cachedPlanSerializer->denormalize($jsonData, $class, JsonEncoder::FORMAT),
                    ],
                    'denormalize without format' => [
                        static fn () => $comparison->reference->denormalize($jsonData, $class),
                        static fn () => $cachedPlanSerializer->denormalize($jsonData, $class),
                    ],
                ];
                foreach ($calls as $callName => [$referenceCall, $cachedPlanCall]) {
                    ++$numberOfComparisons;
                    $referenceOutcome = SerializerComparison::outcome($referenceCall);
                    $cachedPlanOutcome = SerializerComparison::outcome($cachedPlanCall);
                    if ($referenceOutcome !== $cachedPlanOutcome) {
                        $differences[] = sprintf('%s: %s differs: %s vs %s', $class, $callName, json_encode($referenceOutcome, JSON_THROW_ON_ERROR), json_encode($cachedPlanOutcome, JSON_THROW_ON_ERROR));
                    }
                }
            }
        } finally {
            new Filesystem()->remove($cacheDirectory);
        }

        // -- Assert
        self::assertSame([], $differences);
        self::assertGreaterThanOrEqual(static::minimumNumberOfPlannedClasses() * 3, $numberOfComparisons);
    }

    #[Test]
    public function invalid_data_leads_to_the_same_result_as_with_the_property_normalizer(): void
    {
        // -- Arrange
        $comparison = self::comparison();
        $plannedClasses = self::plannedClasses();

        // -- Act
        $differences = [];
        $numberOfFailures = 0;
        $numberOfComparisons = 0;
        foreach ($plannedClasses as $class) {
            try {
                $normalizedData = $comparison->reference->normalize(self::sample($class, false));
            } catch (\Throwable) {
                // Covered by the first test
                continue;
            }

            /**
             * @var array<string, mixed> $data
             */
            $data = json_decode(json_encode($normalizedData, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
            foreach (new \ReflectionClass($class)->getConstructor()?->getParameters() ?? [] as $parameter) {
                foreach (SerializerComparison::mutations($data, $parameter->name) as $mutationName => $mutatedData) {
                    foreach ([null, JsonEncoder::FORMAT] as $format) {
                        ++$numberOfComparisons;
                        $referenceOutcome = SerializerComparison::outcome(static fn () => $comparison->reference->denormalize($mutatedData, $class, $format));
                        $planOutcome = SerializerComparison::outcome(static fn () => $comparison->plan->denormalize($mutatedData, $class, $format));
                        if ($referenceOutcome[0] === 'exception') {
                            ++$numberOfFailures;
                        }
                        if ($referenceOutcome !== $planOutcome) {
                            $differences[] = sprintf('%s $%s %s (format %s): %s vs %s', $class, $parameter->name, $mutationName, $format ?? 'null', json_encode($referenceOutcome, JSON_THROW_ON_ERROR), json_encode($planOutcome, JSON_THROW_ON_ERROR));
                        }
                    }
                }
            }
        }

        // -- Assert
        self::assertSame([], $differences);
        // Makes sure the mutations actually lead to errors
        self::assertGreaterThanOrEqual(static::minimumNumberOfPlannedClasses(), $numberOfFailures);
        self::assertGreaterThanOrEqual(static::minimumNumberOfPlannedClasses() * 2, $numberOfComparisons);
    }

    #[Test]
    public function serializer_selects_the_known_construction_normalizer_for_planned_classes_and_the_self_aware_normalizers_for_value_objects(): void
    {
        // -- Arrange
        $comparison = self::comparison();
        $plannedClasses = self::plannedClasses();

        $getNormalizer = new \ReflectionMethod(Serializer::class, 'getNormalizer');
        $getDenormalizer = new \ReflectionMethod(Serializer::class, 'getDenormalizer');
        $normalizerOfValueObjectKind = [
            ParameterKind::VALUE_OBJECT_STRING => StringNormalizableNormalizer::class,
            ParameterKind::VALUE_OBJECT_INT => IntNormalizableNormalizer::class,
            ParameterKind::VALUE_OBJECT_FLOAT => FloatNormalizableNormalizer::class,
            ParameterKind::VALUE_OBJECT_BOOL => BoolNormalizableNormalizer::class,
            ParameterKind::VALUE_OBJECT_ARRAY => ArrayNormalizableNormalizer::class,
        ];

        // -- Act
        $wrongSelections = [];
        $valueObjects = [];
        foreach ($plannedClasses as $class) {
            $denormalizer = SerializerComparison::unwrap($getDenormalizer->invoke($comparison->plan, [], $class, null, []));
            if (!$denormalizer instanceof KnownConstructionNormalizer) {
                $wrongSelections[] = sprintf('%s is denormalized by %s', $class, get_debug_type($denormalizer));
            }

            try {
                $sample = self::sample($class, false);
            } catch (\Throwable) {
                // Covered by the first test
                continue;
            }

            // A class with a mutable property is only denormalized through its plan (listed by the list command)
            $normalizer = SerializerComparison::unwrap($getNormalizer->invoke($comparison->plan, $sample, null, []));
            if ($comparison->knownConstructionNormalizer->supportsNormalization($sample)
                && !$normalizer instanceof KnownConstructionNormalizer
            ) {
                $wrongSelections[] = sprintf('%s is normalized by %s', $class, get_debug_type($normalizer));
            }

            self::collectValueObjects($sample, $valueObjects);
        }

        foreach ($valueObjects as $class => $valueObject) {
            /**
             * @var int $valueObjectKind Only value objects are collected
             */
            $valueObjectKind = PlanFactory::valueObjectKind($class);

            try {
                $normalizedValueObject = $valueObject->normalize();
            } catch (\Throwable) {
                // A value object the SampleFactory built with values of the wrong type (unresolved generics)
                continue;
            }

            $expectedNormalizer = $normalizerOfValueObjectKind[$valueObjectKind];
            $normalizer = SerializerComparison::unwrap($getNormalizer->invoke($comparison->plan, $valueObject, null, []));
            $denormalizer = SerializerComparison::unwrap($getDenormalizer->invoke($comparison->plan, $normalizedValueObject, $class, null, []));
            if (!$normalizer instanceof $expectedNormalizer
                || !$denormalizer instanceof $expectedNormalizer
            ) {
                $wrongSelections[] = sprintf('%s is (de)normalized by %s / %s instead of %s', $class, get_debug_type($normalizer), get_debug_type($denormalizer), $expectedNormalizer);
            }
        }

        // -- Assert
        self::assertSame([], $wrongSelections);
        self::assertGreaterThanOrEqual(static::minimumNumberOfValueObjects(), count($valueObjects));
    }

    // -- Configuration of the app

    /**
     * Directories whose classes are compared (recursively), e.g. the src directory of the app.
     *
     * @return list<string>
     */
    abstract protected static function sourceDirectories(): array;

    /**
     * The test fails when fewer classes are planned, e.g. when a change of the PlanFactory or the configuration silently takes classes
     * off the plan. Set it to about the current number of planned classes.
     */
    abstract protected static function minimumNumberOfPlannedClasses(): int;

    /**
     * Samples of classes whose constructor validation needs a combination of values the SampleFactory doesn't generate.
     *
     * @return array<class-string, \Closure(): object>
     */
    protected static function samplesOfClasses(): array
    {
        return [];
    }

    /**
     * Samples of value objects and strings of the app (see SampleProvider).
     *
     * @return list<SampleProvider>
     */
    protected static function sampleProviders(): array
    {
        return [];
    }

    /**
     * The test fails when fewer distinct self-aware value objects are found in the samples.
     */
    protected static function minimumNumberOfValueObjects(): int
    {
        return 1;
    }

    /**
     * The configured serializer, containing the KnownConstructionNormalizer.
     */
    protected static function serializer(): object
    {
        return static::getContainer()->get('serializer');
    }

    // -- Helpers

    /**
     * The configured serializer, built once for all tests of the class (see $comparison).
     */
    private static function comparison(): SerializerComparison
    {
        if (self::$comparison === null) {
            static::bootKernel();
            self::$comparison = SerializerComparison::fromSerializer(static::serializer());
        }

        return self::$comparison;
    }

    /**
     * Sample of the class (see SampleFactory), built once for all tests of the class (see $comparison). A class that can't be built
     * throws the same exception on every call.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws \Throwable when the class can't be built
     */
    private static function sample(
        string $class,
        bool $areNullableValuesNull,
    ): object {
        $key = sprintf(
            '%s|%s',
            $class,
            $areNullableValuesNull
                ? 'nulls'
                : 'filled',
        );
        if (!array_key_exists($key, self::$samples)) {
            if (self::$sampleFactory === null) {
                /**
                 * @var PropertyTypeExtractorInterface $propertyTypeExtractor The type extractor of the PropertyNormalizer
                 */
                $propertyTypeExtractor = new \ReflectionProperty(PlanFactory::class, 'propertyTypeExtractor')->getValue(self::comparison()->planFactory);
                self::$sampleFactory = new SampleFactory(
                    $propertyTypeExtractor,
                    self::classesOfSource(),
                    static::samplesOfClasses(),
                    static::sampleProviders(),
                );
            }

            try {
                self::$samples[$key] = self::$sampleFactory->create($class, $areNullableValuesNull);
            } catch (\Throwable $exception) {
                self::$samples[$key] = $exception;
            }
        }

        $sample = self::$samples[$key];
        if ($sample instanceof \Throwable) {
            throw $sample;
        }

        /**
         * @var T $sample Built for the class
         */
        return $sample;
    }

    /**
     * Classes of the source directories the configured KnownConstructionNormalizer supports, determined once for all tests of the class (see $comparison).
     *
     * @return list<class-string>
     */
    private static function plannedClasses(): array
    {
        if (self::$plannedClasses === null) {
            $comparison = self::comparison();
            self::$plannedClasses = array_values(array_filter(
                self::classesOfSource(),
                static fn (string $class): bool => $comparison->knownConstructionNormalizer->supportsDenormalization([], $class),
            ));
        }

        return self::$plannedClasses;
    }

    /**
     * All loadable classes of the source directories, sorted.
     *
     * @return list<class-string>
     */
    private static function classesOfSource(): array
    {
        $classes = [];
        foreach (static::sourceDirectories() as $sourceDirectory) {
            foreach (ConstructFinder::findClassNames($sourceDirectory) as $class) {
                try {
                    $isLoaded = class_exists($class);
                } catch (\Throwable) {
                    // E.g. a class whose parent class only exists with the dev dependencies
                    continue;
                }
                if ($isLoaded) {
                    $classes[] = $class;
                }
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /**
     * @param array<class-string, StringNormalizable|IntNormalizable|FloatNormalizable|BoolNormalizable|ArrayNormalizable> $valueObjects
     */
    private static function collectValueObjects(
        mixed $value,
        array &$valueObjects,
    ): void {
        if (is_array($value)) {
            foreach ($value as $element) {
                self::collectValueObjects($element, $valueObjects);
            }

            return;
        }

        // A closure has no value objects, and casting it to an array contains the closure itself (endless recursion)
        if (!is_object($value)
            || $value instanceof \Closure
        ) {
            return;
        }

        if ($value instanceof StringNormalizable
            || $value instanceof IntNormalizable
            || $value instanceof FloatNormalizable
            || $value instanceof BoolNormalizable
            || $value instanceof ArrayNormalizable
        ) {
            $valueObjects[$value::class] ??= $value;

            return;
        }

        foreach ((array) $value as $property) {
            self::collectValueObjects($property, $valueObjects);
        }
    }
}
