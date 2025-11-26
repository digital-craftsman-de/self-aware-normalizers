<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface NullableIntDenormalizable
{
    public static function denormalizeWhenNotNull(?int $data): ?self;
}
