<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

trait StringNormalizableEnumTrait
{
    #[\Override]
    public static function denormalize(string $data): self
    {
        return self::from($data);
    }

    #[\Override]
    public function normalize(): string
    {
        return $this->value;
    }
}
