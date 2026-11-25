<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\Doctrine;

use BuronDigital\SelfAwareNormalizers\Doctrine\StringNormalizableType;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\SearchTerm;

final class SearchTermType extends StringNormalizableType
{
    #[\Override]
    public static function getTypeName(): string
    {
        return 'search_term';
    }

    #[\Override]
    public static function getClass(): string
    {
        return SearchTerm::class;
    }
}
