<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\Doctrine\AcceptedTermsOfServiceType;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\AcceptedTermsOfService;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoolNormalizableType::class)]
final class BoolNormalizableTypeTest extends TestCase
{
    #[Test]
    public function convert_works(): void
    {
        // -- Arrange
        $doctrineType = new AcceptedTermsOfServiceType();
        $platform = new PostgreSQLPlatform();

        $value = new AcceptedTermsOfService(true);

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
        $doctrineType = new AcceptedTermsOfServiceType();
        $platform = new PostgreSQLPlatform();

        $value = null;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        // -- Assert
        self::assertEquals($value, $convertedValue);
    }

    #[Test]
    public function convert_to_database_value_works_with_bool(): void
    {
        // -- Arrange
        $doctrineType = new AcceptedTermsOfServiceType();
        $platform = new PostgreSQLPlatform();

        $value = true;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);

        // -- Assert
        self::assertEquals($value, $databaseValue);
    }
}
