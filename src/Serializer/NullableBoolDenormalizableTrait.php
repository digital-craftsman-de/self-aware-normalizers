<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

trait NullableBoolDenormalizableTrait
{
    public static function denormalizeWhenNotNull(?bool $data): ?static
    {
        return $data !== null
            ? self::denormalize($data)
            : null;
    }
}
