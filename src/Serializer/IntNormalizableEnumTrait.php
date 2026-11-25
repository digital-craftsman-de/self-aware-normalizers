<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer;

trait IntNormalizableEnumTrait
{
    #[\Override]
    public static function denormalize(int $data): self
    {
        return self::from($data);
    }

    #[\Override]
    public function normalize(): int
    {
        return $this->value;
    }
}
