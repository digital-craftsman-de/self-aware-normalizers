<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Doctrine;

use BuronDigital\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ArrayNormalizableThroughLookupType extends Type
{
    /**
     * @var class-string<ArrayNormalizable>|null
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

        return $platform->getJsonbTypeDeclarationSQL($column);
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

        $className = $this->getClassNameThroughLookup();

        $array = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return $className::denormalize($array);
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

    /**
     * The class name is resolved once per type instance. Doctrine creates one instance per type name and reuses it, so the hot path of
     * the conversion doesn't need a lookup in the type registry, which is expensive and deprecated since doctrine/dbal 4.6 (every call
     * triggers a deprecation).
     *
     * @return class-string<ArrayNormalizable>
     */
    private function getClassNameThroughLookup(): string
    {
        return $this->className ??= $this->lookupClassName();
    }

    /**
     * @return class-string<ArrayNormalizable>
     */
    private function lookupClassName(): string
    {
        /**
         * @var class-string<ArrayNormalizable> $className
         */
        $className = self::lookupName($this);
        if (!class_exists($className)) {
            throw new Exception\InvalidTypeName($className);
        }

        return $className;
    }
}
