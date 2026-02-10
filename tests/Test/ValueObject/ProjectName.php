<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\StringNormalizableTypeWithMaxLength;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;

final readonly class ProjectName implements StringNormalizable, StringNormalizableTypeWithMaxLength
{
    public function __construct(
        public string $value,
    ) {
        if (mb_strlen($this->value) > 50) {
            throw new \InvalidArgumentException('Too long');
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

    #[\Override]
    public static function maxLength(): int
    {
        return 50;
    }
}
