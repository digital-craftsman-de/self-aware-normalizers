<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface BoolNormalizable
{
    public static function denormalize(bool $data): self;

    public function normalize(): bool;
}
