<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\FloatNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class FloatNormalizableThroughLookupType extends Type
{
    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $className = $this->getClassNameThroughLookup();
        $reflectionClass = new \ReflectionClass($className);

        if ($reflectionClass->implementsInterface(NormalizableTypeWithSQLDeclaration::class)) {
            return $className::getSQLDeclaration($column, $platform);
        }

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

        $className = $this->getClassNameThroughLookup();

        return $className::denormalize((float) $value);
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

    /**
     * @return class-string<FloatNormalizable>
     */
    private function getClassNameThroughLookup(): string
    {
        /**
         * @var class-string<FloatNormalizable> $className
         */
        $className = self::lookupName($this);
        if (!class_exists($className)) {
            throw new Exception\InvalidTypeName($className);
        }

        return $className;
    }
}
