<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine\UserRoleType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\UserRole;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringEnumType::class)]
final class StringEnumTypeTest extends TestCase
{
    #[Test]
    public function convert_works(): void
    {
        // -- Arrange
        $doctrineType = new UserRoleType();
        $platform = new PostgreSQLPlatform();

        $value = UserRole::ROLE_USER;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        // -- Assert
        self::assertEquals($value, $convertedValue);
    }

    #[Test]
    public function convert_works_with_null(): void
    {
        // -- Arrange
        $doctrineType = new UserRoleType();
        $platform = new PostgreSQLPlatform();

        $value = null;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        // -- Assert
        self::assertEquals($value, $convertedValue);
    }

    #[Test]
    public function convert_to_database_value_pipe_through_works_with_string(): void
    {
        // -- Arrange
        $doctrineType = new UserRoleType();
        $platform = new PostgreSQLPlatform();

        $value = UserRole::ROLE_USER->value;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);

        // -- Assert
        self::assertEquals($value, $databaseValue);
    }
}
