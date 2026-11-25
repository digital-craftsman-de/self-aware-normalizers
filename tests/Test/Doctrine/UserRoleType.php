<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\Doctrine;

use BuronDigital\SelfAwareNormalizers\Doctrine\StringEnumType;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\UserRole;

final class UserRoleType extends StringEnumType
{
    #[\Override]
    public static function getTypeName(): string
    {
        return 'user_role';
    }

    #[\Override]
    public static function getClass(): string
    {
        return UserRole::class;
    }
}
