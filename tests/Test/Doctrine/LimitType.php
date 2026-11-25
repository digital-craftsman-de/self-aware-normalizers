<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\Doctrine;

use BuronDigital\SelfAwareNormalizers\Doctrine\IntNormalizableType;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\Limit;

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
