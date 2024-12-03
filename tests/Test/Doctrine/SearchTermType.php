<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\StringNormalizableType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;

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
