<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

trait NullableArrayDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?array $data): ?static
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
