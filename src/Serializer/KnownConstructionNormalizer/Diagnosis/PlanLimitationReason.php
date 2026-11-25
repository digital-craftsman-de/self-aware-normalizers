<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis;

/**
 * Why a class gets no plan (or is only denormalized through its plan). Determined where it's decided, never re-implemented: by the
 * PlanFactory while it builds the plan, or by the KnownConstructionNormalizer for the scope and for recursion. Only the first reason of a class is
 * reported.
 */
enum PlanLimitationReason: string
{
    // -- Kinds of classes that never get a plan by design
    case INTERFACE = 'INTERFACE';
    case ENUM = 'ENUM';
    case ABSTRACT_CLASS = 'ABSTRACT_CLASS';
    /**
     * Takes precedence over ENUM and ABSTRACT_CLASS: an enum or abstract class implementing an interface of the self-aware normalizers
     * is (de)normalized by them, so that's what is reported for it. Such classes never reach the KnownConstructionNormalizer.
     */
    case SELF_AWARE_VALUE_OBJECT = 'SELF_AWARE_VALUE_OBJECT';
    case INTERNAL_CLASS = 'INTERNAL_CLASS';
    case EXCEPTION = 'EXCEPTION';
    case ANONYMOUS_CLASS = 'ANONYMOUS_CLASS';
    /**
     * A type that isn't an existing class, enum or interface (e.g. "int" or "X[]").
     */
    case NOT_A_CLASS = 'NOT_A_CLASS';

    // -- Shape of the class
    case CONSTRUCTOR_IS_NOT_PUBLIC = 'CONSTRUCTOR_IS_NOT_PUBLIC';
    case NO_CONSTRUCTOR = 'NO_CONSTRUCTOR';
    case PROPERTY_IS_NOT_PUBLIC = 'PROPERTY_IS_NOT_PUBLIC';
    case PROPERTY_IS_NOT_PROMOTED = 'PROPERTY_IS_NOT_PROMOTED';
    case PROPERTY_HAS_HOOKS = 'PROPERTY_HAS_HOOKS';
    case PROPERTY_IS_INHERITED = 'PROPERTY_IS_INHERITED';
    case PARAMETER_IS_VARIADIC = 'PARAMETER_IS_VARIADIC';
    case PARAMETER_IS_PASSED_BY_REFERENCE = 'PARAMETER_IS_PASSED_BY_REFERENCE';
    case PARAMETER_IS_NOT_PROMOTED = 'PARAMETER_IS_NOT_PROMOTED';
    case PARAMETER_HAS_NO_TYPE = 'PARAMETER_HAS_NO_TYPE';
    case PARAMETER_HAS_OBJECT_DEFAULT_VALUE = 'PARAMETER_HAS_OBJECT_DEFAULT_VALUE';
    case PARAMETERS_DIFFER_FROM_PROPERTIES = 'PARAMETERS_DIFFER_FROM_PROPERTIES';

    // -- Type of a parameter
    case TYPE_IS_NOT_RESOLVABLE = 'TYPE_IS_NOT_RESOLVABLE';
    case INTERSECTION_TYPE = 'INTERSECTION_TYPE';
    case COLLECTION_IS_NOT_AN_ARRAY = 'COLLECTION_IS_NOT_AN_ARRAY';
    case UNSUPPORTED_TYPE = 'UNSUPPORTED_TYPE';

    // -- Decided by the KnownConstructionNormalizer
    case RECURSIVE_CLASS = 'RECURSIVE_CLASS';

    // -- Decided by the normalizers of the serializer
    /**
     * A normalizer with a higher priority than the KnownConstructionNormalizer claims the class (see NormalizerChain), so the serializer
     * doesn't select the KnownConstructionNormalizer for it, and the plans of other classes don't handle it inline.
     */
    case CLAIMED_BY_OTHER_NORMALIZER = 'CLAIMED_BY_OTHER_NORMALIZER';

    // -- Normalization only (the class is planned for the denormalization)
    case PROPERTY_IS_MUTABLE = 'PROPERTY_IS_MUTABLE';

