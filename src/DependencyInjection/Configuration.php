<?php

namespace InSquare\OpendxpSitemapBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('in_square_opendxp_sitemap');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('sites')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('id')->isRequired()->end()
                            ->scalarNode('host')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('languages')
                                ->scalarPrototype()->end()
                                ->requiresAtLeastOneElement()
                            ->end()
                            ->arrayNode('objects')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                    ->requiresAtLeastOneElement()
                ->end()
                ->arrayNode('object_generators')
                    ->beforeNormalization()
                        ->ifTrue(static function ($value): bool {
                            return \is_array($value) && array_is_list($value);
                        })
                        ->then(static function (array $value): array {
                            $normalized = [];
                            foreach ($value as $entry) {
                                if (!\is_array($entry)) {
                                    continue;
                                }

                                foreach ($entry as $key => $class) {
                                    $normalized[$key] = $class;
                                }
                            }

                            return $normalized;
                        })
                    ->end()
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('output')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('dir')
                            ->defaultValue('%kernel.project_dir%/public/sitemap')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('max_urls_per_file')
                            ->defaultValue(50000)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
