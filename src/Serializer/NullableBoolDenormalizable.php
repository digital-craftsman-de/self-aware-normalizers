<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

interface NullableBoolDenormalizable
{
    public static function denormalizeWhenNotNull(?bool $data): ?static;
}
