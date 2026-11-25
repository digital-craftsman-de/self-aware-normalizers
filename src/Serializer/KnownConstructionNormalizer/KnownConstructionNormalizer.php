<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use BuronDigital\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\BoolNormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\FloatNormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\IntNormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizable;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Serializer\Exception\UnsupportedException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\TypeInfo\Type;

/**
 * Fast replacement of the PropertyNormalizer for plain constructor-promoted classes (e.g. read models, DTOs, commands, queries and
 * events). It knows in advance how each object is constructed: containers through a constructor that mirrors their promoted properties,
 * self-aware value objects through their denormalize() / normalize(). That knowledge is precomputed per class as a plan. It produces the
 * same results as the PropertyNormalizer, but without its per object machinery (type resolution per parameter, attribute loops, context
 * handling, normalizer selection per value). Every class that reaches it gets a plan attempt, classes the plan can't express keep the
 * PropertyNormalizer.
 *
 * Per class, a plan is built once (PlanFactory): per constructor parameter the kind of value and the class to build. Denormalization
 * still constructs every object: value objects through their static denormalize() (buron-digital/self-aware-normalizers), the
 * planned class through its constructor, with the same validation as before. Normalization reads the public properties in declaration
 * order and calls normalize() on value objects. Values of other classes go through the serializer as before.
 *
 * Equivalence: the plan only takes the paths on which the PropertyNormalizer would succeed with the same result (data that already has
 * the expected type). Any other data (missing argument, wrong type, null for a non nullable parameter, a failing nested object of
 * another class, a TypeError of the constructor) makes the object fall back to the PropertyNormalizer, which then throws its own exception
 * with its own message and path. Exceptions of value objects and constructors are the same either way. Classes the plan can't express
 * and contexts with options the plan doesn't implement are not supported at all, so the serializer uses the PropertyNormalizer.
 *
 * Must be registered between the self-aware normalizers / BackedEnumNormalizer and the PropertyNormalizer, and the PropertyNormalizer must
 * be the fallback. The fallback must be a PropertyNormalizer without class metadata factory, name converter, class discriminator resolver
 * and object class resolver, with the same type extractor as the PlanFactory and the same default context (the bundle validates this
 * through the KnownConstructionNormalizerCompilerPass). Plans are kept per instance and optionally cached across requests in a PSR-6 pool.
 *
 * The plan mirrors internals of symfony/serializer, symfony/type-info and symfony/property-info. Instead of a version lock, anything it
 * doesn't know leads to the PropertyNormalizer or fails the container build: context options must be on an allowlist (see
 * ACCEPTED_CONTEXT_OPTIONS), the constructor arguments of the fallback as well (see KnownConstructionNormalizerCompilerPass), and a class
 * a normalizer in front of it may handle is never handled inline (see NormalizerChain). The equivalence tests of the bundle compare it
 * with the PropertyNormalizer, also against the next version of Symfony.
 *
 * @psalm-import-type ClassPlan from PlanFactory
 * @psalm-import-type ScalarListSpec from PlanFactory
 */
