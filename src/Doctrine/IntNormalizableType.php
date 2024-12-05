<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class IntNormalizableType extends Type
{
    abstract public static function getTypeName(): string;

    /**
     * @return class-string<IntNormalizable>
     */
    abstract public static function getClass(): string;

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    /**
     * @param ?int $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?IntNormalizable
    {
        if ($value === null) {
            return null;
        }

        /** @var class-string<IntNormalizable> $class */
        $class = static::getClass();

        return $class::denormalize($value);
    }

    /**
     * @param IntNormalizable|int|null $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        return $value->normalize();
    }

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function getName(): string
    {
        return static::getTypeName();
    }

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
