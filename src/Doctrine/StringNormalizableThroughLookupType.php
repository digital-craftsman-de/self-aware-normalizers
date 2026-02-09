<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class StringNormalizableThroughLookupType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $className = $this->getClassNameThroughLookup();
        $reflectionClass = new \ReflectionClass($className);

        if ($reflectionClass->implementsInterface(NormalizableTypeWithSQLDeclaration::class)) {
            /**
             * @var NormalizableTypeWithSQLDeclaration $className
             */
            return $className::getSQLDeclaration($column, $platform);
        }

        if ($reflectionClass->implementsInterface(StringNormalizableTypeAsTypeText::class)) {
            return $platform->getClobTypeDeclarationSQL($column);
        }

        if ($reflectionClass->implementsInterface(StringNormalizableTypeWithMaxLength::class)) {
            /**
             * @var StringNormalizableTypeWithMaxLength $className
             */
            $column['length'] ??= $className::maxLength();
        }

        $column['length'] ??= 255;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * @param ?string $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?StringNormalizable
    {
        if ($value === null) {
            return null;
        }

        $className = $this->getClassNameThroughLookup();

        return $className::denormalize($value);
    }

    /**
     * @param StringNormalizable|string|null $value
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

        return $value->normalize();
    }

    /**
     * @return class-string<StringNormalizable>
     */
    private function getClassNameThroughLookup(): string
    {
        /**
         * @var class-string<StringNormalizable> $className
         */
        $className = self::lookupName($this);
        if (!class_exists($className)) {
            throw new Exception\InvalidTypeName($className);
        }

        return $className;
    }
}
