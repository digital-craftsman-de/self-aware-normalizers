<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;

final readonly class Opacity implements FloatNormalizable
{
    public function __construct(
        public float $opacity,
    ) {
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
        return $this->opacity;
    }
}
