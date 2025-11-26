<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

trait NullableStringDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?string $data): ?static
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
