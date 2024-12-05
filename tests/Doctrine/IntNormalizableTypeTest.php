<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine\LimitType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Limit;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntNormalizableType::class)]
final class IntNormalizableTypeTest extends TestCase
{
    #[Test]
    public function convert_works(): void
    {
        // -- Arrange
        $doctrineType = new LimitType();
        $platform = new PostgreSQLPlatform();

        $value = new Limit(60);

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
        $doctrineType = new LimitType();
        $platform = new PostgreSQLPlatform();

        $value = null;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        // -- Assert
        self::assertEquals($value, $convertedValue);
    }

    #[Test]
    public function convert_to_database_value_works_with_int(): void
    {
        // -- Arrange
        $doctrineType = new LimitType();
        $platform = new PostgreSQLPlatform();

        $value = 60;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);

        // -- Assert
        self::assertEquals($value, $databaseValue);
    }
}
