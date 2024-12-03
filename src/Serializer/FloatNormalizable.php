<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface FloatNormalizable
{
    public static function denormalize(float $data): self;

    public function normalize(): float;
}
