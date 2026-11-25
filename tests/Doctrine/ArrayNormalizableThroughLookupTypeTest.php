<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Doctrine;

use BuronDigital\SelfAwareNormalizers\Test\DTO\GetUsersQuery;
use BuronDigital\SelfAwareNormalizers\Test\DTO\Project;
use BuronDigital\SelfAwareNormalizers\Test\DTO\Search;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\Limit;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\ProjectId;
use BuronDigital\SelfAwareNormalizers\Test\ValueObject\SearchTerm;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
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
        $doctrineType = new ArrayNormalizableThroughLookupType();

        Type::addType(Search::class, $doctrineType);

        $value = new Search(
            searchTerm: new SearchTerm('peter'),
            limit: new Limit(10),
        );
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
