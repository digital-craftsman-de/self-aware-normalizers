<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

trait NullableIntDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?int $data): ?static
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
