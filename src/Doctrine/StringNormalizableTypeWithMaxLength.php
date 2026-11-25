<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Doctrine;

interface StringNormalizableTypeWithMaxLength
{
    public static function maxLength(): int;
}
