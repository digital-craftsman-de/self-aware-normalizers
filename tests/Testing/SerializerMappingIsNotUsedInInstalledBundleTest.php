<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The test case in an app that installs this bundle as non-dev package: the source of the bundle is scanned like a vendor package, its
 * own test cases (src/Testing, which use the attributes on purpose) are skipped.
 */
#[CoversClass(SerializerMappingIsNotUsedTestCase::class)]
final class SerializerMappingIsNotUsedInInstalledBundleTest extends SerializerMappingIsNotUsedTestCase
{
    #[\Override]
    protected static function sourceDirectories(): array
    {
        return [
            dirname(__DIR__, 2).'/src/Serializer',
        ];
    }

    /**
     * The bundle as the only package.
     */
    #[\Override]
    protected static function vendorPackageDirectories(): array
    {
        return [
            dirname(__DIR__, 2).'/src',
        ];
    }

    #[\Override]
    protected static function allowedVendorFiles(): array
    {
        return [];
    }
}
