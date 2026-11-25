<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Equivalence;

/**
 * The state of the SampleFactory while it builds a sample, for SampleProviders: the values it currently tries (it tries several
 * strategies until a class can be built) and a way to build samples of other classes.
 */
final readonly class SampleContext
{
    /**
     * @param int                                                   $sequence         Increases with every sample within the class under test,
     *                                                                                e.g. for dates that must increase
     * @param int                                                   $int              The int the SampleFactory currently uses for int values
     * @param bool                                                  $bool             The bool the SampleFactory currently uses for bool values
     * @param int                                                   $numberOfElements The number of elements the SampleFactory currently uses
     *                                                                                for collections
     * @param class-string|null                                     $classOfParameter Class whose constructor the parameter the sample is built
     *                                                                                for belongs to, null for the class under test. Also set for
     *                                                                                the elements of a collection parameter and for an
     *                                                                                implementation built for an abstract parameter type, e.g.
     *                                                                                to pick the enum case a constructor requires
     * @param \Closure(class-string, string, ?class-string): object $createSample     Builds a sample of another class (class, name of the
     *                                                                                parameter, class of the parameter)
     */
    public function __construct(
        public int $sequence,
        public int $int,
        public bool $bool,
        public int $numberOfElements,
        public ?string $classOfParameter,
        private \Closure $createSample,
    ) {
    }

    /**
     * A sample of another class, built like every other sample (through the providers or the constructor).
     *
     * @template T of object
     *
     * @param class-string<T>   $class
     * @param class-string|null $classOfParameter Class whose constructor the parameter belongs to
     *
     * @return T
     */
    public function sampleOf(
        string $class,
        string $parameterName = '',
        ?string $classOfParameter = null,
    ): object {
        /** @var T */
        return ($this->createSample)($class, $parameterName, $classOfParameter);
    }
}
