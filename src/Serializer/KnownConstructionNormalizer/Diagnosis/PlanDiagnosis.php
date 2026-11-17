<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\PlanFactory;

/**
 * Result of the PlanFactory for a class: either the plan or the limitation that prevents it. A planned class whose objects are only
 * denormalized through the plan carries the limitation of the normalization.
 *
 * @psalm-import-type ClassPlan from PlanFactory
 */
final readonly class PlanDiagnosis
{
    /**
     * @param ClassPlan|null $plan
     */
    private function __construct(
        public ?array $plan,
        public ?PlanLimitation $limitation,
        public ?PlanLimitation $normalizationLimitation,
    ) {
    }

    /**
     * @param ClassPlan $plan
     */
    public static function planned(
        array $plan,
        ?PlanLimitation $normalizationLimitation,
    ): self {
        return new self($plan, null, $normalizationLimitation);
    }

    public static function notPlanned(PlanLimitation $limitation): self
    {
        return new self(null, $limitation, null);
    }
}
