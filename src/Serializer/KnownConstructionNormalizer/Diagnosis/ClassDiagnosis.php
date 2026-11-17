<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis;

/**
 * How the KnownConstructionNormalizer handles a class: whether it's planned, why not, whether its objects are also normalized through the plan, and
 * which of its parameters are (de)normalized through the serializer.
 */
final readonly class ClassDiagnosis
{
    /**
     * @param ?PlanLimitation             $limitation              Null when the class is planned
     * @param ?PlanLimitation             $normalizationLimitation Only for a planned class that is normalized through the fallback
     * @param list<DelegatedParameter>    $delegatedParameters     Only for a planned class
     * @param array<string, class-string> $valueObjectParameters   Parameter => class of the self-aware value objects the plan handles
     *                                                             inline, only for a planned class
     */
    public function __construct(
        public string $class,
        public ?PlanLimitation $limitation,
        public ?PlanLimitation $normalizationLimitation,
        public array $delegatedParameters,
        public array $valueObjectParameters = [],
    ) {
    }
}
