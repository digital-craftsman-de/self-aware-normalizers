<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug\KnownConstructionNormalizerDataCollector;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug\Recorder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The profiler panel of the KnownConstructionNormalizer (KnownConstructionNormalizerDataCollector) and its Recorder are registered by the
 * extension in debug mode (so the panel keeps the position of the bundle in the menu). Only now it's known whether the profiler is enabled:
 * with it, the KnownConstructionNormalizer gets the Recorder. Without it, nothing references the panel and the Recorder (the ProfilerPass
 * doesn't collect the panel, the reset of the Recorder is only a reference ignored when uninitialized), so the container removes both.
 * Without debug mode or profiler, the KnownConstructionNormalizer has no recorder and records nothing.
 *
 * Runs before the ProfilerPass of the FrameworkBundle, which collects the data collectors.
 *
 * @internal
 */
final readonly class KnownConstructionNormalizerProfilerPass implements CompilerPassInterface
{
    public const string RECORDER_SERVICE_ID = Recorder::class;
    public const string DATA_COLLECTOR_SERVICE_ID = KnownConstructionNormalizerDataCollector::class;

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::DATA_COLLECTOR_SERVICE_ID)
            || !$container->hasDefinition('profiler')
        ) {
            return;
        }

        $container->getDefinition(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID)
            ->setArgument('$recorder', new Reference(self::RECORDER_SERVICE_ID));
    }
}
