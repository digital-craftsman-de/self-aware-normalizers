<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer;

/**
 * How the known construction normalizer denormalizes a constructor parameter. Plain integers (not an enum), as they are compared in the hot loop of
 * every denormalized object and are stored in cached plans.
 *
 * Every parameter plan (see the psalm type ParameterPlan of PlanFactory) has a target class and an extra slot, which depend on the kind
 * as documented per constant (the psalm type ParameterExtra lists all variants).
 *
 * @internal
 */
final readonly class ParameterKind
{
    /**
     * Scalars, accepted when the value already has the type (an int for a float only with a JSON format, like the PropertyNormalizer).
     * Target class: null. Extra: null.
     */
    public const int STRING = 1;
    public const int INT = 2;
    public const int FLOAT = 3;
    public const int BOOL = 4;

    /**
     * Accepted as is. Target class: null. Extra: null.
     */
    public const int MIXED = 5;

    /**
     * Collection without validated elements (e.g. array<string, mixed>, list<int|string>), accepted when it's an array. Target class:
     * null. Extra: null.
     */
    public const int ARRAY = 6;

    /**
     * Collection of scalars (or nested collections of scalars), every element is validated. Target class: null. Extra: the
     * ScalarListSpec of the elements (kind, whether null is allowed, spec of the elements of a nested collection).
     */
    public const int SCALAR_LIST = 7;

    /**
     * Value object of digital-craftsman/self-aware-normalizers, built through its static denormalize(). Target class: the value object.
     * Extra: null.
     */
    public const int VALUE_OBJECT_STRING = 8;
    public const int VALUE_OBJECT_INT = 9;
    public const int VALUE_OBJECT_FLOAT = 10;
    public const int VALUE_OBJECT_BOOL = 11;
    public const int VALUE_OBJECT_ARRAY = 12;

    /**
     * Another class with a plan, built directly. Target class: that class. Extra: null.
     */
    public const int NESTED = 13;

    /**
     * Any other class, denormalized through the serializer. Target class: that class. Extra: whether the type is nullable (bool).
     */
    public const int OBJECT = 14;

    /**
     * Collection of value objects (one level, X[]). Target class: the value object. Extra: list{value object kind, KeyCheck}.
     */
    public const int VALUE_OBJECT_LIST = 15;

    /**
     * Collection of another class with a plan, also nested (X[], X[][], ...: list<X>, list<list<X>>, array<string, list<?list<X>>>,
     * ...). Target class: that class. Extra: the KeyCheck the ArrayDenormalizer applies per level, outer level first (non-empty list of
     * int, one entry per level; from a nullable inner level on, every level is checked against the key type of the level in front of the
     * first nullable level, see PlanFactory::classifyCollection()).
     */
    public const int NESTED_LIST = 16;

    /**
     * Collection of any other class (also nested, and a nested collection of value objects), denormalized through the serializer as
     * "Class[]", "Class[][]", .... Target class: that class. Extra: list{key type, value type, type suffix} of the outer collection
     * (Type, Type, "[]" per level), the types passed as context like by the PropertyNormalizer.
     */
    public const int OBJECT_LIST = 17;
}
