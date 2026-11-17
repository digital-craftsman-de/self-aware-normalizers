<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Exception;

/**
 * Internal signal of the known construction normalizer: the data doesn't match what the plan accepts, so the object is denormalized again by the
 * fallback normalizer, which then throws its own exception (or returns its own result). Never leaves the known construction normalizer.
 *
 * Carries why (and for which parameter of which class, the innermost one for nested classes), for the profiler. Only built when the data
 * doesn't match, never on the fast path.
 *
 * @internal
 */
final class FallbackIsRequired extends \RuntimeException
{
    private ?string $class = null;
    private ?string $parameter = null;

    private function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('The data must be denormalized by the fallback normalizer.');
    }

    public static function missingValue(): self
    {
        return new self('missing key');
    }

    public static function nullForNonNullableParameter(): self
    {
        return new self('null for a non-nullable parameter');
    }

    public static function unexpectedValue(
        string $expectedType,
        mixed $value,
    ): self {
        return new self(sprintf('%s expected, %s given', $expectedType, get_debug_type($value)));
    }

    public static function invalidKey(int | string $key): self
    {
        return new self(sprintf('key %s of the wrong type', get_debug_type($key)));
    }

    public static function constructorFailed(\Error $error): self
    {
        return new self(sprintf('the constructor failed (%s: %s)', $error::class, $error->getMessage()));
    }

    public static function valueObjectNotDenormalizable(): self
    {
        return new self('the value object can not be denormalized from the value');
    }

    public static function serializerFailed(
        string $type,
        ?\Throwable $exception,
    ): self {
        return new self($exception !== null
            ? sprintf('the serializer failed to denormalize %s (%s: %s)', $type, $exception::class, $exception->getMessage())
            : sprintf('no normalizer of the serializer supports %s', $type));
    }

    public static function noPlan(string $class): self
    {
        return new self(sprintf('%s has no plan', $class));
    }

    /**
     * The parameter of the class whose value failed. Only the first (innermost) location is kept.
     */
    public function at(
        string $class,
        ?string $parameter,
    ): self {
        if ($this->class === null) {
            $this->class = $class;
            $this->parameter = $parameter;
        }

        return $this;
    }

    public function describe(): string
    {
        return match (true) {
            $this->class === null => $this->reason,
            $this->parameter === null => sprintf('%s: %s', $this->class, $this->reason),
            default => sprintf('%s::$%s: %s', $this->class, $this->parameter, $this->reason),
        };
    }
}
