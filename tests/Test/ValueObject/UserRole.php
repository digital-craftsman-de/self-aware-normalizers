<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\ValueObject;

use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizable;
use BuronDigital\SelfAwareNormalizers\Serializer\StringNormalizableEnumTrait;

enum UserRole: string implements StringNormalizable
{
    use StringNormalizableEnumTrait;

    case ROLE_USER = 'ROLE_USER';
    case ROLE_ADMIN = 'ROLE_ADMIN';
}
