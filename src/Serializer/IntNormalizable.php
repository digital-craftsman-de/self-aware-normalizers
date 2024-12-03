<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface IntNormalizable
{
    public static function denormalize(int $data): self;

    public function normalize(): int;
}
