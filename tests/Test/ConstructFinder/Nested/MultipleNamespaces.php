<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder\Nested {
    final class ClassInFirstNamespace
    {
    }

    enum UnitEnum
    {
        case FIRST;
    }
}

namespace {
    final class ConstructFinderClassInGlobalNamespace
    {
    }
}

namespace ConstructFinderSingleSegmentNamespace {
    final class ClassInSingleSegmentNamespace
    {
    }
}
