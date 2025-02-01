<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\StringNormalizable;

final readonly class ProjectId implements StringNormalizable
{
    public string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function generateRandom(): self
    {
        return new self(uuid_create());
    }

    public static function denormalize(string $data): self
    {
        return new self($data);
    }

    public function normalize(): string
    {
        return $this->id;
    }
}
