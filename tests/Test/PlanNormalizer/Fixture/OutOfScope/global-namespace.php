<?php

declare(strict_types=1);

/**
 * Discovered by ListUnplannedClassesCommandTest: a namespace block without name is the global namespace. Never loaded, so the class is
 * skipped.
 */

namespace {
    final readonly class PlanNormalizerFixtureInTheGlobalNamespace
    {
    }
}
