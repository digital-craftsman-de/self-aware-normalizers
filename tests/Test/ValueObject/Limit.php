<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;

final readonly class Limit implements IntNormalizable
{
    public function __construct(
        public int $limit,
    ) {
        if ($this->limit < 0) {
            throw new \InvalidArgumentException('The limit can not be negative');
        }
        if ($this->limit > 1_000) {
            throw new \InvalidArgumentException('The limit can not be greater than 1000');
        }
    }

    // -- Int normalizable

    #[\Override]
    public static function denormalize(int $data): self
    {
        return new self($data);
    }

    #[\Override]
    public function normalize(): int
    {
        return $this->limit;
    }
}
