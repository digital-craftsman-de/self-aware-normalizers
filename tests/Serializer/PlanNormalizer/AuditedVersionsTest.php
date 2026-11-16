<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditedVersions::class)]
final class AuditedVersionsTest extends TestCase
{
    #[Test]
    public function installed_versions_are_audited(): void
    {
        // -- Act
        $violations = AuditedVersions::violations();

        // -- Assert
        self::assertSame(
            [],
            $violations,
            'The PlanNormalizer mirrors internals of these packages (AbstractObjectNormalizer::denormalizeParameter() and '
            .'validateAndDenormalize(), AbstractNormalizer::instantiateObject(), ArrayDenormalizer, the context options, type-info and '
            .'property-info). Re-audit PlanFactory and PlanNormalizer against the new versions, then add the versions to AuditedVersions.',
        );
    }

    #[Test]
    public function versions_that_are_not_audited_or_not_installed_are_violations(): void
    {
        // -- Arrange
        $installedVersions = [
            'symfony/serializer' => null,
            'symfony/type-info' => '8.2.0.0',
            'symfony/property-info' => 'v8.1.10',
        ];

        // -- Act
        $violations = AuditedVersions::violations(static fn (string $package): ?string => $installedVersions[$package]);

        // -- Assert
        self::assertSame(
            [
                'symfony/serializer is not installed (audited: 8.1)',
                'symfony/type-info is installed in version 8.2.0.0 (audited: 8.1)',
            ],
            $violations,
        );
    }
}
