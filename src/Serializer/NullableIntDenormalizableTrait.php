<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

trait NullableIntDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?int $data): ?static
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
