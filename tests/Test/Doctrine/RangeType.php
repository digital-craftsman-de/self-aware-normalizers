<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\Doctrine;

use BuronDigital\SelfAwareNormalizers\Doctrine\FloatNormalizableType;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\Range;

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
