<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\BoolNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class BoolNormalizableType extends Type
{
    abstract public static function getTypeName(): string;

    /**
     * @return class-string<BoolNormalizable>
     */
    abstract public static function getClass(): string;

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBooleanTypeDeclarationSQL($column);
    }

    /**
     * @param ?bool $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?BoolNormalizable
    {
        if ($value === null) {
            return null;
        }

        /** @var class-string<BoolNormalizable> $class */
        $class = static::getClass();

        return $class::denormalize($value);
    }

    /**
     * @param BoolNormalizable|bool|null $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return $value->normalize();
    }
}
