<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer;

/**
 * The state of the SampleFactory while it builds a sample, for SampleProviders: the values it currently tries (it tries several
 * strategies until a class can be built) and a way to build samples of other classes.
 */
final readonly class SampleContext
{
    /**
     * @param int                                    $sequence         Increases with every sample within the class under test,
     *                                                                 e.g. for dates that must increase
     * @param int                                    $int              The int the SampleFactory currently uses for int values
     * @param bool                                   $bool             The bool the SampleFactory currently uses for bool values
     * @param int                                    $numberOfElements The number of elements the SampleFactory currently uses
     *                                                                 for collections
     * @param \Closure(class-string, string): object $createSample     Builds a sample of another class (class, name of the parameter)
     */
    public function __construct(
        public int $sequence,
        public int $int,
        public bool $bool,
        public int $numberOfElements,
        private \Closure $createSample,
    ) {
    }

    /**
     * A sample of another class, built like every other sample (through the providers or the constructor).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function sampleOf(
        string $class,
        string $parameterName = '',
    ): object {
        /** @var T */
        return ($this->createSample)($class, $parameterName);
    }
}
