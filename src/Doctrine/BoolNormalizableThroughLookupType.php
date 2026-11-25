<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Doctrine;

use BuronDigital\SelfAwareNormalizers\Serializer\BoolNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class BoolNormalizableThroughLookupType extends Type
{
    /**
     * @var class-string<BoolNormalizable>|null
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
     * The class name is resolved once per type instance. Doctrine creates one instance per type name and reuses it, so the hot path of
     * the conversion doesn't need a lookup in the type registry, which is expensive and deprecated since doctrine/dbal 4.6 (every call
     * triggers a deprecation).
     *
     * @return class-string<BoolNormalizable>
     */
    private function getClassNameThroughLookup(): string
    {
        return $this->className ??= $this->lookupClassName();
    }

    /**
     * @return class-string<BoolNormalizable>
     */
    private function lookupClassName(): string
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
