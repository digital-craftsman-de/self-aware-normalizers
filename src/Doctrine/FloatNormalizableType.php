<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class FloatNormalizableType extends Type
{
    abstract public static function getTypeName(): string;

    /**
     * @return class-string<FloatNormalizable>
     */
    abstract public static function getClass(): string;

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getFloatDeclarationSQL($column);
    }

    /**
     * @param float|string|null $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?FloatNormalizable
    {
        if ($value === null) {
            return null;
        }

        /**
         * @var class-string<FloatNormalizable> $class
         */
        $class = static::getClass();

        return $class::denormalize((float) $value);
    }

    /**
     * @param FloatNormalizable|float|null $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        return $value->normalize();
    }
}
