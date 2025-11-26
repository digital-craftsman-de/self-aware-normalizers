<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

trait NullableFloatDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?float $data): ?self
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
