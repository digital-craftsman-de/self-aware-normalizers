<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\DTO;

use BuronDigital\SelfAwareNormalizers\Doctrine\NormalizableTypeWithSQLDeclaration;
use BuronDigital\SelfAwareNormalizers\Serializer\BoolNormalizable;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final readonly class AcceptedTermsOfServiceAsInt implements BoolNormalizable, NormalizableTypeWithSQLDeclaration
{
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

    #[\Override]
    public static function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string {
        return 'INT';
    }
}
