<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

interface ArrayNormalizable
{
    public static function denormalize(array $data): self;

    public function normalize(): array;
}
