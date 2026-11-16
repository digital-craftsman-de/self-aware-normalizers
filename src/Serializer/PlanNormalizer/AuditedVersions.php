<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer;

use Composer\InstalledVersions;

/**
 * The PlanNormalizer mirrors internals of these packages (AbstractObjectNormalizer::denormalizeParameter() and validateAndDenormalize(),
 * AbstractNormalizer::instantiateObject(), the ArrayDenormalizer, the context options, the type resolution of type-info and the type
 * extraction of property-info). It's only equivalent to the PropertyNormalizer for the versions it was audited against, so the bundle
 * refuses to enable it with any other version (see SelfAwareNormalizersExtension).
 *
 * The core of the bundle (the self-aware normalizers and Doctrine types) is independent of it and keeps supporting all versions of
 * composer.json. Supporting a new version: audit PlanFactory and PlanNormalizer against the changes of the packages, then add the version
 * here (the tests of the bundle run against the installed versions).
 *
 * @internal
 */
final readonly class AuditedVersions
{
    /**
     * Package => audited minor versions.
     */
    public const array AUDITED_VERSIONS = [
        'symfony/serializer' => ['8.1'],
        'symfony/type-info' => ['8.1'],
        'symfony/property-info' => ['8.1'],
    ];

    /**
     * Descriptions of the packages whose installed version isn't audited (or which aren't installed at all), empty when every package is
     * installed in an audited version.
     *
     * @param \Closure(string): ?string|null $installedVersion Package => installed version (null when not installed), Composer by default
     *
     * @return list<string>
     */
    public static function violations(?\Closure $installedVersion = null): array
    {
        $installedVersion ??= self::installedVersion(...);

        $violations = [];
        foreach (self::AUDITED_VERSIONS as $package => $auditedVersions) {
            $version = $installedVersion($package);
            if ($version === null) {
                $violations[] = sprintf('%s is not installed (audited: %s)', $package, implode(', ', $auditedVersions));

                continue;
            }

            $minorVersion = implode('.', array_slice(explode('.', ltrim($version, 'v')), 0, 2));
            if (!in_array($minorVersion, $auditedVersions, true)) {
                $violations[] = sprintf('%s is installed in version %s (audited: %s)', $package, $version, implode(', ', $auditedVersions));
            }
        }

        return $violations;
    }

    /**
     * @codeCoverageIgnore Depends on the installation
     */
    private static function installedVersion(string $package): ?string
    {
        return InstalledVersions::isInstalled($package)
            ? InstalledVersions::getVersion($package)
            : null;
    }
}
