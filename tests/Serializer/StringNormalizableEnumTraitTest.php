<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringNormalizableEnumTraitTest extends TestCase
{
    #[Test]
    public function denormalize_and_normalize_works(): void
    {
        // -- Arrange
        $userRole = UserRole::ROLE_USER;
        $data = 'ROLE_USER';

        // -- Act
        $denormalized = UserRole::denormalize($data);
        $normalized = $userRole->normalize();

        // -- Assert
        $this->assertSame($userRole, $denormalized);
        $this->assertSame($data, $normalized);
    }
}
