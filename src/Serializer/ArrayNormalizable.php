<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

interface ArrayNormalizable
{
    public static function denormalize(array $data): self;

    public function normalize(): array;
}
