<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\IntNormalizableType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;

final class LimitType extends IntNormalizableType
{
    #[\Override]
    public static function getTypeName(): string
    {
        return 'limit';
    }

    #[\Override]
    public static function getClass(): string
    {
        return Limit::class;
    }
}
