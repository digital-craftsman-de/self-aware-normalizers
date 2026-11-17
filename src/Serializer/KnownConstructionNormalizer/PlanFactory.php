<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\BoolNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\TypeInfo\Exception\ExceptionInterface as TypeInfoException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionTypeResolver;

/**
 * Builds the plan of a class for the KnownConstructionNormalizer: per constructor parameter how its value is denormalized, and the properties that
 * are normalized. Only classes whose shape the plan can express exactly as the PropertyNormalizer (de)normalizes them get a plan:
 * - a public constructor whose parameters are exactly the non-static properties of the class, all promoted, public and without hooks
 *   (no inherited properties, no variadic or by-reference parameters),
 * - each parameter has a type the plan can handle (see classifyType()); the type is resolved exactly like
 *   AbstractObjectNormalizer::denormalizeParameter() does it (type extractor of the PropertyNormalizer, overruled by the native
 *   parameter type when they disagree),
 * - no value object of digital-craftsman/self-aware-normalizers, no enum and no abstract class (those never reach the normalizer;
 *   the order in which diagnose() checks them only decides the reported reason).
 *
 * Everything else returns null, so the class stays with the PropertyNormalizer. diagnose() returns the reason instead (the first
 * limitation found), for the profiler panel; create() is built on it, so the reason is always the one that decided.
 *
 * Plans only contain scalars, arrays and the types of collections of other objects, so they can be cached in a PSR-6 pool.
 *
 * Failure contract: create() returns null (diagnose() the limitation) for a shape the plan can't express and for a type it can't resolve,
 * so the class stays with the PropertyNormalizer. Types are only resolved and classified within the try of createParameterPlan(), which
 * turns TypeCanNotBePlanned and the exceptions of symfony/type-info and reflection into the limitation TYPE_IS_NOT_RESOLVABLE: neither
 * method throws for an existing class. Keep any new type resolution within that try. A bug here therefore only makes classes slower,
 * never different; it shows up as fewer planned classes, which the equivalence test of the bundle guards with its minimum number of
 * planned classes.
 *
 * A parameter plan is a list (destructured in the hot loop): name, ParameterKind, target class, whether the parameter allows null,
 * whether it has a default value, the default value, and the extra slot of the kind (a ParameterExtra, see ParameterKind; typed mixed in the
 * plan, as it's narrowed per kind by KnownConstructionNormalizer::build()).
 *
 * @psalm-type ScalarListSpec = list{
 *   0: int,
 *   1: bool,
 *   2: mixed,
 *   3: bool,
 * }
 * @psalm-type ParameterExtra = null|bool|ScalarListSpec|non-empty-list<int>|list{
 *   0: int,
 *   1: int,
 * }|list{
 *   0: list<int>,
 *   1: bool,
 *   2: int,
 * }|list{
 *   0: Type,
 *   1: Type,
 *   2: string,
 * }
 * @psalm-type ParameterPlan = list{
 *   0: string,
 *   1: int,
 *   2: ?class-string,
 *   3: bool,
 *   4: bool,
 *   5: mixed,
 *   6: mixed,
 *   7: bool,
 * }
 * @psalm-type ClassPlan = array{
 *   parameters: list<ParameterPlan>,
 *   properties: list<string>,
 *   isNormalizable: bool,
 * }
 */
