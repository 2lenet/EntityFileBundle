<?php

declare(strict_types=1);

namespace Lle\EntityFileBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder("lle_entityfile");
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode("storage_adapter")->defaultValue("default.storage")->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
