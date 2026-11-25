<?php

declare(strict_types=1);

namespace BuronDigital\SelfAwareNormalizers\Test\KnownConstructionNormalizer\Profiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * A bundle listed after the SelfAwareNormalizersBundle that registers a profiler panel of the same priority in its extension (like
 * buron-digital/deserializing-connection).
 */
final class LaterBundle extends Bundle
{
    #[\Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new LaterExtension();
    }
}

final class LaterExtension extends Extension
{
    #[\Override]
    public function load(
        array $configs,
        ContainerBuilder $container,
    ): void {
        $container->setDefinition(LaterDataCollector::class, new Definition(LaterDataCollector::class))
            ->addTag('data_collector', [
                'template' => 'later.html.twig',
                'id' => 'later',
            ]);
    }

    #[\Override]
    public function getAlias(): string
    {
        return 'later';
    }
}

final class LaterDataCollector extends DataCollector
{
    #[\Override]
    public function collect(
        Request $request,
        Response $response,
        ?\Throwable $exception = null,
    ): void {
    }

    #[\Override]
    public function getName(): string
    {
        return 'later';
    }
}
