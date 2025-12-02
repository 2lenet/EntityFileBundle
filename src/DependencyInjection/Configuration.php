<?php

declare(strict_types=1);

namespace Lle\EntityFileBundle\DependencyInjection;

use Lle\EntityFileBundle\Entity\EntityFile;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_STORAGE_ADAPTER = "lle_entity_file.storage.default";
    public const DEFAULT_ENTITY_FILE_CLASS = EntityFile::class;
    public const DEFAULT_ROLE = "ROLE_USER";

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder("lle_entity_file");
        $rootNode = $treeBuilder->getRootNode();
        /** @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->arrayNode("configurations")
                    ->useAttributeAsKey("name")
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode("class")->isRequired()->end()
                            ->scalarNode("storage_adapter")->defaultValue(self::DEFAULT_STORAGE_ADAPTER)->end()
                            ->scalarNode("entity_file_class")->defaultValue(self::DEFAULT_ENTITY_FILE_CLASS)->end()
                            ->scalarNode("role")->defaultValue(self::DEFAULT_ROLE)->end()
                            ->scalarNode("disposition")->defaultValue(HeaderUtils::DISPOSITION_INLINE)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
