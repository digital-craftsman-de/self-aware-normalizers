<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

interface NullableIntDenormalizable
{
    public static function denormalizeWhenNotNull(?int $data): ?static;
}
