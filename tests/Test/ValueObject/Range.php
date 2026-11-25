<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\ValueObject;

use BuronDigital\SelfAwareNormalizers\Serializer\FloatNormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\NullableFloatDenormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\NullableFloatDenormalizableTrait;

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
