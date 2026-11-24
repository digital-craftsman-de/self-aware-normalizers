<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\LowRange;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Opacity;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Range;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FloatNormalizableThroughLookupType::class)]
final class FloatNormalizableThroughLookupTypeTest extends TestCase
{
    #[Test]
    public function get_sql_declaration_and_convert_works(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new FloatNormalizableThroughLookupType();

        Type::addType(Range::class, $doctrineType);

        $plainValue = 0.5;
        $value = new Range($plainValue);
        $nullValue = null;

        $column = [];
        $expectedSqlDeclaration = 'DOUBLE PRECISION';

        // -- Act
        $sqlDeclaration = $doctrineType->getSQLDeclaration($column, $platform);

        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        $convertedValueThatWasAnIntBefore = $doctrineType->convertToDatabaseValue($plainValue, $platform);

        $databaseNullValue = $doctrineType->convertToDatabaseValue($nullValue, $platform);
        $convertedNullValue = $doctrineType->convertToPHPValue($databaseNullValue, $platform);

        // -- Assert
        self::assertSame($expectedSqlDeclaration, $sqlDeclaration);
        self::assertEquals($value, $convertedValue);
        self::assertEquals($nullValue, $convertedNullValue);
        self::assertEquals($plainValue, $convertedValueThatWasAnIntBefore);
    }

    #[Test]
    public function get_sql_declaration_works_with_own_sql_declaration(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new FloatNormalizableThroughLookupType();

        Type::addType(LowRange::class, $doctrineType);

        $column = [];
        $expectedSqlDeclaration = 'NUMERIC(3)';

        // -- Act
        $sqlDeclaration = $doctrineType->getSQLDeclaration($column, $platform);

        // -- Assert
        self::assertSame($expectedSqlDeclaration, $sqlDeclaration);
    }

    #[Test]
    public function lookup_fails_on_invalid_name(): void
    {
        // -- Assert
        $this->expectException(Exception\InvalidTypeName::class);

        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new FloatNormalizableThroughLookupType();

        Type::addType(sprintf('invalid-%s', Range::class), $doctrineType);

        $value = 50;

        // -- Act
        $doctrineType->convertToPHPValue($value, $platform);
    }

    /**
     * Doctrine creates one instance per type name and reuses it. The class name is therefore only looked up once per instance and not
     * for every converted value, as the lookup is expensive (it's deprecated since doctrine/dbal 4.6 and triggers a deprecation).
     */
    #[Test]
    public function class_name_is_only_looked_up_once_per_type_instance(): void
    {
        // -- Arrange
        Deprecation::enableTrackingDeprecations();

        $platform = new PostgreSQLPlatform();
        $doctrineType = new FloatNormalizableThroughLookupType();

        Type::addType(Opacity::class, $doctrineType);

        $value = new Opacity(0.8);
        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);

        // -- Act
        $deprecationsBeforeFirstConversion = Deprecation::getUniqueTriggeredDeprecationsCount();

        $doctrineType->convertToPHPValue($databaseValue, $platform);

        $deprecationsBeforeFurtherConversions = Deprecation::getUniqueTriggeredDeprecationsCount();

        $doctrineType->getSQLDeclaration([], $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);
        $convertedDatabaseValue = $doctrineType->convertToDatabaseValue($value, $platform);

        $deprecationsAfterFurtherConversions = Deprecation::getUniqueTriggeredDeprecationsCount();

        // -- Assert
        self::assertSame(1, $deprecationsBeforeFurtherConversions - $deprecationsBeforeFirstConversion);
        self::assertSame(0, $deprecationsAfterFurtherConversions - $deprecationsBeforeFurtherConversions);
        self::assertEquals($value, $convertedValue);
        self::assertSame($databaseValue, $convertedDatabaseValue);
    }
}
