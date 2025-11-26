<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

trait NullableArrayDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?array $data): ?static
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
