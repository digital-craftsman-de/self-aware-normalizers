<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

trait NullableIntDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?int $data): ?self
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
