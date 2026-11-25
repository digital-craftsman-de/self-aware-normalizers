<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Diagnosis;

/**
 * Why a class gets no plan (or is only denormalized through its plan), with the property or parameter it's about and details like the
 * type that can't be planned.
 */
final readonly class PlanLimitation
{
    public function __construct(
        public PlanLimitationReason $reason,
        public ?string $member,
        public ?string $detail,
    ) {
    }

    public static function ofClass(PlanLimitationReason $reason): self
    {
        return new self($reason, null, null);
    }

    public function describe(): string
    {
        $description = $this->reason->description();
        if ($this->member !== null) {
            $description = sprintf('%s: $%s', $description, $this->member);
        }
        if ($this->detail !== null) {
            $description = sprintf('%s (%s)', $description, $this->detail);
        }

        return $description;
    }
}
