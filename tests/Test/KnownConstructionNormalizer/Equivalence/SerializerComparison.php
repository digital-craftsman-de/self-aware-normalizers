<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence;

use BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\KnownConstructionNormalizer;
use BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\PlanFactory;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Serializer\Debug\TraceableNormalizer;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Serializers to compare the KnownConstructionNormalizer with the PropertyNormalizer, built from the normalizers of the container:
 * - plan: the configured serializer,
 * - reference: clones of all normalizers except the KnownConstructionNormalizer, so the PropertyNormalizer handles every class like before,
 * - planOnly: like plan, but the fallback of the KnownConstructionNormalizer throws, to prove that the plan itself handles valid data.
 *
 * Used by EquivalenceTestCase and available for tests of apps with own fixtures.
 *
 * Outcomes are compared through a description of the result (classes and properties with their types, so an int doesn't equal a float and
 * an \ArrayObject doesn't equal an array) or of the exception (class, message and for serializer exceptions the deserialization path,
 * expected types and missing arguments).
 */
final readonly class SerializerComparison
{
    /**
     * Deep enough for a list<list<list<X>>> with a value object property in X.
     */
    private const int MAXIMUM_LEVEL_OF_MUTATIONS = 4;

    private function __construct(
        public Serializer $plan,
        public Serializer $reference,
        public Serializer $planOnly,
        public KnownConstructionNormalizer $knownConstructionNormalizer,
        public PlanFactory $planFactory,
        /**
         * @var array<string, mixed>
         */
        public array $defaultContext,
        /**
         * Accepted context keys of the configured KnownConstructionNormalizer.
         *
         * @var list<string>
         */
        public array $acceptedContextKeys,
        /**
         * @var list<NormalizerInterface|DenormalizerInterface>
         */
        private array $normalizers,
    ) {
    }

    /**
     * Serializer like the configured one, with another KnownConstructionNormalizer.
     *
     * @param \Closure(PlanFactory, array<string, mixed>, list<string>): KnownConstructionNormalizer $createKnownConstructionNormalizer Gets the plan factory, the
     *                                                                                                                                  default context and the accepted
     *                                                                                                                                  context keys of the configured one
     */
    public function serializerWith(\Closure $createKnownConstructionNormalizer): Serializer
    {
        return new Serializer(
            array_map(
                fn (NormalizerInterface | DenormalizerInterface $normalizer): NormalizerInterface | DenormalizerInterface => $normalizer instanceof KnownConstructionNormalizer
                    ? $createKnownConstructionNormalizer($this->planFactory, $this->defaultContext, $this->acceptedContextKeys)
                    : clone $normalizer,
                $this->normalizers,
            ),
            [new JsonEncoder()],
            $this->defaultContext,
        );
    }

    public static function fromSerializer(object $serializer): self
    {
        if ($serializer instanceof TraceableSerializer) {
            $serializer = new \ReflectionProperty(TraceableSerializer::class, 'serializer')->getValue($serializer);
        }
        if (!$serializer instanceof Serializer) {
            throw new \LogicException(sprintf('Unexpected serializer %s.', get_debug_type($serializer)));
        }

        /**
         * @var list<NormalizerInterface|DenormalizerInterface> $normalizers
         */
        $normalizers = array_map(
            self::unwrap(...),
            new \ReflectionProperty(Serializer::class, 'normalizers')->getValue($serializer),
        );
        /**
         * @var array<string, mixed> $defaultContext
         */
        $defaultContext = new \ReflectionProperty(Serializer::class, 'defaultContext')->getValue($serializer);

        $knownConstructionNormalizers = array_values(array_filter(
            $normalizers,
            static fn (object $normalizer): bool => $normalizer instanceof KnownConstructionNormalizer,
        ));
        if (count($knownConstructionNormalizers) !== 1
            || !$knownConstructionNormalizers[0] instanceof KnownConstructionNormalizer
        ) {
            throw new \LogicException('The serializer must contain exactly one KnownConstructionNormalizer.');
        }
        $knownConstructionNormalizer = $knownConstructionNormalizers[0];

        /**
         * @var PlanFactory $planFactory
         */
        $planFactory = new \ReflectionProperty(KnownConstructionNormalizer::class, 'planFactory')->getValue($knownConstructionNormalizer);
        /**
         * @var list<string> $acceptedContextKeys
         */
        $acceptedContextKeys = new \ReflectionProperty(KnownConstructionNormalizer::class, 'acceptedContextKeys')->getValue($knownConstructionNormalizer);

        $reference = new Serializer(
            array_values(array_map(
                static fn (NormalizerInterface | DenormalizerInterface $normalizer): NormalizerInterface | DenormalizerInterface => clone $normalizer,
                array_filter(
                    $normalizers,
                    static fn (object $normalizer): bool => !$normalizer instanceof KnownConstructionNormalizer,
                ),
            )),
            [new JsonEncoder()],
            $defaultContext,
        );

        $planOnly = new Serializer(
            array_map(
                static fn (NormalizerInterface | DenormalizerInterface $normalizer): NormalizerInterface | DenormalizerInterface => $normalizer instanceof KnownConstructionNormalizer
                    ? new KnownConstructionNormalizer($planFactory, self::throwingFallback(), null, 'test', $defaultContext, $acceptedContextKeys)
                    : clone $normalizer,
                $normalizers,
            ),
            [new JsonEncoder()],
            $defaultContext,
        );

        return new self($serializer, $reference, $planOnly, $knownConstructionNormalizer, $planFactory, $defaultContext, $acceptedContextKeys, $normalizers);
    }

    /**
     * Serializer whose KnownConstructionNormalizer only reads plans from the given pool: they are written into it for the given classes first, then
     * read by a KnownConstructionNormalizer whose type extractor throws (a class without cached plan is left to the PropertyNormalizer).
     *
     * @param list<class-string> $classes
     */
    public function serializerWithCachedPlans(
        CacheItemPoolInterface $planCache,
        array $classes,
    ): Serializer {
        $writingKnownConstructionNormalizer = new KnownConstructionNormalizer($this->planFactory, $this->knownConstructionNormalizer, $planCache, 'test', $this->defaultContext, $this->acceptedContextKeys);
        foreach ($classes as $class) {
            $writingKnownConstructionNormalizer->supportsDenormalization([], $class);
        }

        $readingPlanFactory = $this->planFactory->withPropertyTypeExtractor(new ThrowingPropertyTypeExtractor());

        return $this->serializerWith(
            fn (PlanFactory $planFactory, array $defaultContext, array $acceptedContextKeys): KnownConstructionNormalizer => new KnownConstructionNormalizer(
                $readingPlanFactory,
                $this->knownConstructionNormalizer,
                $planCache,
                'test',
                $defaultContext,
                $acceptedContextKeys,
            ),
        );
    }

    public static function unwrap(mixed $normalizer): mixed
    {
        return $normalizer instanceof TraceableNormalizer
            ? new \ReflectionProperty(TraceableNormalizer::class, 'normalizer')->getValue($normalizer)
            : $normalizer;
    }

    /**
     * Outcome of a call: the description of its result or of its exception. Warnings (e.g. a missing array key in a value object) are
     * turned into exceptions, so they are compared as well.
     *
     * @param \Closure(): mixed $call
     *
     * @return list<mixed>
     */
    public static function outcome(\Closure $call): array
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            return ['result', self::describe($call())];
        } catch (\Throwable $exception) {
            return [
                'exception',
                $exception::class,
                $exception->getMessage(),
                $exception instanceof NotNormalizableValueException
                    ? [$exception->getPath(), $exception->getExpectedTypes(), $exception->getCurrentType(), $exception->canUseMessageForUser()]
                    : null,
                $exception instanceof MissingConstructorArgumentsException
                    ? [$exception->getMissingConstructorArguments(), $exception->getClass()]
                    : null,
            ];
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Variants of the data with the value of the key removed, set to null, set to a value of the wrong type or an invalid value, for a
     * list with a wrong, a null or a nested wrong element and with string or other int keys, and the same for every inner level along the
     * first elements (up to MAXIMUM_LEVEL_OF_MUTATIONS levels deep).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, array<string, mixed>>
     */
    public static function mutations(
        array $data,
        string $key,
    ): array {
        $value = $data[$key] ?? null;

        $withoutKey = $data;
        unset($withoutKey[$key]);

        $mutations = [
            'removed' => $withoutKey,
            'null' => [$key => null] + $data,
            'extra key' => $data + ['unknownKey' => 'Example'],
        ];

        $mutations['wrong type'] = [
            $key => match (true) {
                is_string($value) => 12345,
                is_int($value) => '12345',
                is_float($value) => '2.5',
                is_bool($value) => 'true',
                is_array($value) => 'not an array',
                default => ['unexpected' => true],
            },
        ] + $data;

        $mutations['int for float'] = [
            $key => is_float($value)
                ? (int) $value
                : 7,
        ] + $data;

        // Not an empty string: value objects of dates and times might return the current time for it, which differs between two calls
        if (is_string($value)) {
            $mutations['invalid value'] = [$key => 'invalid'] + $data;
        }

        if (is_array($value)
            && $value !== []
        ) {
            $firstKey = array_key_first($value);
            $firstElement = $value[$firstKey];

            $withWrongElement = $value;
            $withWrongElement[$firstKey] = is_array($firstElement)
                ? 'not an array'
                : ['unexpected' => true];
            $mutations['wrong element'] = [$key => $withWrongElement] + $data;

            $withNullElement = $value;
            $withNullElement[$firstKey] = null;
            $mutations['null element'] = [$key => $withNullElement] + $data;

            $mutations['string keys'] = [$key => ['first' => $firstElement]] + $data;
            $mutations['int keys'] = [$key => [7 => $firstElement]] + $data;

            // The same for every inner level along the first elements, down to the deepest array (the inner levels of a nested list
            // like list<list<X>> and the properties of the objects in it)
            $path = [$firstKey];
            $element = $firstElement;
            for ($level = 1; $level <= self::MAXIMUM_LEVEL_OF_MUTATIONS; ++$level) {
                if (!is_array($element)
                    || $element === []
                ) {
                    break;
                }

                $innerKey = array_key_first($element);
                $innerElement = $element[$innerKey];
                $innerPath = [...$path, $innerKey];

                $mutations[sprintf('level %d wrong element', $level)] = [$key => self::withValueAtPath($value, $innerPath, match (true) {
                    is_array($innerElement) => 'not an array',
                    is_string($innerElement) => 12345,
                    default => 'invalid',
                })] + $data;
                $mutations[sprintf('level %d null element', $level)] = [$key => self::withValueAtPath($value, $innerPath, null)] + $data;
                $mutations[sprintf('level %d string keys', $level)] = [$key => self::withValueAtPath($value, $path, ['first' => $innerElement])] + $data;
                $mutations[sprintf('level %d int keys', $level)] = [$key => self::withValueAtPath($value, $path, [7 => $innerElement])] + $data;

                $path = $innerPath;
                $element = $innerElement;
            }
        }

        return $mutations;
    }

    /**
     * @param array<array-key, mixed>   $value
     * @param non-empty-list<array-key> $path
     *
     * @return array<array-key, mixed>
     */
    private static function withValueAtPath(
        array $value,
        array $path,
        mixed $newValue,
    ): array {
        $key = array_shift($path);
        /**
         * @var array<array-key, mixed> $element The path follows arrays only
         */
        $element = $value[$key];
        $value[$key] = $path === []
            ? $newValue
            : self::withValueAtPath($element, $path, $newValue);

        return $value;
    }

    /**
     * Description of a value that distinguishes types (int / float, array / \ArrayObject) and covers all properties of objects.
     */
    public static function describe(mixed $value): mixed
    {
        return match (true) {
            $value === null => null,
            is_scalar($value) => [get_debug_type($value), $value],
            is_array($value) => array_map(self::describe(...), $value),
            $value instanceof \DateTimeInterface => [$value::class, $value->format('Y-m-d H:i:s.u e')],
            $value instanceof \SplFileInfo => [$value::class, $value->getPathname()],
            $value instanceof \UnitEnum => [$value::class, $value->name],
            $value instanceof \ArrayObject => [\ArrayObject::class, self::describe($value->getArrayCopy())],
            is_object($value) => [$value::class, self::describe((array) $value)],
            default => [get_debug_type($value)],
        };
    }

    private static function throwingFallback(): NormalizerInterface & DenormalizerInterface
    {
        return new class implements NormalizerInterface, DenormalizerInterface {
            /**
             * @param array<string, mixed> $context
             */
            #[\Override]
            public function normalize(
                mixed $data,
                ?string $format = null,
                array $context = [],
            ): never {
                throw new LogicException(sprintf('The fallback normalizes %s.', get_debug_type($data)));
            }

            /**
             * @param array<string, mixed> $context
             */
            #[\Override]
            public function supportsNormalization(
                mixed $data,
                ?string $format = null,
                array $context = [],
            ): bool {
                return false;
            }

            /**
             * @param array<string, mixed> $context
             */
            #[\Override]
            public function denormalize(
                mixed $data,
                string $type,
                ?string $format = null,
                array $context = [],
            ): never {
                throw new LogicException(sprintf('The fallback denormalizes %s.', $type));
            }

            /**
             * @param array<string, mixed> $context
             */
            #[\Override]
            public function supportsDenormalization(
                mixed $data,
                string $type,
                ?string $format = null,
                array $context = [],
            ): bool {
                return false;
            }

            /**
             * @return array<string, bool>
             */
            #[\Override]
            public function getSupportedTypes(?string $format): array
            {
                return [];
            }
        };
    }
}
