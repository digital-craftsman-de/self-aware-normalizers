<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Test\DTO;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\AcceptedTermsOfService;
use DigitalCraftsman\SelfAwareNormalizers\Test\ValueObject\SearchTerm;

/**
 * @psalm-type NormalizedSearchWithOptionalAcceptedTermsOfService = array{
 *     searchTerm: string,
 *     acceptedTermsOfService: bool | null,
 * }
 */
final readonly class SearchWithOptionalAcceptedTermsOfService implements ArrayNormalizable
{
    public function __construct(
        public SearchTerm $searchTerm,
        public ?AcceptedTermsOfService $acceptedTermsOfService,
    ) {
    }

    // -- Array normalizable

    /**
     * @param NormalizedSearchWithOptionalAcceptedTermsOfService $data
     */
    #[\Override]
    public static function denormalize(array $data): self
    {
        return new self(
            searchTerm: SearchTerm::denormalize($data['searchTerm']),
            acceptedTermsOfService: AcceptedTermsOfService::denormalizeWhenNotNull($data['acceptedTermsOfService']),
        );
    }

    /**
     * @return NormalizedSearchWithOptionalAcceptedTermsOfService
     */
    #[\Override]
    public function normalize(): array
    {
        return [
            'searchTerm' => $this->searchTerm->normalize(),
            'acceptedTermsOfService' => $this->acceptedTermsOfService?->normalize(),
        ];
    }
}
