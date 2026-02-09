<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

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
