<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

interface NullableStringDenormalizable
{
    public static function denormalizeWhenNotNull(?string $data): ?static;
}
