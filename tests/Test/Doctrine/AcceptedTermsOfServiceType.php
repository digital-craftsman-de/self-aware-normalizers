<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\BoolNormalizableType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\AcceptedTermsOfService;

final class AcceptedTermsOfServiceType extends BoolNormalizableType
{
    #[\Override]
    public static function getTypeName(): string
    {
        return 'accepted_terms_of_service';
    }

    #[\Override]
    public static function getClass(): string
    {
        return AcceptedTermsOfService::class;
    }
}
