<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Exception\FallbackIsRequired;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Exception\TypeCanNotBePlanned;
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
                'UNION_TYPE' => 'union type',
                'INTERSECTION_TYPE' => 'intersection type',
                'COLLECTION_IS_NOT_AN_ARRAY' => 'collection is not an array',
                'UNSUPPORTED_TYPE' => 'unsupported type',
                'RECURSIVE_CLASS' => 'references itself (directly or through other classes)',
                'CLAIMED_BY_OTHER_NORMALIZER' => 'claimed by a normalizer with a higher priority',
                'PROPERTY_IS_MUTABLE' => 'property is mutable',
            ],
            $descriptions,
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
