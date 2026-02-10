<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\StringNormalizableTypeAsTypeText;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;

final readonly class Description implements StringNormalizable, StringNormalizableTypeAsTypeText
{
    public function __construct(
        public string $value,
    ) {
        if ($this->value === '') {
            throw new \InvalidArgumentException('The description must not be empty');
        }
    }

    // -- String normalizable

    #[\Override]
    public static function denormalize(string $data): self
    {
        return new self($data);
    }

    #[\Override]
    public function normalize(): string
    {
        return $this->value;
    }
}
