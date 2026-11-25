<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Doctrine;

use BuronDigital\SelfAwareNormalizers\Serializer\FloatNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class FloatNormalizableThroughLookupType extends Type
{
    /**
     * @var class-string<FloatNormalizable>|null
     */
    private ?string $className = null;

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
     * The class name is resolved once per type instance. Doctrine creates one instance per type name and reuses it, so the hot path of
     * the conversion doesn't need a lookup in the type registry, which is expensive and deprecated since doctrine/dbal 4.6 (every call
     * triggers a deprecation).
     *
     * @return class-string<FloatNormalizable>
     */
    private function getClassNameThroughLookup(): string
    {
        return $this->className ??= $this->lookupClassName();
    }

    /**
     * @return class-string<FloatNormalizable>
     */
    private function lookupClassName(): string
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
