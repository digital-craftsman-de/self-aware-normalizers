<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\PlanNormalizer\Fixture\NotLoadable;

/**
 * Can't be loaded, like a class whose parent only exists with the dev dependencies (e.g. a Rector rule on prod). Only discovered by
 * ListUnplannedClassesCommandTest.
 *
 * @psalm-suppress UndefinedClass On purpose
 */
final class MissingParent extends DoesNotExist
{
}