final class KnownConstructionNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    /**
     * Version of the plan structure, part of the cache key (together with the version of the code, see the constructor).
     */
    private const string PLAN_CACHE_KEY_PREFIX = 'plan-v3';

    /**
     * Context options the plan is used with, whatever their value (option => true). The plan is only used when every option of the
     * context and of the default context is one of them, one of CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE (with that value) or one of the
     * accepted context keys of the configuration. Any other option (e.g. one added by a later version of the serializer) makes the
     * serializer use the PropertyNormalizer, so an option is never silently ignored. Verified against symfony/serializer 8.1.
     */
    private const array ACCEPTED_CONTEXT_OPTIONS = [
        // Options of the PropertyNormalizer the plan implements itself (see normalize())
        AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,

        // Options of the PropertyNormalizer that only change which attributes are cached (the results are the same)
        AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY => true,

        // Internal options the serializer and its normalizers put into the context during a (de)normalization:
        // - cache_key: only the key of the attribute cache, the plan removes it before handing values on
        // - deserialization_path: only the path in exceptions, which are always thrown by the PropertyNormalizer (the plan falls back)
        // - key_type, value_type (ArrayDenormalizer, validateAndDenormalize()): the types of a collection, only read for collections and
        //   null data, which the plan doesn't denormalize itself, or set by the plan itself for the collections it hands on
        // - _read_attributes: set by every object normalizer before it reads it
        // - circular_reference_limit_counters: counters of the object normalizers the plan is nested in, handed on as is
        // - debug_trace_id (TraceableSerializer, in debug mode): only used for the profiler
        'cache_key' => true,
        'deserialization_path' => true,
        'key_type' => true,
        'value_type' => true,
        '_read_attributes' => true,
        'circular_reference_limit_counters' => true,
        'debug_trace_id' => true,

        // Options the serializer replaces with the collectors of the errors (not_normalizable_value_exceptions and
        // extra_attributes_exceptions, which aren't accepted) before a denormalization. Not read by any normalizer.
        'collect_denormalization_errors' => true,
        'collect_extra_attributes_errors' => true,

        // Options of other normalizers. The plan hands the values of other classes on to the serializer with the context as is, like
        // the PropertyNormalizer, so these normalizers get the same options either way.
        'datetime_format' => true, // DateTimeNormalizer
        'datetime_timezone' => true,
        'datetime_cast' => true,
        'datetime_force_timezone' => true,
        'dateinterval_format' => true, // DateIntervalNormalizer
        'uid_normalization_format' => true, // UidNormalizer
        'allow_invalid_values' => true, // BackedEnumNormalizer
        'translatable_normalization_locale' => true, // TranslatableNormalizer
        'exception' => true, // ProblemNormalizer (ErrorRenderer)
        'debug' => true,
        'instance' => true, // ConstraintViolationListNormalizer, ProblemNormalizer, FormErrorNormalizer
        'payload_fields' => true,
        'status' => true,
        'status_code' => true,
        'title' => true,
        'type' => true,
        'require_snake_case_properties' => true, // Name converters, the fallback has none
        'require_camel_case_properties' => true,
        'messenger_serialization' => true, // Serializer of symfony/messenger, only a marker

        // Options of the encoders and decoders, which are part of the context of the (de)normalization of serialize() and
        // deserialize(). Not read by any normalizer.
        'json_encode_options' => true, // JsonEncode
        'json_decode_associative' => true, // JsonDecode
        'json_decode_detailed_errors' => true,
        'json_decode_options' => true,
        'json_decode_recursion_depth' => true,
        'csv_delimiter' => true, // CsvEncoder
        'csv_enclosure' => true,
        'csv_key_separator' => true,
        'csv_headers' => true,
        'csv_escape_formulas' => true,
        'csv_end_of_line' => true,
        'no_headers' => true,
        'as_collection' => true, // CsvEncoder, XmlEncoder
        'decoder_ignored_node_types' => true, // XmlEncoder
        'encoder_ignored_node_types' => true,
        'xml_encoding' => true,
        'xml_format_output' => true,
        'load_options' => true,
        'save_options' => true,
        'remove_empty_tags' => true,
        'xml_root_node_name' => true,
        'xml_standalone' => true,
        'xml_type_cast_attributes' => true,
        'xml_version' => true,
        'cdata_wrapping' => true,
        'cdata_wrapping_name_pattern' => true,
        'cdata_wrapping_pattern' => true,
        'ignore_empty_attributes' => true,
        'preserve_numeric_keys' => true,
        'yaml_indentation' => true, // YamlEncoder (its preserve_empty_objects is the one above)
        'yaml_inline' => true,
        'yaml_indent' => true,
        'yaml_flags' => true,
    ];

    /**
     * Options of the PropertyNormalizer the plan is only used with when their effective value (context before default context, null
     * like not set) is the one that keeps the default behaviour, which the plan implements. Option => accepted value.
     */
    private const array CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE = [
        AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
        AbstractNormalizer::REQUIRE_ALL_PROPERTIES => false,
        AbstractNormalizer::FILTER_BOOL => false,
        AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false,
        AbstractObjectNormalizer::ENABLE_TYPE_CONVERSION => false,
        AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => false,
        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => false,
        // The plan only reads objects built through their constructor, whose promoted properties are always initialized
        AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => true,
        Serializer::EMPTY_ARRAY_AS_OBJECT => false,
    ];

    /**
     * Options the PropertyNormalizer reads that change its result and that the plan doesn't implement. Never accepted, also not through
     * the accepted context keys of the configuration. Other options that aren't accepted (e.g. the ones of the circular reference
     * handling, which the plan doesn't implement either) can be accepted there.
     */
    public const array NOT_IMPLEMENTED_CONTEXT_OPTIONS = [
        AbstractNormalizer::OBJECT_TO_POPULATE,
        AbstractNormalizer::GROUPS,
        AbstractNormalizer::ATTRIBUTES,
        AbstractNormalizer::IGNORED_ATTRIBUTES,
        AbstractNormalizer::CALLBACKS,
        AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS,
        AbstractObjectNormalizer::MAX_DEPTH_HANDLER,
        PropertyNormalizer::NORMALIZE_VISIBILITY,
        UnwrappingDenormalizer::UNWRAP_PATH,
        // Collecting errors (COLLECT_DENORMALIZATION_ERRORS / COLLECT_EXTRA_ATTRIBUTES_ERRORS) and union members
        'not_normalizable_value_exceptions',
        'extra_attributes_exceptions',
        '_union_member',
    ];

    private const int NORMALIZATION_VALUE_OBJECT = 1;
    private const int NORMALIZATION_PLANNED = 2;
    private const int NORMALIZATION_SERIALIZER = 3;

    /**
     * @var array<string, ClassPlan|false>
     */
    private array $plans = [];

    /**
     * Class => class, in the order the plans are started (a set, only the keys are used).
     *
     * @var array<string, string>
     */
    private array $plansInProgress = [];

    /**
     * @var array<class-string, int>
     */
    private array $normalizationKinds = [];

    /**
     * @var array<string, string> Classes that reference themselves (directly or through other classes), class => class (a set)
     */
    private array $recursiveClasses = [];

    /**
     * Accepted context options (built-in and configured), option => true.
     *
     * @var array<string, true>
     */
    private readonly array $acceptedContextOptions;

    /**
     * Whether every option of the default context is known (accepted or with an accepted value).
     */
    private readonly bool $isDefaultContextKnown;

    /**
     * Options of the default context with a value that isn't accepted, which the context must overrule, option => option.
     *
     * @var array<string, string>
     */
    private readonly array $defaultContextOptionsToOverrule;

    /**
     * @param string               $planCacheVersion    Version of the code (e.g. the build id of the container), part of the cache key, so a
     *                                                  cached plan never outlives the code it was built from
     * @param array<string, mixed> $defaultContext
     * @param ?Debug\Recorder      $recorder            Only in debug mode with the profiler
     * @param list<string>         $acceptedContextKeys Further context options that don't change the result of the PropertyNormalizer
     *                                                  (guaranteed by the application), e.g. options of its own normalizers. Must be
     *                                                  acceptable (see isContextOptionAcceptable(), validated by the configuration).
     */
    public function __construct(
        private readonly PlanFactory $planFactory,
        private readonly NormalizerInterface & DenormalizerInterface $fallbackNormalizer,
        private readonly ?CacheItemPoolInterface $planCache,
        private readonly string $planCacheVersion,
        private readonly array $defaultContext = [],
        private readonly array $acceptedContextKeys = [],
        private readonly ?Debug\Recorder $recorder = null,
    ) {
        $this->acceptedContextOptions = self::ACCEPTED_CONTEXT_OPTIONS + array_fill_keys($acceptedContextKeys, true);

        $this->isDefaultContextKnown = array_diff_key($defaultContext, $this->acceptedContextOptions, self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE) === [];

        $defaultContextOptionsToOverrule = [];
        foreach (array_intersect_key($defaultContext, self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE) as $option => $value) {
            if ($value !== null
                && $value !== self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE[$option]
            ) {
                $defaultContextOptionsToOverrule[$option] = $option;
            }
        }
        $this->defaultContextOptionsToOverrule = $defaultContextOptionsToOverrule;
    }

    /**
     * Whether an option can be accepted through the configuration (see NOT_IMPLEMENTED_CONTEXT_OPTIONS).
     */
    public static function isContextOptionAcceptable(string $option): bool
    {
        return !in_array($option, self::NOT_IMPLEMENTED_CONTEXT_OPTIONS, true)
            && !array_key_exists($option, self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE);
    }

    /**
     * Not cacheable, as the support depends on the data and the context.
     *
     * @return array<string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => false,
        ];
    }

    // -- Denormalization

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
        $isSupported = is_array($data)
            && self::isFormatSupported($format)
            && $this->plan($type) !== false
            && $this->isContextSupported($context);
        if (!$isSupported
            && $this->recorder !== null
        ) {
            $this->recorder->notSupported(Debug\Recorder::DENORMALIZE, $type, $this->reasonOfNotSupported(false, is_array($data), $type, $format, $context));
        }

        return $isSupported;
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
    ): mixed {
        $plan = $this->plans[$type] ?? $this->plan($type);
        if (!is_array($data)
            || $plan === false
        ) {
            return $this->fallbackNormalizer->denormalize($data, $type, $format, $context);
        }

        /**
         * @var class-string $type Only classes have a plan
         */
        try {
            $object = $this->build(
                $data,
                $type,
                $plan,
                self::isIntCastToFloat($format),
                $format,
                $context,
            );
        } catch (Exception\FallbackIsRequired $exception) {
            $this->recorder?->fellBack(Debug\Recorder::DENORMALIZE, $type, $exception->describe());

            return $this->fallbackNormalizer->denormalize($data, $type, $format, $context);
        }

        $this->recorder?->handledByPlan(Debug\Recorder::DENORMALIZE, $type);

        return $object;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param class-string            $class
     * @param ClassPlan               $plan
     * @param array<string, mixed>    $context
     *
     * @throws Exception\FallbackIsRequired
     */
    private function build(
        array $data,
        string $class,
        array $plan,
        bool $isIntCastToFloat,
        ?string $format,
        array $context,
    ): object {
        $arguments = [];
        foreach ($plan['parameters'] as [$name, $kind, $targetClass, $allowsNull, $hasDefault, $default, $extra, $isList]) {
            if (!array_key_exists($name, $data)) {
                // Same order as AbstractNormalizer::instantiateObject(): default value, then null for a nullable parameter
                if ($hasDefault) {
                    $arguments[$name] = $default;
                    continue;
                }
                if ($allowsNull) {
                    $arguments[$name] = null;
                    continue;
                }

                throw Exception\FallbackIsRequired::missingValue()->at($class, $name);
            }

            $value = $data[$name];
            if ($value === null) {
                if ($allowsNull) {
                    $arguments[$name] = null;
                    continue;
                }

                throw Exception\FallbackIsRequired::nullForNonNullableParameter()->at($class, $name);
            }

            // symfony/serializer 8.2 deprecates an array that isn't a list for a list, 8.1 accepts it: left to the PropertyNormalizer
            if ($isList
                && is_array($value)
                && !array_is_list($value)
            ) {
                throw Exception\FallbackIsRequired::notAList()->at($class, $name);
            }

            /**
             * @var class-string $targetClass Only used for the kinds with a class
             */
            try {
                $arguments[$name] = match ($kind) {
                    ParameterKind::STRING => is_string($value)
                        ? $value
                        : throw Exception\FallbackIsRequired::unexpectedValue('string', $value),
                    ParameterKind::INT => is_int($value)
                        ? $value
                        : throw Exception\FallbackIsRequired::unexpectedValue('int', $value),
                    ParameterKind::FLOAT => self::denormalizeFloat($value, $isIntCastToFloat),
                    ParameterKind::BOOL => is_bool($value)
                        ? $value
                        : throw Exception\FallbackIsRequired::unexpectedValue('bool', $value),
                    ParameterKind::MIXED => $value,
                    ParameterKind::ARRAY => is_array($value)
                        ? $value
                        : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
                    ParameterKind::SCALAR_LIST => is_array($value)
                        /**
                         * @var ScalarListSpec $extra
                         */
                        ? self::validateScalarList($value, $extra, $isIntCastToFloat)
                        : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
                    ParameterKind::VALUE_OBJECT_STRING,
                    ParameterKind::VALUE_OBJECT_INT,
                    ParameterKind::VALUE_OBJECT_FLOAT,
                    ParameterKind::VALUE_OBJECT_BOOL,
                    ParameterKind::VALUE_OBJECT_ARRAY => self::denormalizeValueObject($value, $kind, $targetClass),
                    ParameterKind::NESTED => is_array($value)
                        ? $this->buildNested($value, $targetClass, $isIntCastToFloat, $format, $context)
                        : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
                    ParameterKind::OBJECT => $this->denormalizeThroughSerializer(
                        $value,
                        $targetClass,
                        $format,
                        self::contextOfObject($value, $extra === true, $context),
                    ),
                    ParameterKind::VALUE_OBJECT_LIST => is_array($value)
                        /**
                         * @var list{0: int, 1: int} $extra
                         */
                        ? self::denormalizeValueObjectList($value, $targetClass, $extra[0], $extra[1])
                        : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
                    ParameterKind::NESTED_LIST => is_array($value)
                        /**
                         * @var non-empty-list<int> $extra
                         */
                        ? $this->buildNestedList($value, $targetClass, $extra, $isIntCastToFloat, $format, $context)
                        : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
                    ParameterKind::SCALAR_UNION_LIST => is_array($value)
                        /**
                         * @var list{0: list<int>, 1: bool, 2: int} $extra
                         */
                        ? self::validateScalarUnionList($value, $extra[0], $extra[1], $extra[2], $isIntCastToFloat)
                        : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
                    ParameterKind::UNION => $extra !== null
                        /**
                         * @var list<int> $extra
                         */
                        ? self::validateScalarUnionElement($value, $extra, false, $isIntCastToFloat)
                        : throw Exception\FallbackIsRequired::union(),
                    ParameterKind::UNION_LIST => $value === []
                        ? $value
                        : throw Exception\FallbackIsRequired::elementsOfUnion(),
                    ParameterKind::OBJECT_LIST => is_array($value)
                        /**
                         * @var list{0: Type, 1: Type, 2: string} $extra
                         */
                        ? $this->denormalizeThroughSerializer(
                            $value,
                            $targetClass.$extra[2],
                            $format,
                            [
                                'key_type' => $extra[0],
                                'value_type' => $extra[1],
                            ] + $context,
                        )
                        : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
                };
            } catch (Exception\FallbackIsRequired $exception) {
                throw $exception->at($class, $name);
            }
        }

        // Named arguments (like the PropertyNormalizer), so a plan that doesn't match the constructor (anymore) fails instead of passing
        // values to the wrong parameters
        try {
            return new $class(...$arguments);
        } catch (\Error $error) {
            // A TypeError or an unknown or missing parameter: the PropertyNormalizer reports it through reflection, with its own message
            throw Exception\FallbackIsRequired::constructorFailed($error)->at($class, null);
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param class-string            $class
     * @param array<string, mixed>    $context
     *
     * @throws Exception\FallbackIsRequired
     */
    private function buildNested(
        array $data,
        string $class,
        bool $isIntCastToFloat,
        ?string $format,
        array $context,
    ): object {
        $plan = ($this->plans[$class] ?? $this->plan($class)) ?: throw Exception\FallbackIsRequired::noPlan($class);

        return $this->build($data, $class, $plan, $isIntCastToFloat, $format, $context);
    }

    /**
     * Like the ArrayDenormalizer with each element denormalized by the known construction normalizer. A nested collection (X[][], ...) is denormalized
     * level by level like by the ArrayDenormalizer: every element of an outer level must be an array, the keys of each level are checked
     * against the KeyCheck the ArrayDenormalizer applies there (see PlanFactory::classifyCollectionOfObjects() for nullable inner levels).
     *
     * @param array<array-key, mixed> $data
     * @param class-string            $class
     * @param non-empty-list<int>     $keyChecks KeyCheck per level, outer level first
     * @param array<string, mixed>    $context
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception\FallbackIsRequired
     */
    private function buildNestedList(
        array $data,
        string $class,
        array $keyChecks,
        bool $isIntCastToFloat,
        ?string $format,
        array $context,
    ): array {
        $plan = ($this->plans[$class] ?? $this->plan($class)) ?: throw Exception\FallbackIsRequired::noPlan($class);

        return $this->buildNestedListLevel($data, $class, $plan, $keyChecks, 0, $isIntCastToFloat, $format, $context);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param class-string            $class
     * @param ClassPlan               $plan
     * @param non-empty-list<int>     $keyChecks
     * @param array<string, mixed>    $context
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception\FallbackIsRequired
     */
    private function buildNestedListLevel(
        array $data,
        string $class,
        array $plan,
        array $keyChecks,
        int $level,
        bool $isIntCastToFloat,
        ?string $format,
        array $context,
    ): array {
        $keyCheck = $keyChecks[$level];
        $isInnermostLevel = !isset($keyChecks[$level + 1]);
        foreach ($data as $key => $element) {
            if (!is_array($element)) {
                throw Exception\FallbackIsRequired::unexpectedValue('array', $element);
            }

            self::validateKey($key, $keyCheck);

            $data[$key] = $isInnermostLevel
                ? $this->build($element, $class, $plan, $isIntCastToFloat, $format, $context)
                : $this->buildNestedListLevel($element, $class, $plan, $keyChecks, $level + 1, $isIntCastToFloat, $format, $context);
        }

        return $data;
    }

    /**
     * Like AbstractObjectNormalizer::validateAndDenormalize() for an object of a nullable (union) type: a scalar is denormalized as union
     * member, so an object normalizer throws instead of building an object from it.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function contextOfObject(
        mixed $value,
        bool $isNullable,
        array $context,
    ): array {
        return $isNullable
            && !is_array($value)
            && !is_object($value)
                ? ['_union_member' => true] + $context
                : $context;
    }

    /**
     * Same as the self-aware normalizers, with the type of the value checked before, so that a TypeError is thrown by the
     * PropertyNormalizer path with its message.
     *
     * @param class-string $class
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function denormalizeValueObject(
        mixed $value,
        int $kind,
        string $class,
    ): object {
        try {
            return self::denormalizeValueObjectOfKind($value, $kind, $class);
        } catch (UnsupportedException) {
            // AbstractObjectNormalizer::validateAndDenormalize() swallows it for a nullable (union) type and throws its own exception
            // instead. Every other exception of the serializer (and any other exception) is thrown by the PropertyNormalizer as is,
            // with or without a nullable type, so it's left to bubble up the same way.
            throw Exception\FallbackIsRequired::valueObjectNotDenormalizable();
        }
    }

    /**
     * @param class-string $class
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function denormalizeValueObjectOfKind(
        mixed $value,
        int $kind,
        string $class,
    ): object {
        return match ($kind) {
            /**
             * @var class-string<StringNormalizable> $class
             */
            ParameterKind::VALUE_OBJECT_STRING => is_string($value)
                ? $class::denormalize($value)
                : throw Exception\FallbackIsRequired::unexpectedValue('string', $value),
            /**
             * @var class-string<IntNormalizable> $class
             */
            ParameterKind::VALUE_OBJECT_INT => is_int($value)
                ? $class::denormalize($value)
                : throw Exception\FallbackIsRequired::unexpectedValue('int', $value),
            /**
             * @var class-string<FloatNormalizable> $class
             */
            ParameterKind::VALUE_OBJECT_FLOAT => is_float($value)
            || is_int($value)
                ? $class::denormalize($value)
                : throw Exception\FallbackIsRequired::unexpectedValue('float', $value),
            /**
             * @var class-string<BoolNormalizable> $class
             */
            ParameterKind::VALUE_OBJECT_BOOL => is_bool($value)
                ? $class::denormalize($value)
                : throw Exception\FallbackIsRequired::unexpectedValue('bool', $value),
            /**
             * @var class-string<ArrayNormalizable> $class
             */
            ParameterKind::VALUE_OBJECT_ARRAY => is_array($value)
                ? $class::denormalize($value)
                : throw Exception\FallbackIsRequired::unexpectedValue('array', $value),
        };
    }

    /**
     * Like the ArrayDenormalizer with the elements denormalized by the self-aware normalizers (which keep null as null).
     *
     * @param array<array-key, mixed> $data
     * @param class-string            $class
     *
     * @return array<array-key, ?object>
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function denormalizeValueObjectList(
        array $data,
        string $class,
        int $kind,
        int $keyCheck,
    ): array {
        foreach ($data as $key => $element) {
            self::validateKey($key, $keyCheck);

            $data[$key] = $element === null
                ? null
                : self::denormalizeValueObject($element, $kind, $class);
        }

        /**
         * @var array<array-key, ?object>
         */
        return $data;
    }

    /**
     * Same as AbstractObjectNormalizer::validateAndDenormalize() for any other class. A failure is reproduced by the PropertyNormalizer,
     * so the exception carries its deserialization path.
     *
     * @param array<string, mixed> $context
     *
     * @throws Exception\FallbackIsRequired
     */
    private function denormalizeThroughSerializer(
        mixed $value,
        string $type,
        ?string $format,
        array $context,
    ): mixed {
        if ($value instanceof $type) {
            return $value;
        }

        unset($context['cache_key']);

        try {
            if ($this->denormalizer->supportsDenormalization($value, $type, $format, $context)) {
                return $this->denormalizer->denormalize($value, $type, $format, $context);
            }
        } catch (\Throwable $exception) {
            throw Exception\FallbackIsRequired::serializerFailed($type, $exception);
        }

        throw Exception\FallbackIsRequired::serializerFailed($type, null);
    }

    /**
     * Same as the scalar collection handling of AbstractObjectNormalizer::validateAndDenormalize(): elements of the expected type are
     * kept, an int becomes a float with a JSON format.
     *
     * @param array<array-key, mixed> $data
     * @param ScalarListSpec          $spec
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function validateScalarList(
        array $data,
        array $spec,
        bool $isIntCastToFloat,
    ): array {
        foreach ($data as $key => $element) {
            $data[$key] = self::validateScalarElement($element, $spec, $isIntCastToFloat);
        }

        return $data;
    }

    /**
     * @param ScalarListSpec $spec
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function validateScalarElement(
        mixed $element,
        array $spec,
        bool $isIntCastToFloat,
    ): mixed {
        [$kind, $isNullable, $innerSpec, $isList] = $spec;
        if ($element === null) {
            return $isNullable
                ? null
                : throw Exception\FallbackIsRequired::unexpectedValue('non-null element', $element);
        }

        return match ($kind) {
            ParameterKind::STRING => is_string($element)
                ? $element
                : throw Exception\FallbackIsRequired::unexpectedValue('string', $element),
            ParameterKind::INT => is_int($element)
                ? $element
                : throw Exception\FallbackIsRequired::unexpectedValue('int', $element),
            ParameterKind::BOOL => is_bool($element)
                ? $element
                : throw Exception\FallbackIsRequired::unexpectedValue('bool', $element),
            ParameterKind::FLOAT => self::denormalizeFloat($element, $isIntCastToFloat),
            ParameterKind::SCALAR_LIST => is_array($element)
                /**
                 * @var ScalarListSpec $innerSpec
                 */
                ? self::validateScalarList(self::listOrFallback($element, $isList), $innerSpec, $isIntCastToFloat)
                : throw Exception\FallbackIsRequired::unexpectedValue('array', $element),
        };
    }

    /**
     * A nested level of a collection that is typed as list must be a list (see the check of the parameter in build()).
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function listOrFallback(
        array $data,
        bool $isList,
    ): array {
        if ($isList
            && !array_is_list($data)
        ) {
            throw Exception\FallbackIsRequired::notAList();
        }

        return $data;
    }

    /**
     * Like symfony/serializer 8.2 for a collection of a union of scalars: every key must match the key type, every element must have the
     * type of a member (null only for a nullable element type). An int for a union with float but without int may be converted, which is
     * left to the PropertyNormalizer.
     *
     * @param array<array-key, mixed> $data
     * @param list<int>               $kinds
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function validateScalarUnionList(
        array $data,
        array $kinds,
        bool $isNullable,
        int $keyCheck,
        bool $isIntCastToFloat,
    ): array {
        foreach ($data as $key => $element) {
            self::validateKey($key, $keyCheck);
            self::validateScalarUnionElement($element, $kinds, $isNullable, $isIntCastToFloat);
        }

        return $data;
    }

    /**
     * Like validateAndDenormalize() of the PropertyNormalizer for a union of scalars, which tries the members in order: a value with the
     * type of a member is kept as is by every order. An int for a union with float may be converted by the float member with the JSON
     * format (depending on the order of the members), which is left to the PropertyNormalizer.
     *
     * @param list<int> $kinds
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function validateScalarUnionElement(
        mixed $element,
        array $kinds,
        bool $isNullable,
        bool $isIntCastToFloat,
    ): mixed {
        $kind = match (true) {
            is_string($element) => ParameterKind::STRING,
            is_int($element) => ParameterKind::INT,
            is_float($element) => ParameterKind::FLOAT,
            is_bool($element) => ParameterKind::BOOL,
            default => null,
        };
        $isValid = $kind === null
            ? $element === null && $isNullable
            : in_array($kind, $kinds, true);
        if (!$isValid
            || (
                $kind === ParameterKind::INT
                && $isIntCastToFloat
                && in_array(ParameterKind::FLOAT, $kinds, true)
            )
        ) {
            throw Exception\FallbackIsRequired::unexpectedValue('a member of the union', $element);
        }

        return $element;
    }

    /**
     * @throws Exception\FallbackIsRequired
     */
    private static function denormalizeFloat(
        mixed $value,
        bool $isIntCastToFloat,
    ): float {
        if (is_float($value)) {
            return $value;
        }

        // JSON has no distinction between int and float, the PropertyNormalizer casts with a JSON format only
        if (is_int($value)
            && $isIntCastToFloat
        ) {
            return (float) $value;
        }

        throw Exception\FallbackIsRequired::unexpectedValue('float', $value);
    }

    /**
     * Key check of ArrayDenormalizer::validateKeyType().
     *
     * @throws Exception\FallbackIsRequired
     */
    private static function validateKey(
        int | string $key,
        int $keyCheck,
    ): void {
        $isKeyValid = match ($keyCheck) {
            KeyCheck::INT => is_int($key),
            KeyCheck::STRING => is_string($key),
            KeyCheck::UNCHECKED => true,
        };
        if (!$isKeyValid) {
            throw Exception\FallbackIsRequired::invalidKey($key);
        }
    }

    /**
     * The formats the chain of normalizers is checked for (see NormalizerChain). XML and CSV would enable the type conversion of the
     * PropertyNormalizer as well.
     */
    private static function isFormatSupported(?string $format): bool
    {
        return $format === null
            || $format === 'json';
    }

    private static function isIntCastToFloat(?string $format): bool
    {
        return $format !== null
            && str_contains($format, 'json');
    }

    // -- Normalization

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): bool {
        if (!is_object($data)) {
            return false;
        }

        $isSupported = self::isFormatSupported($format)
            && ($plan = $this->plans[$data::class] ?? $this->plan($data::class)) !== false
            && $plan['isNormalizable']
            && $this->isContextSupported($context);
        if (!$isSupported
            && $this->recorder !== null
        ) {
            $this->recorder->notSupported(Debug\Recorder::NORMALIZE, $data::class, $this->reasonOfNotSupported(true, true, $data::class, $format, $context));
        }

        return $isSupported;
    }

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): array | \ArrayObject {
        $plan = is_object($data)
            ? $this->plans[$data::class] ?? $this->plan($data::class)
            : false;
        if ($plan === false
            || !$plan['isNormalizable']
        ) {
            if (is_object($data)) {
                $this->recorder?->fellBack(Debug\Recorder::NORMALIZE, $data::class, Debug\Recorder::REASON_NO_PLAN);
            }

            /**
             * @var array<string, mixed>|\ArrayObject<string, mixed>
             */
            return $this->fallbackNormalizer->normalize($data, $format, $context);
        }

        /**
         * @var object $data
         */
        unset($context['cache_key']);
        $this->recorder?->handledByPlan(Debug\Recorder::NORMALIZE, $data::class);

        return $this->normalizeObject(
            $data,
            $plan,
            $format,
            $context,
            (bool) ($context[AbstractObjectNormalizer::SKIP_NULL_VALUES] ?? $this->defaultContext[AbstractObjectNormalizer::SKIP_NULL_VALUES] ?? false),
            (bool) ($context[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS] ?? $this->defaultContext[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS] ?? false),
        );
    }

    /**
     * Same as AbstractObjectNormalizer::normalize() for a class with public properties only.
     *
     * @param ClassPlan            $plan
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|\ArrayObject<string, mixed>
     */
    private function normalizeObject(
        object $object,
        array $plan,
        ?string $format,
        array $context,
        bool $isNullSkipped,
        bool $isEmptyObjectPreserved,
    ): array | \ArrayObject {
        $normalizedData = [];
        foreach ($plan['properties'] as $property) {
            /**
             * @psalm-suppress MixedAssignment
             */
            $value = $object->{$property};
            if ($value !== null
                && !is_scalar($value)
            ) {
                $value = $this->normalizeValue($value, $format, $context, $isNullSkipped, $isEmptyObjectPreserved);
            }

            if ($value === null
                && $isNullSkipped
            ) {
                continue;
            }

            $normalizedData[$property] = $value;
        }

        if ($normalizedData === []
            && $isEmptyObjectPreserved
        ) {
            return new \ArrayObject();
        }

        return $normalizedData;
    }

    /**
     * Same as Serializer::normalize() for the values of a planned object.
     *
     * @param array<string, mixed> $context
     */
    private function normalizeValue(
        mixed $value,
        ?string $format,
        array $context,
        bool $isNullSkipped,
        bool $isEmptyObjectPreserved,
    ): mixed {
        if ($value === null
            || is_scalar($value)
        ) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $element) {
                $value[$key] = $this->normalizeValue($element, $format, $context, $isNullSkipped, $isEmptyObjectPreserved);
            }

            return $value;
        }

        if (!is_object($value)) {
            return $this->normalizer->normalize($value, $format, $context);
        }

        $class = $value::class;
        $normalizationKind = $this->normalizationKinds[$class] ?? $this->normalizationKind($class);

        if ($normalizationKind === self::NORMALIZATION_VALUE_OBJECT) {
            /**
             * @var StringNormalizable|IntNormalizable|FloatNormalizable|BoolNormalizable|ArrayNormalizable $value
             */
            return $value->normalize();
        }

        if ($normalizationKind === self::NORMALIZATION_PLANNED) {
            /**
             * @var ClassPlan $plan
             */
            $plan = $this->plans[$class];

            return $this->normalizeObject($value, $plan, $format, $context, $isNullSkipped, $isEmptyObjectPreserved);
        }

        return $this->normalizer->normalize($value, $format, $context);
    }

    /**
     * @param class-string $class
     */
    private function normalizationKind(string $class): int
    {
        $plan = $this->plan($class);

        $normalizationKind = match (true) {
            $this->isValueObjectHandledInline($class) => self::NORMALIZATION_VALUE_OBJECT,
            $plan !== false && $plan['isNormalizable'] => self::NORMALIZATION_PLANNED,
            default => self::NORMALIZATION_SERIALIZER,
        };

        $this->normalizationKinds[$class] = $normalizationKind;

        return $normalizationKind;
    }

    /**
     * A value object is normalized through its normalize() directly, unless a normalizer in front of the KnownConstructionNormalizer claims
     * it (see NormalizerChain).
     */
    private function isValueObjectHandledInline(string $class): bool
    {
        return PlanFactory::valueObjectKind($class) !== null
            && $this->planFactory->isHandledInline($class);
    }

    // -- Plans

    /**
     * @return ClassPlan|false
     */
    private function plan(string $class): array | false
    {
        if (array_key_exists($class, $this->plans)) {
            return $this->plans[$class];
        }

        /**
         * @var class-string $class Any type, the PlanFactory leaves types that aren't a class without plan
         */
        $planCache = $this->planCache;
        if ($planCache === null) {
            return $this->plans[$class] = $this->createRecordedPlan($class);
        }

        $cacheItem = $planCache->getItem(sprintf(
            '%s.%s.%s',
            self::PLAN_CACHE_KEY_PREFIX,
            $this->planCacheVersion,
            strtr($class, '\\', '.'),
        ));
        if ($cacheItem->isHit()) {
            /**
             * @var ClassPlan|false $plan
             */
            $plan = $cacheItem->get();
            $this->recorder?->planLoadedFromPool($class);

            return $this->plans[$class] = $plan;
        }

        $plan = $this->createRecordedPlan($class);
        $planCache->save($cacheItem->set($plan));

        return $this->plans[$class] = $plan;
    }

    /**
     * createPlan() with the time it took for the recorder (only measured with a recorder).
     *
     * @param class-string $class
     *
     * @return ClassPlan|false
     */
    private function createRecordedPlan(string $class): array | false
    {
        if ($this->recorder === null) {
            return $this->createPlan($class);
        }

        $start = hrtime(true);
        $plan = $this->createPlan($class);
        $this->recorder->planBuilt($class, (hrtime(true) - $start) / 1_000_000);

        return $plan;
    }

    /**
     * A class referencing itself (directly or through other classes) isn't planned: data that's only invalid deep down would fall back on
     * every level of the recursion (the PropertyNormalizer re-denormalizing the planned levels below again and again).
     *
     * @param class-string $class
     *
     * @return ClassPlan|false
     */
    private function createPlan(string $class): array | false
    {
        $this->plansInProgress[$class] = $class;
        try {
            $plan = $this->planFactory->create($class, $this->hasPlanOutsideOfRecursion(...)) ?? false;
        } finally {
            unset($this->plansInProgress[$class]);
        }

        return isset($this->recursiveClasses[$class])
            ? false
            : $plan;
    }

    // -- Diagnosis

    /**
     * How the class is handled and why (for the profiler). Not used while (de)normalizing: the reason is taken from the PlanFactory again,
     * as plans only cache the outcome.
     */
    public function diagnose(string $class): Diagnosis\ClassDiagnosis
    {
        $plan = $this->plan($class);

        /**
         * @var class-string $class Any type, the PlanFactory diagnoses types that aren't a class as such
         */
        $planDiagnosis = $this->planFactory->diagnose(
            $class,
            // Whether another class has a plan only decides the kind of a parameter, never a limitation, which is all that's read here
            static fn (string $otherClass): bool => false,
        );

        if ($plan === false) {
            // The factory only builds a plan for a class the normalizer has none for when the class references itself: the classes of the
            // recursion have no plan, so the factory now denormalizes them through the serializer.
            return new Diagnosis\ClassDiagnosis(
                $class,
                $planDiagnosis->limitation ?? Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::RECURSIVE_CLASS),
                null,
                [],
            );
        }

        $delegatedParameters = [];
        $valueObjectParameters = [];
        $fallbackParameters = [];
        foreach ($plan['parameters'] as [$name, $kind, $targetClass, , , , $extra]) {
            if (($kind === ParameterKind::UNION && $extra === null)
                || $kind === ParameterKind::UNION_LIST
            ) {
                $fallbackParameters[$name] = $kind === ParameterKind::UNION
                    ? 'union with other members than scalars: denormalized through the fallback when set'
                    : 'collection of a union with other members than scalars: denormalized through the fallback when not empty';
            }

            if ($targetClass !== null
                && ParameterKind::isValueObject($kind)
            ) {
                $valueObjectParameters[$name] = $targetClass;
            }

            $typeSuffix = match ($kind) {
                ParameterKind::OBJECT => '',
                ParameterKind::OBJECT_LIST => self::typeSuffixOfObjectList($extra),
                default => null,
            };
            if ($targetClass !== null
                && $typeSuffix !== null
            ) {
                $delegatedParameters[] = new Diagnosis\DelegatedParameter($name, $targetClass, $typeSuffix);
            }
        }

        return new Diagnosis\ClassDiagnosis(
            $class,
            null,
            $planDiagnosis->normalizationLimitation,
            $delegatedParameters,
            $valueObjectParameters,
            $fallbackParameters,
        );
    }

    /**
     * The "[]" per level of an OBJECT_LIST parameter (see ParameterKind).
     */
    private static function typeSuffixOfObjectList(mixed $extra): string
    {
        /**
         * @var list{0: Type, 1: Type, 2: string} $extra
         */
        return $extra[2];
    }

    /**
     * Whether another class has a plan, while a plan is built. A class whose plan is in progress references itself (directly or through
     * other classes), so it and the classes of the recursion are marked as recursive.
     */
    private function hasPlanOutsideOfRecursion(string $otherClass): bool
    {
        if (isset($this->plansInProgress[$otherClass])) {
            $this->markClassesOfRecursionAsRecursive($otherClass);

            return false;
        }

        return $this->plan($otherClass) !== false;
    }

    /**
     * Marks the class and all classes whose plan is built from it (the classes in progress after it) as recursive.
     */
    private function markClassesOfRecursionAsRecursive(string $class): void
    {
        $isInRecursion = false;
        foreach ($this->plansInProgress as $classInProgress) {
            if ($classInProgress === $class) {
                $isInRecursion = true;
            }
            if ($isInRecursion) {
                $this->recursiveClasses[$classInProgress] = $classInProgress;
            }
        }
    }

    // -- Debug

    /**
     * Why supports*() declined, for the recorder. Only called with a recorder, after the check failed. For the denormalization, a class
     * without plan is reported as such, even if the data isn't an array either.
     *
     * @param array<string, mixed> $context
     */
    private function reasonOfNotSupported(
        bool $isNormalization,
        bool $isDataSupported,
        string $class,
        ?string $format,
        array $context,
    ): string {
        if (!self::isFormatSupported($format)) {
            return sprintf('the format "%s" is not supported', (string) $format);
        }

        $plan = $this->plan($class);
        if ($plan === false
            || (
                $isNormalization
                && !$plan['isNormalizable']
            )
        ) {
            return Debug\Recorder::REASON_NO_PLAN;
        }

        return $isDataSupported
            ? $this->reasonOfUnsupportedContext($context)
            : 'the data is not an array';
    }

    /**
     * The options of the context and the default context that aren't accepted (see isContextSupported()).
     *
     * @param array<string, mixed> $context
     */
    private function reasonOfUnsupportedContext(array $context): string
    {
        $reasons = [];
        foreach (array_keys($context + $this->defaultContext) as $option) {
            if (isset($this->acceptedContextOptions[$option])) {
                continue;
            }

            if (!array_key_exists($option, self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE)) {
                $reasons[] = sprintf('the context option "%s" is unknown', $option);
                continue;
            }

            $acceptedValue = self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE[$option];
            if (($context[$option] ?? $this->defaultContext[$option] ?? $acceptedValue) !== $acceptedValue) {
                $reasons[] = sprintf('the context option "%s" is only supported with %s', $option, var_export($acceptedValue, true));
            }
        }

        return implode(', ', $reasons);
    }

    /**
     * The context options the known construction normalizer is used with: option => accepted value (null for any value).
     *
     * @return array<string, bool|null>
     */
    public function acceptedContextOptions(): array
    {
        return array_fill_keys(array_keys($this->acceptedContextOptions), null) + self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE;
    }

    /**
     * Only contexts whose options are all accepted (see ACCEPTED_CONTEXT_OPTIONS), merged with the default context like by the
     * PropertyNormalizer: an option of the context overrules the one of the default context, unless it's null.
     *
     * @param array<string, mixed> $context
     */
    private function isContextSupported(array $context): bool
    {
        if (!$this->isDefaultContextKnown) {
            return false;
        }

        foreach ($context as $option => $value) {
            if (isset($this->acceptedContextOptions[$option])) {
                continue;
            }
            if (!array_key_exists($option, self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE)) {
                return false;
            }

            // Null takes the value of the default context, which is checked below
            if ($value !== null
                && $value !== self::CONTEXT_OPTIONS_WITH_ACCEPTED_VALUE[$option]
            ) {
                return false;
            }
        }

        // Options of the default context without the accepted value must be overruled by the context (with the accepted value, see
        // above)
        foreach ($this->defaultContextOptionsToOverrule as $option) {
            if (!isset($context[$option])) {
                return false;
            }
        }

        return true;
    }
}
