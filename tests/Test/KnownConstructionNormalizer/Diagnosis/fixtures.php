<?php

declare(strict_types=1);

/**
 * Fixture classes of PlanFactoryTest for the limitations of parameters that no other fixture reaches, as an earlier check
 * catches them there. Outside of the Fixture directory, so the equivalence test doesn't sample them.
 */

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Diagnosis;

final readonly class VariadicParameter
{
    public function __construct(
        public string $name,
        string ...$names,
    ) {
    }
}

final readonly class ParameterPassedByReference
{
    public function __construct(
        public string $name,
        ?string &$reference = null,
    ) {
    }
}

final class UntypedParameter
{
    /**
     * @psalm-suppress MissingParamType, MissingPropertyType Untyped on purpose
     */
    public function __construct(
        public $name,
    ) {
    }
}
