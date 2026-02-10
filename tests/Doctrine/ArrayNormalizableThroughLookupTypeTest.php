<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Doctrine;

use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\GetUsersQuery;
use DigitalCraftsman\SelfAwareNormalizers\Test\DTO\Project;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\ProjectId;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayNormalizableThroughLookupType::class)]
final class ArrayNormalizableThroughLookupTypeTest extends TestCase
{
    #[Test]
    public function get_sql_declaration_and_convert_works(): void
    {
        // -- Arrange
        $doctrineType = new ArrayNormalizableThroughLookupType();
        $platform = new PostgreSQLPlatform();

        Type::addType(Project::class, $doctrineType);

        $column = [];
        $expectedSqlDeclaration = 'JSONB';

        $value = new Project(
            projectId: ProjectId::generateRandom(),
            name: 'Great project',
        );
        $nullValue = null;

        // -- Act
        $sqlDeclaration = $doctrineType->getSQLDeclaration($column, $platform);

        $databaseValue = $doctrineType->convertToDatabaseValue($value, $platform);
        $convertedValue = $doctrineType->convertToPHPValue($databaseValue, $platform);

        $databaseNullValue = $doctrineType->convertToDatabaseValue($nullValue, $platform);
        $convertedNullValue = $doctrineType->convertToPHPValue($databaseNullValue, $platform);

        // -- Assert
        self::assertSame($expectedSqlDeclaration, $sqlDeclaration);
        self::assertEquals($value, $convertedValue);
        self::assertEquals($nullValue, $convertedNullValue);
    }

    #[Test]
    public function get_sql_declaration_works_with_own_sql_declaration(): void
    {
        // -- Arrange
        $platform = new PostgreSQLPlatform();
        $doctrineType = new ArrayNormalizableThroughLookupType();

        Type::addType(GetUsersQuery::class, $doctrineType);

        $column = [];
        $expectedSqlDeclaration = 'JSON';

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
        $doctrineType = new ArrayNormalizableThroughLookupType();

        Type::addType(sprintf('invalid-%s', Project::class), $doctrineType);

        $value = 'test';

        // -- Act
        $doctrineType->convertToPHPValue($value, $platform);
    }
}
