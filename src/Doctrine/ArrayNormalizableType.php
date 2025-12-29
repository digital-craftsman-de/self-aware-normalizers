<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class ArrayNormalizableType extends Type
{
    abstract public static function getTypeName(): string;

    /**
     * @return class-string<ArrayNormalizable>
     */
    abstract public static function getClass(): string;

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['jsonb'] = true;

        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * @param ?string $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?ArrayNormalizable
    {
        if ($value === null) {
            return null;
        }

        /** @var class-string<ArrayNormalizable> $class */
        $class = static::getClass();

        $array = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return $class::denormalize($array);
    }

    /**
     * @param ?ArrayNormalizable $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        $array = $value->normalize();

        return json_encode($array, JSON_THROW_ON_ERROR);
    }
}
