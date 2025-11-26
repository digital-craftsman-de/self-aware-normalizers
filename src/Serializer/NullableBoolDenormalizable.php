<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface NullableBoolDenormalizable
{
    public static function denormalizeWhenNotNull(?bool $data): ?self;
}
