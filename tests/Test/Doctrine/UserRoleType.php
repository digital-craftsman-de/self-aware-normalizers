<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\StringEnumType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\UserRole;

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
