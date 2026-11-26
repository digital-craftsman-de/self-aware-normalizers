<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis;

use BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Exception\FallbackIsRequired;
use BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Exception\TypeCanNotBePlanned;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Some reasons can't be reached through the list command (e.g. interfaces and internal classes are never in the scope), so every reason
 * is pinned here.
 */
#[CoversClass(PlanLimitationReason::class)]
#[CoversClass(FallbackIsRequired::class)]
#[CoversClass(TypeCanNotBePlanned::class)]
final class PlanLimitationReasonTest extends TestCase
{
    #[Test]
    public function every_reason_has_a_description(): void
    {
        // -- Act
        $descriptions = [];
        foreach (PlanLimitationReason::cases() as $reason) {
            $descriptions[$reason->value] = $reason->description();
        }

        // -- Assert
        self::assertSame(
            [
                'INTERFACE' => 'interface',
                'ENUM' => 'enum',
                'ABSTRACT_CLASS' => 'abstract class',
                'SELF_AWARE_VALUE_OBJECT' => 'self-aware value object',
                'INTERNAL_CLASS' => 'internal class',
                'EXCEPTION' => 'exception',
                'ANONYMOUS_CLASS' => 'anonymous class',
                'NOT_A_CLASS' => 'not a class',
                'NO_PROPERTIES' => 'no properties (not supported by the PropertyNormalizer)',
                'CONSTRUCTOR_IS_NOT_PUBLIC' => 'constructor is not public',
                'NO_CONSTRUCTOR' => 'no constructor',
                'PROPERTY_IS_NOT_PUBLIC' => 'property is not public',
                'PROPERTY_IS_NOT_PROMOTED' => 'property is not promoted',
                'PROPERTY_HAS_HOOKS' => 'property has hooks',
                'PROPERTY_IS_INHERITED' => 'property is inherited',
                'PARAMETER_IS_VARIADIC' => 'parameter is variadic',
                'PARAMETER_IS_PASSED_BY_REFERENCE' => 'parameter is passed by reference',
                'PARAMETER_IS_NOT_PROMOTED' => 'parameter is not promoted',
                'PARAMETER_HAS_NO_TYPE' => 'parameter has no type',
                'PARAMETER_HAS_OBJECT_DEFAULT_VALUE' => 'parameter has an object as default value',
                'PARAMETERS_DIFFER_FROM_PROPERTIES' => 'parameters differ from properties',
                'TYPE_IS_NOT_RESOLVABLE' => 'type is not resolvable',
                'INTERSECTION_TYPE' => 'intersection type',
                'COLLECTION_IS_NOT_AN_ARRAY' => 'collection is not an array',
                'UNSUPPORTED_TYPE' => 'unsupported type',
                'RECURSIVE_CLASS' => 'references itself (directly or through other classes)',
                'CLAIMED_BY_OTHER_NORMALIZER' => 'claimed by a normalizer with a higher priority',
                'PROPERTY_IS_MUTABLE' => 'property is mutable',
                'TRAVERSABLE' => 'traversable (normalized element by element by the serializer)',
            ],
            $descriptions,
        );
    }

