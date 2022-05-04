<?php

declare(strict_types=1);

namespace Lle\EntityFileBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_STORAGE = "entityfile.storage";

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder("lle_entity_file");
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode("configurations")
                    ->useAttributeAsKey("name")
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode("class")->isRequired()->end()
                            ->scalarNode("property")->isRequired()->end()
                            ->scalarNode("storage_adapter")->defaultValue(self::DEFAULT_STORAGE)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
