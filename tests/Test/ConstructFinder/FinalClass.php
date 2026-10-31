<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ConstructFinder;

/**
 * class NotAClassInDocBlock.
 */
final class FinalClass
{
    // class NotAClassInComment
    public function className(): string
    {
        return self::class;
    }

    public function anonymousClass(): object
    {
        return new class {
        };
    }

    public function anonymousReadonlyClass(): object
    {
        return new readonly class {
        };
    }

    public function anonymousClassWithArguments(): object
    {
        return new class(1) {
            public function __construct(
                public int $value,
            ) {
            }
        };
    }

    public function anonymousClassExtendingClass(): object
    {
        return new class extends \stdClass {
        };
    }

    public function anonymousClassWithAttribute(): object
    {
        return new #[\AllowDynamicProperties] class {
        };
    }
}
