<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\BoolNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\NullableBoolDenormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\NullableBoolDenormalizableTrait;

final readonly class AcceptedTermsOfService implements BoolNormalizable, NullableBoolDenormalizable
{
    use NullableBoolDenormalizableTrait;

    public function __construct(
        public bool $hasAcceptedTermsOfService,
    ) {
    }

    // -- Bool normalizable

    #[\Override]
    public static function denormalize(bool $data): self
    {
        return new self($data);
    }

    #[\Override]
    public function normalize(): bool
    {
        return $this->hasAcceptedTermsOfService;
    }
}
