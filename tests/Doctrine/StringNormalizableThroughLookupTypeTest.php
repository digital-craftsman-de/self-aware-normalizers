<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\CustomerName;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\Description;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\ProjectName;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringNormalizableThroughLookupType::class)]
#[CoversClass(Exception\InvalidTypeName::class)]
final class StringNormalizableThroughLookupTypeTest extends TestCase
{
    #[Test]
    public function get_sql_declaration_and_convert_works(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new StringNormalizableThroughLookupType();

        Type::addType(SearchTerm::class, $doctrineType);

        $plainValue = 'peter';
        $value = new SearchTerm($plainValue);
        $nullValue = null;

        $column = [];
        $expectedSqlDeclaration = 'VARCHAR(255)';

        // -- Act
        $sqlDeclaration = $doctrineType->getSQLDeclaration($column, $platform);

        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        $convertedValueThatWasAStringBefore = $doctrineType->convertToDatabaseValue($value->search, $platform);

        $databaseNullValue = $doctrineType->convertToDatabaseValue($nullValue, $platform);
        $convertedNullValue = $doctrineType->convertToPHPValue($databaseNullValue, $platform);

        // -- Assert
        self::assertSame($expectedSqlDeclaration, $sqlDeclaration);
        self::assertEquals($value, $convertedValue);
        self::assertEquals($nullValue, $convertedNullValue);
        self::assertEquals($plainValue, $convertedValueThatWasAStringBefore);
    }

    #[Test]
    public function get_sql_declaration_works_with_max_length(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new StringNormalizableThroughLookupType();

        Type::addType(ProjectName::class, $doctrineType);

        $column = [];
        $expectedSqlDeclaration = 'VARCHAR(50)';

        $columnWithLength = [
            'length' => 75,
        ];
        $expectedSqlDeclarationWithLength = 'VARCHAR(75)';

        // -- Act
        $sqlDeclaration = $doctrineType->getSQLDeclaration($column, $platform);
        $sqlDeclarationWithLength = $doctrineType->getSQLDeclaration($columnWithLength, $platform);

        // -- Assert
        self::assertSame($expectedSqlDeclaration, $sqlDeclaration);
        self::assertSame($expectedSqlDeclarationWithLength, $sqlDeclarationWithLength);
    }

    #[Test]
    public function get_sql_declaration_works_with_own_sql_declaration(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new StringNormalizableThroughLookupType();

        Type::addType(CustomerName::class, $doctrineType);

        $column = [];
        $expectedSqlDeclaration = 'VARCHAR(70)';

        // -- Act
        $sqlDeclaration = $doctrineType->getSQLDeclaration($column, $platform);

        // -- Assert
        self::assertSame($expectedSqlDeclaration, $sqlDeclaration);
    }

    #[Test]
    public function get_sql_declaration_works_as_text(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new StringNormalizableThroughLookupType();

        Type::addType(Description::class, $doctrineType);

        $column = [];
        $expectedSqlDeclaration = 'TEXT';

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
        $doctrineType = new StringNormalizableThroughLookupType();

        Type::addType(sprintf('invalid-%s', SearchTerm::class), $doctrineType);

        $value = 'test';

        // -- Act
        $doctrineType->convertToPHPValue($value, $platform);
    }
}
