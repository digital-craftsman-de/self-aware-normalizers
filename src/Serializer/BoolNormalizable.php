<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

interface BoolNormalizable
{
    public static function denormalize(bool $data): self;

    public function normalize(): bool;
}
