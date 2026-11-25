<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

interface NullableFloatDenormalizable
{
    public static function denormalizeWhenNotNull(?float $data): ?static;
}
