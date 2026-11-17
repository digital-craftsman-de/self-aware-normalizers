<?php

declare(strict_types=1);

namespace DigitalCraftsman\SelfAwareNormalizers\DependencyInjection;

use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug\KnownConstructionNormalizerDataCollector;
use DigitalCraftsman\SelfAwareNormalizers\Serializer\KnownConstructionNormalizer\Debug\Recorder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the profiler panel of the KnownConstructionNormalizer (KnownConstructionNormalizerDataCollector) and gives the
 * KnownConstructionNormalizer its Recorder, only in debug mode and only when the profiler is enabled. Otherwise, the KnownConstructionNormalizer
 * has no recorder and records nothing.
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
        if (!$container->hasDefinition(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID)
            || !$container->hasDefinition('profiler')
            || $container->getParameter('kernel.debug') !== true
        ) {
            return;
        }

        $container->setDefinition(self::RECORDER_SERVICE_ID, new Definition(Recorder::class))
            ->addTag('kernel.reset', [
                'method' => 'reset',
            ]);

        $container->getDefinition(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID)
            ->setArgument('$recorder', new Reference(self::RECORDER_SERVICE_ID));

        $container->setDefinition(self::DATA_COLLECTOR_SERVICE_ID, new Definition(KnownConstructionNormalizerDataCollector::class, [
            new Reference(self::RECORDER_SERVICE_ID),
            new Reference(SelfAwareNormalizersExtension::KNOWN_CONSTRUCTION_NORMALIZER_SERVICE_ID),
            [],
        ]))
            ->addTag('data_collector', [
                'template' => KnownConstructionNormalizerDataCollector::TEMPLATE,
                'id' => KnownConstructionNormalizerDataCollector::NAME,
            ]);
    }
}
