<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer;

/**
 * The keys of a collection of objects the plan accepts, like ArrayDenormalizer::validateKeyType() (int, string, or both).
 *
 * @internal
 */
final readonly class KeyCheck
{
    public const int UNCHECKED = 0;
    public const int INT = 1;
    public const int STRING = 2;
}
