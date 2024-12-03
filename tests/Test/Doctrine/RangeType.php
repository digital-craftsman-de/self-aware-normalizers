<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\FloatNormalizableType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Range;

final class RangeType extends FloatNormalizableType
{
    #[\Override]
    public static function getTypeName(): string
    {
        return 'range';
    }

    #[\Override]
    public static function getClass(): string
    {
        return Range::class;
    }
}
