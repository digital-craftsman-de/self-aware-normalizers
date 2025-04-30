<?php

namespace DigitalCraftsman\SelfAwareNormalizers;

use DigitalCraftsman\SelfAwareNormalizers\Doctrine\DoctrineTypeRegisterCompilerPass;
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
    }
}
