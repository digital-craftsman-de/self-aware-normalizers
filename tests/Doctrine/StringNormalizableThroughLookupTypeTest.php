<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringNormalizableThroughLookupType::class)]
final class StringNormalizableThroughLookupTypeTest extends TestCase
{
    #[Test]
    public function convert_works(): void
    {
        // -- Arrange
        $doctrineType = new StringNormalizableThroughLookupType();
        $platform = new PostgreSQLPlatform();

        if (!StringNormalizableType::hasType(SearchTerm::class)) {
            StringNormalizableType::addType(SearchTerm::class, $doctrineType);
        }

        $value = new SearchTerm('peter');

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        // -- Assert
        self::assertEquals($value, $convertedValue);
    }

    #[Test]
    public function convert_to_database_value_works_with_string(): void
    {
        // -- Arrange
        $doctrineType = new StringNormalizableThroughLookupType();
        $platform = new PostgreSQLPlatform();

        $value = 'peter';

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);

        // -- Assert
        self::assertEquals($value, $databaseValue);
    }

    #[Test]
    public function convert_works_with_null(): void
    {
        // -- Arrange
        $doctrineType = new StringNormalizableThroughLookupType();
        $platform = new PostgreSQLPlatform();

        if (!StringNormalizableType::hasType(SearchTerm::class)) {
            StringNormalizableType::addType(SearchTerm::class, $doctrineType);
        }

        $value = null;

        // -- Act
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        // -- Assert
        self::assertEquals($value, $convertedValue);
    }
}
