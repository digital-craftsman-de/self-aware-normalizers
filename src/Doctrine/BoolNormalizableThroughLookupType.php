<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\BoolNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class BoolNormalizableThroughLookupType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $className = $this->getClassNameThroughLookup();
        $reflectionClass = new \ReflectionClass($className);

        if ($reflectionClass->implementsInterface(NormalizableTypeWithSQLDeclaration::class)) {
            return $className::getSQLDeclaration($column, $platform);
        }

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

        $className = $this->getClassNameThroughLookup();

        return $className::denormalize($value);
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

    /**
     * @return class-string<BoolNormalizable>
     */
    private function getClassNameThroughLookup(): string
    {
        /**
         * @var class-string<BoolNormalizable> $className
         */
        $className = self::lookupName($this);
        if (!class_exists($className)) {
            throw new Exception\InvalidTypeName($className);
        }

        return $className;
    }
}
