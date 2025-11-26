<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\NullableFloatDenormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\NullableFloatDenormalizableTrait;

final readonly class Range implements FloatNormalizable, NullableFloatDenormalizable
{
    use NullableFloatDenormalizableTrait;

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
