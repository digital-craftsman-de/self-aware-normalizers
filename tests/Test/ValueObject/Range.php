<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;

final readonly class Range implements FloatNormalizable
{
    public function __construct(
        public float $range,
    ) {
        if ($this->range < 0) {
            throw new \InvalidArgumentException('The range can not be negative');
        }
        if ($this->range > 1) {
            throw new \InvalidArgumentException('The limit can not be greater than 1');
        }
    }

    // -- Float normalizable

    #[\Override]
    public static function denormalize(float $data): self
    {
        return new self($data);
    }

    #[\Override]
    public function normalize(): float
    {
        return $this->range;
    }
}
