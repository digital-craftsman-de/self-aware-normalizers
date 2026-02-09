<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

interface StringNormalizableTypeWithMaxLength
{
    public static function maxLength(): int;
}
