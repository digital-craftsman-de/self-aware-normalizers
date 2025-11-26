<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface NullableStringDenormalizable
{
    public static function denormalizeWhenNotNull(?string $data): ?static;
}
