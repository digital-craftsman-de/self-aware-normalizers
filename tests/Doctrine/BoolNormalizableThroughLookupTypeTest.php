<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Doctrine;

use BuronDigital\SelfAwareNormalizers\Test\DTO\AcceptedTermsOfServiceAsInt;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\AcceptedTermsOfService;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\SubscribedToNewsletter;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoolNormalizableThroughLookupType::class)]
final class BoolNormalizableThroughLookupTypeTest extends TestCase
{
    #[Test]
    public function get_sql_declaration_and_convert_works(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new BoolNormalizableThroughLookupType();

        Type::addType(AcceptedTermsOfService::class, $doctrineType);

        $plainValue = true;
        $value = new AcceptedTermsOfService($plainValue);
        $nullValue = null;

        $column = [];
        $expectedSqlDeclaration = 'BOOLEAN';

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
        $doctrineType = new BoolNormalizableThroughLookupType();

        Type::addType(AcceptedTermsOfServiceAsInt::class, $doctrineType);

        $column = [];
        $expectedSqlDeclaration = 'INT';

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
        $doctrineType = new BoolNormalizableThroughLookupType();

        Type::addType(sprintf('invalid-%s', AcceptedTermsOfService::class), $doctrineType);

        $value = true;

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
        $doctrineType = new BoolNormalizableThroughLookupType();

        Type::addType(SubscribedToNewsletter::class, $doctrineType);

        $value = new SubscribedToNewsletter(true);
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
