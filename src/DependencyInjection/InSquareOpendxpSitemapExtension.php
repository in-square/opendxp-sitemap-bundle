<?php

namespace InSquare\OpendxpSitemapBundle\DependencyInjection;

use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class InSquareOpendxpSitemapExtension extends Extension
{
    public function getAlias(): string
    {
        return 'in_square_opendxp_sitemap';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('in_square_opendxp_sitemap', $config);
        $container->registerForAutoconfiguration(ObjectGeneratorInterface::class)
            ->addTag('in_square_opendxp_sitemap.object_generator');

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }
}
