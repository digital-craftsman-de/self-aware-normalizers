<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\ValueObject;

use BuronDigital\SelfAwareNormalizers\Serializer\BoolNormalizable;

final readonly class SubscribedToNewsletter implements BoolNormalizable
{
    public function __construct(
        public bool $isSubscribedToNewsletter,
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
        return $this->isSubscribedToNewsletter;
    }
}