final readonly class PlanFactory
{
    private const array KINDS_OF_SCALAR_TYPES = [
        'string' => ParameterKind::STRING,
        'int' => ParameterKind::INT,
        'float' => ParameterKind::FLOAT,
        'bool' => ParameterKind::BOOL,
    ];

    private const array KEY_CHECKS = [
        'int|string' => KeyCheck::UNCHECKED,
        'int' => KeyCheck::INT,
        'string' => KeyCheck::STRING,
    ];

    private const array KINDS_OF_BUILTIN_TYPES = self::KINDS_OF_SCALAR_TYPES + [
        'mixed' => ParameterKind::MIXED,
        'array' => ParameterKind::ARRAY,
    ];

    private ReflectionTypeResolver $reflectionTypeResolver;
    private TypeContextFactory $typeContextFactory;

    /**
     * @param PropertyTypeExtractorInterface $propertyTypeExtractor Must be the type extractor of the PropertyNormalizer the
     *                                                              KnownConstructionNormalizer falls back to
     * @param NormalizerChain                $normalizerChain       The normalizers of the serializer in front of the
     *                                                              KnownConstructionNormalizer, which may claim classes the plan would
     *                                                              otherwise handle inline
     */
    public function __construct(
        private PropertyTypeExtractorInterface $propertyTypeExtractor,
        private NormalizerChain $normalizerChain = new NormalizerChain(),
    ) {
        $this->reflectionTypeResolver = new ReflectionTypeResolver();
        $this->typeContextFactory = new TypeContextFactory();
    }

    /**
     * The same factory with another type extractor (e.g. for a test that proves that plans are read from a cache).
     */
    public function withPropertyTypeExtractor(PropertyTypeExtractorInterface $propertyTypeExtractor): self
    {
        return new self($propertyTypeExtractor, $this->normalizerChain);
    }

    /**
     * Whether a class can be handled inline (planned, or as value object through its own methods): only when no normalizer the serializer
     * asks before the KnownConstructionNormalizer claims it (see NormalizerChain).
     */
    public function isHandledInline(string $class): bool
    {
        return $this->normalizerChain->claimOf($class) === null;
    }

    /**
     * @param class-string                 $class
     * @param \Closure(class-string): bool $hasPlan Whether another class has a plan (to denormalize it directly)
     *
     * @return ClassPlan|null Null when the shape of the class can't be expressed by a plan
     */
    public function create(
        string $class,
        \Closure $hasPlan,
    ): ?array {
        return $this->diagnose($class, $hasPlan)->plan;
    }

    /**
     * The plan of the class, or why the class can't get one. create() is built on it, so the reported limitation is always the one that
     * decided. Only called when a plan is built (once per class), never per object.
     *
     * @param class-string                 $class
     * @param \Closure(class-string): bool $hasPlan Whether another class has a plan (to denormalize it directly)
     */
    public function diagnose(
        string $class,
        \Closure $hasPlan,
    ): Diagnosis\PlanDiagnosis {
        if (!class_exists($class)
            && !interface_exists($class)
        ) {
            return Diagnosis\PlanDiagnosis::notPlanned(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::NOT_A_CLASS));
        }

        $reflectionClass = new \ReflectionClass($class);
        if (!$reflectionClass->isInterface()
            && !$reflectionClass->isEnum()
            && !$reflectionClass->isAbstract()
        ) {
            $claim = $this->normalizerChain->claimOf($class);
            if ($claim !== null) {
                return Diagnosis\PlanDiagnosis::notPlanned(new Diagnosis\PlanLimitation(Diagnosis\PlanLimitationReason::CLAIMED_BY_OTHER_NORMALIZER, null, $claim));
            }
        }

        $limitationOfKind = match (true) {
            $reflectionClass->isInterface() => Diagnosis\PlanLimitationReason::INTERFACE,
            self::valueObjectKind($class) !== null => Diagnosis\PlanLimitationReason::SELF_AWARE_VALUE_OBJECT,
            $reflectionClass->isEnum() => Diagnosis\PlanLimitationReason::ENUM,
            $reflectionClass->isAbstract() => Diagnosis\PlanLimitationReason::ABSTRACT_CLASS,
            $reflectionClass->isInternal() => Diagnosis\PlanLimitationReason::INTERNAL_CLASS,
            // Exceptions are never read models, DTOs, commands, queries or events (and have inherited, non-public properties anyway)
            $reflectionClass->implementsInterface(\Throwable::class) => Diagnosis\PlanLimitationReason::EXCEPTION,
            // The name of an anonymous class can't be part of the key of a cached plan
            $reflectionClass->isAnonymous() => Diagnosis\PlanLimitationReason::ANONYMOUS_CLASS,
            // The only remaining reason for a class not to be instantiable
            !$reflectionClass->isInstantiable() => Diagnosis\PlanLimitationReason::CONSTRUCTOR_IS_NOT_PUBLIC,
            default => null,
        };
        if ($limitationOfKind !== null) {
            return Diagnosis\PlanDiagnosis::notPlanned(Diagnosis\PlanLimitation::ofClass($limitationOfKind));
        }

        // Public, as the class is instantiable
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return Diagnosis\PlanDiagnosis::notPlanned(Diagnosis\PlanLimitation::ofClass(Diagnosis\PlanLimitationReason::NO_CONSTRUCTOR));
        }

        $properties = [];
        $normalizationLimitation = null;
        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $limitationOfProperty = match (true) {
                !$property->isPublic() => Diagnosis\PlanLimitationReason::PROPERTY_IS_NOT_PUBLIC,
                !$property->isPromoted() => Diagnosis\PlanLimitationReason::PROPERTY_IS_NOT_PROMOTED,
                $property->hasHooks() => Diagnosis\PlanLimitationReason::PROPERTY_HAS_HOOKS,
                $class !== $property->getDeclaringClass()->name => Diagnosis\PlanLimitationReason::PROPERTY_IS_INHERITED,
                default => null,
            };
            if ($limitationOfProperty !== null) {
                return Diagnosis\PlanDiagnosis::notPlanned(new Diagnosis\PlanLimitation(
                    $limitationOfProperty,
                    $property->name,
                    $limitationOfProperty === Diagnosis\PlanLimitationReason::PROPERTY_IS_INHERITED
                        ? sprintf('declared in %s', $property->getDeclaringClass()->name)
                        : null,
                ));
            }

            // Only objects of which every property is read only are normalized directly: their object graph is built bottom-up through
            // the constructors, so a circular reference (which the PropertyNormalizer detects and the plan doesn't) isn't expected there.
            if ($normalizationLimitation === null
                && !$property->isReadOnly()
            ) {
                $normalizationLimitation = new Diagnosis\PlanLimitation(Diagnosis\PlanLimitationReason::PROPERTY_IS_MUTABLE, $property->name, null);
            }

            $properties[] = $property->name;
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $parameterPlan = $this->createParameterPlan($reflectionClass, $parameter, $hasPlan);
            if ($parameterPlan instanceof Diagnosis\PlanLimitation) {
                return Diagnosis\PlanDiagnosis::notPlanned($parameterPlan);
            }

            $parameters[] = $parameterPlan;
        }

        // Every property is promoted, so the parameters must be exactly the properties (a parameter without a property would be treated
        // as missing by the PropertyNormalizer).
        $namesOfParameters = array_map(
            static fn (array $parameterPlan): string => $parameterPlan[0],
            $parameters,
        );
        if ($properties === []
            || $namesOfParameters !== $properties
        ) {
            return Diagnosis\PlanDiagnosis::notPlanned(new Diagnosis\PlanLimitation(
                Diagnosis\PlanLimitationReason::PARAMETERS_DIFFER_FROM_PROPERTIES,
                null,
                sprintf(
                    'parameters: %s, properties: %s',
                    json_encode($namesOfParameters, JSON_THROW_ON_ERROR),
                    json_encode($properties, JSON_THROW_ON_ERROR),
                ),
            ));
        }

        return Diagnosis\PlanDiagnosis::planned(
            [
                'parameters' => $parameters,
                'properties' => $properties,
                'isNormalizable' => $normalizationLimitation === null,
            ],
            $normalizationLimitation,
        );
    }

    /**
     * A type that can't be resolved (an exception of symfony/type-info or reflection, or TypeCanNotBePlanned) is a limitation like any
     * other: the PropertyNormalizer handles the class (and fails the same way, if at all).
     *
     * @param \ReflectionClass<object>     $reflectionClass
     * @param \Closure(class-string): bool $hasPlan
     *
     * @return ParameterPlan|Diagnosis\PlanLimitation
     */
    private function createParameterPlan(
        \ReflectionClass $reflectionClass,
        \ReflectionParameter $parameter,
        \Closure $hasPlan,
    ): array | Diagnosis\PlanLimitation {
        $limitationOfParameter = match (true) {
            $parameter->isVariadic() => Diagnosis\PlanLimitationReason::PARAMETER_IS_VARIADIC,
            $parameter->isPassedByReference() => Diagnosis\PlanLimitationReason::PARAMETER_IS_PASSED_BY_REFERENCE,
            !$parameter->isPromoted() => Diagnosis\PlanLimitationReason::PARAMETER_IS_NOT_PROMOTED,
            !$parameter->hasType() => Diagnosis\PlanLimitationReason::PARAMETER_HAS_NO_TYPE,
            default => null,
        };
        if ($limitationOfParameter !== null) {
            return new Diagnosis\PlanLimitation($limitationOfParameter, $parameter->name, null);
        }

        try {
            $type = $this->resolveParameterType($reflectionClass, $parameter);
            $classification = $this->classifyType($type, $hasPlan);
        } catch (Exception\TypeCanNotBePlanned | TypeInfoException | \ReflectionException $exception) {
            return new Diagnosis\PlanLimitation(Diagnosis\PlanLimitationReason::TYPE_IS_NOT_RESOLVABLE, $parameter->name, $exception->getMessage());
        }
        if ($classification instanceof Diagnosis\PlanLimitationReason) {
            return new Diagnosis\PlanLimitation($classification, $parameter->name, (string) $type);
        }

        $hasDefault = $parameter->isDefaultValueAvailable();
        $default = $hasDefault
            ? $parameter->getDefaultValue()
            : null;
        // A default object (other than an enum case) is a new instance on every call of the PropertyNormalizer, a cached plan would share
        // one instance.
        if (is_object($default)
            && !$default instanceof \UnitEnum
        ) {
            return new Diagnosis\PlanLimitation(Diagnosis\PlanLimitationReason::PARAMETER_HAS_OBJECT_DEFAULT_VALUE, $parameter->name, null);
        }

        [$kind, $targetClass, $extra] = $classification;

        // symfony/serializer 8.2 deprecates (9.0 rejects) an array that isn't a list for a property typed as list, 8.1 accepts it. The
        // plan leaves such data to the PropertyNormalizer, whatever the version.
        $collectionType = $type instanceof NullableType
            ? $type->getWrappedType()
            : $type;
        $isList = $collectionType instanceof CollectionType
            && $collectionType->isList();

        return [
            $parameter->name,
            $kind,
            $targetClass,
            $parameter->allowsNull(),
            $hasDefault,
            $default,
            $extra,
            $isList,
        ];
    }

    /**
     * Same type as AbstractObjectNormalizer::denormalizeParameter() uses.
     *
     * @param \ReflectionClass<object> $reflectionClass
     *
     * @throws Exception\TypeCanNotBePlanned
     */
    private function resolveParameterType(
        \ReflectionClass $reflectionClass,
        \ReflectionParameter $parameter,
    ): Type {
        // Without a type, the PropertyNormalizer would use AbstractNormalizer::denormalizeParameter() without type enforcement
        $type = $this->propertyTypeExtractor->getType($reflectionClass->name, $parameter->name) ?? throw new Exception\TypeCanNotBePlanned();
        /** @var \ReflectionType $parameterType Checked by createParameterPlan() (PARAMETER_HAS_NO_TYPE) */
        $parameterType = $parameter->getType();

        return $this->typeOverruledByParameterType($type, $parameterType, $reflectionClass, $parameter);
    }

    /**
     * @param \ReflectionClass<object> $reflectionClass
     */
    private function typeOverruledByParameterType(
        Type $type,
        \ReflectionType $parameterType,
        \ReflectionClass $reflectionClass,
        \ReflectionParameter $parameter,
    ): Type {
        /** @var \ReflectionClass<object> $declaringClass A parameter of a constructor always has one */
        $declaringClass = $parameter->getDeclaringClass();
        $resolvedParameterType = $this->reflectionTypeResolver->resolve(
            $parameterType,
            $this->typeContextFactory->createFromClassName($reflectionClass->name, $declaringClass->name),
        );

        $isMismatch = $resolvedParameterType->isSatisfiedBy(
            static fn (Type $parameterTypePart): bool => self::isMismatch($parameterTypePart, $type),
        );

        return $isMismatch
            ? $resolvedParameterType
            : $type;
    }

    /**
     * Same as the closure of AbstractObjectNormalizer::denormalizeParameter(): whether the extracted type doesn't match a part of the
     * native parameter type.
     */
    private static function isMismatch(
        Type $parameterTypePart,
        Type $type,
    ): bool {
        if ($parameterTypePart instanceof BuiltinType) {
            $typeIdentifier = $parameterTypePart->getTypeIdentifier();
            if ($typeIdentifier === TypeIdentifier::ARRAY
                || $typeIdentifier === TypeIdentifier::ITERABLE
            ) {
                return !$type->isIdentifiedBy(TypeIdentifier::ARRAY)
                    && !$type->isIdentifiedBy(TypeIdentifier::ITERABLE)
                    && !$type->isSatisfiedBy(static fn (Type $collectionType): bool => $collectionType instanceof CollectionType);
            }

            return $typeIdentifier !== TypeIdentifier::NULL
                && $typeIdentifier !== TypeIdentifier::MIXED
                && !$type->isIdentifiedBy($typeIdentifier);
        }

        return $parameterTypePart instanceof ObjectType
            && !$type->isIdentifiedBy($parameterTypePart->getClassName());
    }

    /**
     * Classifies the type the way AbstractObjectNormalizer::validateAndDenormalize() handles it. Only the success paths are planned: any
     * value the plan doesn't accept is handed to the PropertyNormalizer.
     *
     * @param \Closure(class-string): bool $hasPlan
     *
     * @return list{
     *   0: int,
     *   1: ?class-string,
     *   2: ParameterExtra,
     * }|Diagnosis\PlanLimitationReason
     */
    private function classifyType(
        Type $type,
        \Closure $hasPlan,
    ): array | Diagnosis\PlanLimitationReason {
        if ($type instanceof IntersectionType) {
            return Diagnosis\PlanLimitationReason::INTERSECTION_TYPE;
        }

        $isNullable = $type->isNullable();

        $t = $type;
        if ($t instanceof NullableType) {
            $t = $t->getWrappedType();
        }

        // A real union is tried member by member, not planned
        if ($t instanceof UnionType) {
            return Diagnosis\PlanLimitationReason::UNION_TYPE;
        }

        if ($t instanceof CollectionType) {
            return $this->classifyCollection($t, $hasPlan);
        }

        while ($t instanceof WrappingTypeInterface) {
            $t = $t->getWrappedType();
        }

        if ($t instanceof ObjectType) {
            return $this->classifyClass($t->getClassName(), $isNullable, $hasPlan);
        }

        // A builtin type is its identifier as string. Other builtin types (e.g. object, iterable) and other types (e.g. object shapes)
        // aren't planned.
        $kind = self::KINDS_OF_BUILTIN_TYPES[(string) $t] ?? null;

        return $kind !== null
            ? [$kind, null, null]
            : Diagnosis\PlanLimitationReason::UNSUPPORTED_TYPE;
    }

    /**
     * @param \Closure(class-string): bool $hasPlan
     *
     * @return list{
     *   0: int,
     *   1: ?class-string,
     *   2: ParameterExtra,
     * }|Diagnosis\PlanLimitationReason
     */
    private function classifyCollection(
        CollectionType $collectionType,
        \Closure $hasPlan,
    ): array | Diagnosis\PlanLimitationReason {
        // The collection itself must be an array (not e.g. a collection class with generics)
        $outerType = $collectionType;
        while ($outerType instanceof WrappingTypeInterface) {
            $outerType = $outerType->getWrappedType();
        }
        if (!$outerType instanceof BuiltinType
            || $outerType->getTypeIdentifier() !== TypeIdentifier::ARRAY
        ) {
            return Diagnosis\PlanLimitationReason::COLLECTION_IS_NOT_AN_ARRAY;
        }

        $keyType = $collectionType->getCollectionKeyType();
        $valueType = $collectionType->getCollectionValueType();

        // AbstractObjectNormalizer::validateAndDenormalize() treats a LogicException of the unwrapping as mixed. Here, it lets the plan of
        // the class fail, so the class is left to the PropertyNormalizer.
        $valueBaseType = $valueType;
        while ($valueBaseType instanceof WrappingTypeInterface) {
            $valueBaseType = $valueBaseType->getWrappedType();
        }

        // An element type that is a real union is denormalized element by element by symfony/serializer 8.2 (and kept as is by 8.1)
        if ($valueBaseType instanceof UnionType) {
            return self::classifyUnionElements($valueBaseType, $valueType->isNullable(), $keyType);
        }

        if (self::hasScalarElements($valueBaseType, $valueType)) {
            $scalarListSpec = self::scalarListSpec($valueType);

            return $scalarListSpec !== null
                ? [ParameterKind::SCALAR_LIST, null, $scalarListSpec]
                : Diagnosis\PlanLimitationReason::UNSUPPORTED_TYPE;
        }

        // Collection of objects, denormalized through the ArrayDenormalizer by the PropertyNormalizer as "X[]". A nested collection of
        // objects (an array of collections) as "X[][]", "X[][][]", ...: the same levels as AbstractObjectNormalizer::validateAndDenormalize()
        // (one CollectionType per level, one NullableType unwrapped per level), each with the key check the ArrayDenormalizer applies.
        $isNestedCollection = $valueBaseType instanceof BuiltinType
            && $valueBaseType->getTypeIdentifier() === TypeIdentifier::ARRAY;
        $keyCheckOfContext = self::keyCheck($keyType);
        $keyChecks = [$keyCheckOfContext];
        $isContextPassedOn = true;
        $innerType = $valueType;
        $levelType = $innerType instanceof NullableType
            ? $innerType->getWrappedType()
            : $innerType;
        while ($isNestedCollection
            && $levelType instanceof CollectionType
        ) {
            // The ArrayDenormalizer passes the key and value type of the next level on only while the value type is a CollectionType.
            // From a nullable inner level on, the value type is a NullableType and the context stays as it is: the keys of every further
            // level are checked against the key type of the level in front of the first nullable level. (A null inner level isn't
            // denormalizable as "X[]", which the plan leaves to the PropertyNormalizer like any value that isn't an array.) This mirrored
            // quirk is guarded by the NestedObjectCollections fixture of KnownConstructionNormalizerTest (e.g. list<?list<array<string, Leaf>>>), which
            // turns red when Symfony changes it.
            $isContextPassedOn = $isContextPassedOn
                && !$innerType instanceof NullableType;
            if ($isContextPassedOn) {
                $keyCheckOfContext = self::keyCheck($levelType->getCollectionKeyType());
            }
            $keyChecks[] = $keyCheckOfContext;

            $innerType = $levelType->getCollectionValueType();
            $levelType = $innerType instanceof NullableType
                ? $innerType->getWrappedType()
                : $innerType;
        }
        while ($levelType instanceof WrappingTypeInterface) {
            $levelType = $levelType->getWrappedType();
        }

        if ($levelType instanceof ObjectType) {
            return $this->classifyCollectionOfObjects($levelType->getClassName(), $collectionType, $keyChecks, $hasPlan);
        }

        // Any other element type isn't validated by the PropertyNormalizer, the value only has to be an array
        return [ParameterKind::ARRAY, null, null];
    }

    /**
     * Elements of a union type: a union of scalars is validated element by element (and the keys checked like symfony/serializer 8.2
     * does it), any other union falls back unless the collection is empty.
     *
     * @return list{
     *   0: int,
     *   1: ?class-string,
     *   2: ParameterExtra,
     * }
     *
     * @throws Exception\TypeCanNotBePlanned
     */
    private static function classifyUnionElements(
        UnionType $unionType,
        bool $isNullable,
        Type $keyType,
    ): array {
        $kinds = [];
        foreach ($unionType->getTypes() as $memberType) {
            $kind = self::KINDS_OF_SCALAR_TYPES[(string) $memberType] ?? null;
            if ($kind === null) {
                return [ParameterKind::UNION_LIST, null, null];
            }

            $kinds[] = $kind;
        }

        return [ParameterKind::SCALAR_UNION_LIST, null, [$kinds, $isNullable, self::keyCheck($keyType)]];
    }

    /**
     * A collection of objects with one level (X[]) or more (X[][], ...), which the PropertyNormalizer denormalizes as "X[]...[]" through
     * the ArrayDenormalizer with the key and value type of the outer collection as context. Value objects (one level only) and classes with
     * a plan are denormalized directly with the key checks of the ArrayDenormalizer per level, everything else through the serializer
     * like by the PropertyNormalizer.
     *
     * @param class-string                 $elementClass
     * @param non-empty-list<int>          $keyChecks    KeyCheck per level, outer level first (see classifyCollection())
     * @param \Closure(class-string): bool $hasPlan
     *
     * @return list{
     *   0: int,
     *   1: ?class-string,
     *   2: ParameterExtra,
     * }
     */
    private function classifyCollectionOfObjects(
        string $elementClass,
        CollectionType $outerCollectionType,
        array $keyChecks,
        \Closure $hasPlan,
    ): array {
        $valueObjectKind = self::valueObjectKind($elementClass);
        if ($valueObjectKind !== null
            && count($keyChecks) === 1
            && $this->isHandledInline($elementClass)
        ) {
            return [ParameterKind::VALUE_OBJECT_LIST, $elementClass, [$valueObjectKind, $keyChecks[0]]];
        }

        if ($valueObjectKind === null
            && $hasPlan($elementClass)
        ) {
            return [ParameterKind::NESTED_LIST, $elementClass, $keyChecks];
        }

        return [
            ParameterKind::OBJECT_LIST,
            $elementClass,
            [
                $outerCollectionType->getCollectionKeyType(),
                $outerCollectionType->getCollectionValueType(),
                str_repeat('[]', count($keyChecks)),
            ],
        ];
    }

    /**
     * @param class-string                 $class
     * @param \Closure(class-string): bool $hasPlan
     *
     * @return list{
     *   0: int,
     *   1: ?class-string,
     *   2: ParameterExtra,
     * }
     */
    private function classifyClass(
        string $class,
        bool $isNullable,
        \Closure $hasPlan,
    ): array {
        // A class that doesn't exist is denormalized through the serializer as well, which fails like for the PropertyNormalizer
        $valueObjectKind = self::valueObjectKind($class);
        if ($valueObjectKind !== null
            && $this->isHandledInline($class)
        ) {
            return [$valueObjectKind, $class, null];
        }

        if ($hasPlan($class)) {
            return [ParameterKind::NESTED, $class, null];
        }

        return [ParameterKind::OBJECT, $class, $isNullable];
    }

    /**
     * The kind of a value object of digital-craftsman/self-aware-normalizers, null for any other class. A class can only implement one of
     * the interfaces, as their normalize() methods have different return types.
     */
    public static function valueObjectKind(string $class): ?int
    {
        return match (true) {
            is_subclass_of($class, StringNormalizable::class) => ParameterKind::VALUE_OBJECT_STRING,
            is_subclass_of($class, IntNormalizable::class) => ParameterKind::VALUE_OBJECT_INT,
            is_subclass_of($class, FloatNormalizable::class) => ParameterKind::VALUE_OBJECT_FLOAT,
            is_subclass_of($class, BoolNormalizable::class) => ParameterKind::VALUE_OBJECT_BOOL,
            is_subclass_of($class, ArrayNormalizable::class) => ParameterKind::VALUE_OBJECT_ARRAY,
            default => null,
        };
    }

    /**
     * Key check of the ArrayDenormalizer::validateKeyType(), which calls is_<identifier>() for every key type: only int and string keys
     * can be planned.
     *
     * @throws Exception\TypeCanNotBePlanned
     */
    private static function keyCheck(Type $keyType): int
    {
        $typeIdentifiers = array_map(
            static fn (Type $type): ?string => $type instanceof BuiltinType
                ? $type->getTypeIdentifier()->value
                : null,
            $keyType instanceof UnionType
                ? $keyType->getTypes()
                : [$keyType],
        );
        sort($typeIdentifiers);

        // type-info only allows int and string keys, so the other key types are only a guard
        return self::KEY_CHECKS[implode('|', $typeIdentifiers)] ?? throw new Exception\TypeCanNotBePlanned();
    }

    /**
     * Copy of AbstractObjectNormalizer::hasScalarElements().
     */
    private static function hasScalarElements(
        Type $collectionValueBaseType,
        Type $collectionValueType,
    ): bool {
        if ($collectionValueBaseType instanceof BuiltinType
            && $collectionValueBaseType->getTypeIdentifier() === TypeIdentifier::ARRAY
        ) {
            while ($collectionValueType instanceof NullableType
                || $collectionValueType instanceof CollectionType
            ) {
                $collectionValueType = $collectionValueType instanceof NullableType
                    ? $collectionValueType->getWrappedType()
                    : $collectionValueType->getCollectionValueType();
            }

            $collectionValueBaseType = $collectionValueType;
        }

        return $collectionValueBaseType instanceof BuiltinType
            && in_array($collectionValueBaseType->getTypeIdentifier(), [TypeIdentifier::BOOL, TypeIdentifier::FLOAT, TypeIdentifier::INT, TypeIdentifier::STRING], true);
    }

    /**
     * Element spec of a collection of scalars: [kind of the element (scalar kind or SCALAR_LIST), whether null is allowed, spec of the
     * elements of a nested collection, whether the nested collection is a list].
     *
     * @return ScalarListSpec|null
     */
    private static function scalarListSpec(Type $elementType): ?array
    {
        $isNullable = $elementType->isNullable();

        $type = $elementType;
        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if ($type instanceof CollectionType) {
            $innerSpec = self::scalarListSpec($type->getCollectionValueType());

            return $innerSpec !== null
                ? [ParameterKind::SCALAR_LIST, $isNullable, $innerSpec, $type->isList()]
                : null;
        }

        // A builtin type is its identifier as string
        $kind = self::KINDS_OF_SCALAR_TYPES[(string) $type] ?? null;

        return $kind !== null
            ? [$kind, $isNullable, null, false]
            : null;
    }
}
