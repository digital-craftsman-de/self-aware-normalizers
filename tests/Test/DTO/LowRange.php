<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\NormalizableTypeWithSQLDeclaration;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final readonly class LowRange implements FloatNormalizable, NormalizableTypeWithSQLDeclaration
{
    public function __construct(
        public float $range,
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
        return $this->range;
    }

    #[\Override]
    public static function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string {
        return 'NUMERIC(3)';
    }
}
