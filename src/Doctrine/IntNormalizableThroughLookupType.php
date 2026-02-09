<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\IntNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class IntNormalizableThroughLookupType extends Type
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

        $className = $this->getClassNameThroughLookup();

        return $className::denormalize($value);
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
     * @return class-string<IntNormalizable>
     */
    private function getClassNameThroughLookup(): string
    {
        /**
         * @var class-string<IntNormalizable> $className
         */
        $className = self::lookupName($this);
        if (!class_exists($className)) {
            throw new Exception\InvalidTypeName($className);
        }

        return $className;
    }
}
