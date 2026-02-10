<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\NormalizableTypeWithSQLDeclaration;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final readonly class HighLimit implements IntNormalizable, NormalizableTypeWithSQLDeclaration
{
    public function __construct(
        public int $limit,
    ) {
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

    #[\Override]
    public static function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string {
        return 'INT(5)';
    }
}
