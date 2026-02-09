<?php

namespace DigitalCraftsman\SelfAwareNormalizers;

use DigitalCraftsman\SelfAwareNormalizers\DependencyInjection\DoctrineTypeRegisterCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class SelfAwareNormalizersBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DoctrineTypeRegisterCompilerPass());
    }
}
