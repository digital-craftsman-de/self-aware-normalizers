<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;

interface NormalizableTypeWithSQLDeclaration
{
    /**
     * @param array<string, mixed> $column
     */
    public static function getSQLDeclaration(array $column, AbstractPlatform $platform): string;
}
