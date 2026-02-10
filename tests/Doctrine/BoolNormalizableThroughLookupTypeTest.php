<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\AcceptedTermsOfServiceAsInt;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\AcceptedTermsOfService;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
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
}