    /**
     * The profiler lists the classes without plan by design apart from the ones that could get a plan, and only the latter get a hint.
     */
    #[Test]
    public function reasons_by_design_have_no_hint_and_every_other_reason_has_one(): void
    {
        // -- Act
        $reasonsByDesign = [];
        $hints = [];
        foreach (PlanLimitationReason::cases() as $reason) {
            if ($reason->isByDesign()) {
                $reasonsByDesign[] = $reason->value;
            }
            $hints[$reason->value] = $reason->fixHint();
        }

        // -- Assert
        self::assertSame(
            [
                'INTERFACE',
                'ENUM',
                'ABSTRACT_CLASS',
                'SELF_AWARE_VALUE_OBJECT',
                'INTERNAL_CLASS',
                'EXCEPTION',
                'ANONYMOUS_CLASS',
                'NOT_A_CLASS',
                'NO_PROPERTIES',
                'CLAIMED_BY_OTHER_NORMALIZER',
                'TRAVERSABLE',
            ],
            $reasonsByDesign,
        );
        self::assertSame(
            [
                'INTERFACE' => null,
                'ENUM' => null,
                'ABSTRACT_CLASS' => null,
                'SELF_AWARE_VALUE_OBJECT' => null,
                'INTERNAL_CLASS' => null,
                'EXCEPTION' => null,
                'ANONYMOUS_CLASS' => null,
                'NOT_A_CLASS' => null,
                'NO_PROPERTIES' => null,
                'CONSTRUCTOR_IS_NOT_PUBLIC' => 'Make the constructor public.',
                'NO_CONSTRUCTOR' => 'Promote every property in the constructor, with parameters exactly like the properties.',
                'PROPERTY_IS_NOT_PUBLIC' => 'Make the property public (readonly).',
                'PROPERTY_IS_NOT_PROMOTED' => 'Promote every property in the constructor, with parameters exactly like the properties.',
                'PROPERTY_HAS_HOOKS' => 'Remove the hooks of the property.',
                'PROPERTY_IS_INHERITED' => 'Declare the property in the class itself instead of a parent class.',
                'PARAMETER_IS_VARIADIC' => 'Use an array instead of a variadic parameter.',
                'PARAMETER_IS_PASSED_BY_REFERENCE' => 'Pass the parameter by value.',
                'PARAMETER_IS_NOT_PROMOTED' => 'Promote every property in the constructor, with parameters exactly like the properties.',
                'PARAMETER_HAS_NO_TYPE' => 'Add a type to the parameter.',
                'PARAMETER_HAS_OBJECT_DEFAULT_VALUE' => 'Use null as default value and create the object in the caller.',
                'PARAMETERS_DIFFER_FROM_PROPERTIES' => 'Promote every property in the constructor, with parameters exactly like the properties.',
                'TYPE_IS_NOT_RESOLVABLE' => 'Use a type the plan can express: scalars, arrays, lists of them, value objects and other plannable classes.',
                'INTERSECTION_TYPE' => 'Use a single (nullable) type or a union.',
                'COLLECTION_IS_NOT_AN_ARRAY' => 'Use an array (e.g. list<X>) instead of an iterable or a collection class.',
                'UNSUPPORTED_TYPE' => 'Use a type the plan can express: scalars, arrays, lists of them, value objects and other plannable classes.',
                'RECURSIVE_CLASS' => 'Break the recursion, e.g. by referencing the nested items by id.',
                'CLAIMED_BY_OTHER_NORMALIZER' => null,
                'PROPERTY_IS_MUTABLE' => 'Make every property readonly (the class is only denormalized through its plan).',
                'TRAVERSABLE' => null,
            ],
            $hints,
        );
    }

    #[Test]
    public function internal_signals_have_a_message(): void
    {
        // -- Assert
        self::assertSame('The data must be denormalized by the fallback normalizer.', FallbackIsRequired::missingValue()->getMessage());
        self::assertSame('The type can not be expressed by a plan.', new TypeCanNotBePlanned()->getMessage());
    }

    #[Test]
    public function fallbacks_describe_why_and_where(): void
    {
        // -- Act & Assert
        self::assertSame('missing key', FallbackIsRequired::missingValue()->describe());
        self::assertSame('Example::$name: null for a non-nullable parameter', FallbackIsRequired::nullForNonNullableParameter()->at('Example', 'name')->describe());
        // The innermost location is kept
        self::assertSame('Inner::$count: int expected, string given', FallbackIsRequired::unexpectedValue('int', '1')->at('Inner', 'count')->at('Outer', 'inner')->describe());
        self::assertSame('Example: the constructor failed (TypeError: Invalid.)', FallbackIsRequired::constructorFailed(new \TypeError('Invalid.'))->at('Example', null)->describe());
        self::assertSame('key int of the wrong type', FallbackIsRequired::invalidKey(7)->describe());
        self::assertSame('the value object can not be denormalized from the value', FallbackIsRequired::valueObjectNotDenormalizable()->describe());
        self::assertSame('the serializer failed to denormalize Example (LogicException: Invalid.)', FallbackIsRequired::serializerFailed('Example', new \LogicException('Invalid.'))->describe());
        self::assertSame('no normalizer of the serializer supports Example', FallbackIsRequired::serializerFailed('Example', null)->describe());
        self::assertSame('Example has no plan', FallbackIsRequired::noPlan('Example')->describe());
    }
}
