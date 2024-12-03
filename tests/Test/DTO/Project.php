<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\ProjectId;

final readonly class Project implements ArrayNormalizable
{
    public function __construct(
        public ProjectId $projectId,
        public string $name,
    ) {
    }

    // -- Array normalizable

    /**
     * @param array{
     *     projectId: string,
     *      name: string,
     * } $data
     */
    public static function denormalize(array $data): self
    {
        return new self(
            projectId: ProjectId::fromString($data['projectId']),
            name: $data['name'],
        );
    }

    /**
     * @return array{
     *     projectId: string,
     *      name: string,
     * }
     */
    public function normalize(): array
    {
        return [
            'projectId' => (string) $this->projectId,
            'name' => $this->name,
        ];
    }
}
