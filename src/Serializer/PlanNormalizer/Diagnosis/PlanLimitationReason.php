<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\Diagnosis;

/**
 * Why a class gets no plan (or is only denormalized through its plan). Determined where it's decided, never re-implemented: by the
 * PlanFactory while it builds the plan, or by the PlanNormalizer for the scope and for recursion. Only the first reason of a class is
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
     * is (de)normalized by them, so that's what is reported for it. self-aware-normalizers:plan-normalizer:list-unplanned leaves these
     * classes out completely, as they never reach the PlanNormalizer.
     */
    case SELF_AWARE_VALUE_OBJECT = 'SELF_AWARE_VALUE_OBJECT';
    case INTERNAL_CLASS = 'INTERNAL_CLASS';

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
    case UNION_TYPE = 'UNION_TYPE';
    case INTERSECTION_TYPE = 'INTERSECTION_TYPE';
    case COLLECTION_IS_NOT_AN_ARRAY = 'COLLECTION_IS_NOT_AN_ARRAY';
    case UNSUPPORTED_TYPE = 'UNSUPPORTED_TYPE';

    // -- Decided by the PlanNormalizer
    case NOT_IN_SCOPE = 'NOT_IN_SCOPE';
    case RECURSIVE_CLASS = 'RECURSIVE_CLASS';

    // -- Normalization only (the class is planned for the denormalization)
    case PROPERTY_IS_MUTABLE = 'PROPERTY_IS_MUTABLE';

    /**
     * Whether the class could be put on the fast path by changing its shape. Interfaces, enums, abstract classes, self-aware value objects
     * and internal classes never reach the PropertyNormalizer as such, so they aren't relevant.
     */
    public function isRelevant(): bool
    {
        return match ($this) {
            self::NOT_IN_SCOPE,
            self::INTERFACE,
            self::ENUM,
            self::ABSTRACT_CLASS,
            self::SELF_AWARE_VALUE_OBJECT,
            self::INTERNAL_CLASS => false,
            default => true,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NOT_IN_SCOPE => 'not in the scope of the plan normalizer',
            self::INTERFACE => 'interface',
            self::ENUM => 'enum',
            self::ABSTRACT_CLASS => 'abstract class',
            self::SELF_AWARE_VALUE_OBJECT => 'self-aware value object',
            self::INTERNAL_CLASS => 'internal class',
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
            self::UNION_TYPE => 'union type',
            self::INTERSECTION_TYPE => 'intersection type',
            self::COLLECTION_IS_NOT_AN_ARRAY => 'collection is not an array',
            self::UNSUPPORTED_TYPE => 'unsupported type',
            self::RECURSIVE_CLASS => 'references itself (directly or through other classes)',
            self::PROPERTY_IS_MUTABLE => 'property is mutable',
        };
    }
}
