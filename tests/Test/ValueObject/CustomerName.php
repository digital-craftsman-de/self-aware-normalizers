<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\ValueObject;

use BuronDigital\SelfAwareNormalizers\Doctrine\NormalizableTypeWithSQLDeclaration;
use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final readonly class CustomerName implements StringNormalizable, NormalizableTypeWithSQLDeclaration
{
    public function __construct(
        public string $value,
    ) {
        if (mb_strlen($this->value) > 70) {
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
    public static function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string {
        return 'VARCHAR(70)';
    }
}
