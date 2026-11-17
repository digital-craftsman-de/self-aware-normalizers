<?php

namespace DigitalCraftsman\SelfAwareNormalizers;

use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\DoctrineTypeRegisterCompilerPass;
use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\KnownConstructionNormalizerCompilerPass;
use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\KnownConstructionNormalizerProfilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @codeCoverageIgnore
 */
final class SelfAwareNormalizersBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DoctrineTypeRegisterCompilerPass());
        // Before the services are removed, so it sees the final (autowired and bound) arguments of the fallback normalizer
        $container->addCompilerPass(new KnownConstructionNormalizerCompilerPass(), PassConfig::TYPE_BEFORE_REMOVING);
        // Before the ProfilerPass of the FrameworkBundle (priority 0), which collects the data collectors
        $container->addCompilerPass(new KnownConstructionNormalizerProfilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }
}
