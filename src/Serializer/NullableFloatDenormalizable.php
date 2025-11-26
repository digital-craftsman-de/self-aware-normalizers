<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface NullableFloatDenormalizable
{
    public static function denormalizeWhenNotNull(?float $data): ?self;
}
