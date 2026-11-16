<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\Exception;

/**
 * Internal signal of the plan normalizer: the data doesn't match what the plan accepts, so the object is denormalized again by the
 * fallback normalizer, which then throws its own exception (or returns its own result). Never leaves the plan normalizer.
 *
 * @internal
 *
 * @psalm-immutable
 */
final class FallbackIsRequired extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The data must be denormalized by the fallback normalizer.');
    }
}
