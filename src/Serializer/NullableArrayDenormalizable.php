<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface NullableArrayDenormalizable
{
    public static function denormalizeWhenNotNull(?array $data): ?static;
}
