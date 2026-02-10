<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizableEnumTrait;

enum UserRole: string implements StringNormalizable
{
    use StringNormalizableEnumTrait;

    case ROLE_USER = 'ROLE_USER';
    case ROLE_ADMIN = 'ROLE_ADMIN';
}