    /**
     * Whether classes with this reason never get a plan by design, as they aren't plain read models (or are handled by another
     * normalizer). The profiler only lists the other ones as missing the fast path.
     */
    public function isByDesign(): bool
    {
        return match ($this) {
            self::INTERFACE,
            self::ENUM,
            self::ABSTRACT_CLASS,
            self::SELF_AWARE_VALUE_OBJECT,
            self::INTERNAL_CLASS,
            self::EXCEPTION,
            self::ANONYMOUS_CLASS,
            self::NOT_A_CLASS,
            self::CLAIMED_BY_OTHER_NORMALIZER => true,
            default => false,
        };
    }

    /**
     * How to put a class with this reason on the fast path, null for the reasons by design.
     */
    public function fixHint(): ?string
    {
        return match ($this) {
            self::CONSTRUCTOR_IS_NOT_PUBLIC => 'Make the constructor public.',
            self::NO_CONSTRUCTOR,
            self::PROPERTY_IS_NOT_PROMOTED,
            self::PARAMETER_IS_NOT_PROMOTED,
            self::PARAMETERS_DIFFER_FROM_PROPERTIES => 'Promote every property in the constructor, with parameters exactly like the properties.',
            self::PROPERTY_IS_NOT_PUBLIC => 'Make the property public (readonly).',
            self::PROPERTY_HAS_HOOKS => 'Remove the hooks of the property.',
            self::PROPERTY_IS_INHERITED => 'Declare the property in the class itself instead of a parent class.',
            self::PARAMETER_IS_VARIADIC => 'Use an array instead of a variadic parameter.',
            self::PARAMETER_IS_PASSED_BY_REFERENCE => 'Pass the parameter by value.',
            self::PARAMETER_HAS_NO_TYPE => 'Add a type to the parameter.',
            self::PARAMETER_HAS_OBJECT_DEFAULT_VALUE => 'Use null as default value and create the object in the caller.',
            self::TYPE_IS_NOT_RESOLVABLE,
            self::UNSUPPORTED_TYPE => 'Use a type the plan can express: scalars, arrays, lists of them, value objects and other plannable classes.',
            self::INTERSECTION_TYPE => 'Use a single (nullable) type or a union.',
            self::COLLECTION_IS_NOT_AN_ARRAY => 'Use an array (e.g. list<X>) instead of an iterable or a collection class.',
            self::RECURSIVE_CLASS => 'Break the recursion, e.g. by referencing the nested items by id.',
            self::PROPERTY_IS_MUTABLE => 'Make every property readonly (the class is only denormalized through its plan).',
            default => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::INTERFACE => 'interface',
            self::ENUM => 'enum',
            self::ABSTRACT_CLASS => 'abstract class',
            self::SELF_AWARE_VALUE_OBJECT => 'self-aware value object',
            self::INTERNAL_CLASS => 'internal class',
            self::EXCEPTION => 'exception',
            self::ANONYMOUS_CLASS => 'anonymous class',
            self::NOT_A_CLASS => 'not a class',
            self::CONSTRUCTOR_IS_NOT_PUBLIC => 'constructor is not public',
            self::NO_CONSTRUCTOR => 'no constructor',
            self::PROPERTY_IS_NOT_PUBLIC => 'property is not public',
            self::PROPERTY_IS_NOT_PROMOTED => 'property is not promoted',
            self::PROPERTY_HAS_HOOKS => 'property has hooks',
            self::PROPERTY_IS_INHERITED => 'property is inherited',
            self::PARAMETER_IS_VARIADIC => 'parameter is variadic',
            self::PARAMETER_IS_PASSED_BY_REFERENCE => 'parameter is passed by reference',
            self::PARAMETER_IS_NOT_PROMOTED => 'parameter is not promoted',
            self::PARAMETER_HAS_NO_TYPE => 'parameter has no type',
            self::PARAMETER_HAS_OBJECT_DEFAULT_VALUE => 'parameter has an object as default value',
            self::PARAMETERS_DIFFER_FROM_PROPERTIES => 'parameters differ from properties',
            self::TYPE_IS_NOT_RESOLVABLE => 'type is not resolvable',
            self::INTERSECTION_TYPE => 'intersection type',
            self::COLLECTION_IS_NOT_AN_ARRAY => 'collection is not an array',
            self::UNSUPPORTED_TYPE => 'unsupported type',
            self::RECURSIVE_CLASS => 'references itself (directly or through other classes)',
            self::CLAIMED_BY_OTHER_NORMALIZER => 'claimed by a normalizer with a higher priority',
            self::PROPERTY_IS_MUTABLE => 'property is mutable',
        };
    }
}
