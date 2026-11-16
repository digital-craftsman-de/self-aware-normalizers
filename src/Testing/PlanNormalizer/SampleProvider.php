<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer;

/**
 * Samples of an app for the SampleFactory of PlanNormalizerEquivalenceTestCase: the SampleFactory builds every class through its
 * constructor with generated values, which isn't enough for value objects whose validation depends on several values (e.g. a date range
 * whose start must be before its end), that have no public constructor (e.g. ids built through a named constructor) or that need a
 * resource (e.g. an uploaded file). A provider returns a sample for those and null for everything else. Providers are asked in the order
 * returned by PlanNormalizerEquivalenceTestCase::sampleProviders(), before the built-in samples of the SampleFactory.
 */
interface SampleProvider
{
    /**
     * A sample of the class or null to leave the class to the next provider and the SampleFactory.
     *
     * @param class-string $class
     * @param string       $parameterName Name of the parameter the sample is built for (empty for the class under test), e.g. to build
     *                                    a deadline before other dates
     */
    public function sample(
        string $class,
        string $parameterName,
        SampleContext $context,
    ): ?object;

    /**
     * A string for a parameter of this name or null to leave it to the next provider and the SampleFactory (which generates strings by
     * the name, e.g. an email address for a name containing "email").
     */
    public function string(string $parameterName): ?string;
}
