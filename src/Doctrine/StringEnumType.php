<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class StringEnumType extends Type
{
    abstract public static function getTypeName(): string;

    abstract public static function getClass(): string;

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * @param ?string $value
     *
     * @psalm-suppress UndefinedClass
     * @psalm-suppress UndefinedDocblockClass
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?object
    {
        if ($value === null) {
            return null;
        }

        /** @var class-string $class */
        $class = static::getClass();

        // Unfortunately, it's not possible to type something that tells this line that it's a valid enum call
        return $class::from($value);
    }

    /**
     * Would be a string if used as a parameter in a query.
     *
     * @param object|string|null $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return $value->value;
    }
}
