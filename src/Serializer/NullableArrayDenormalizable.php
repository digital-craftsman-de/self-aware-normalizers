<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

interface NullableArrayDenormalizable
{
    public static function denormalizeWhenNotNull(?array $data): ?static;
}
