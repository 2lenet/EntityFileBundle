<?php

declare(strict_types=1);

namespace Lle\EntityFileBundle\DependencyInjection;

use Lle\EntityFileBundle\Entity\EntityFile;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Mime\MimeTypes;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_STORAGE_ADAPTER = "lle_entity_file.storage.default";
    public const DEFAULT_ENTITY_FILE_CLASS = EntityFile::class;
    public const DEFAULT_ROLE = "ROLE_USER";
    // image/jpeg covers the "jpg" and "jpeg" extensions
    public const DEFAULT_MIME_TYPES = ["image/jpeg", "image/png", "image/webp", "application/pdf"];

    public function getConfigTreeBuilder(): TreeBuilder
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
                            ->scalarNode("storage_adapter")->defaultValue(self::DEFAULT_STORAGE_ADAPTER)->end()
                            ->scalarNode("entity_file_class")->defaultValue(self::DEFAULT_ENTITY_FILE_CLASS)->end()
                            ->scalarNode("role")->defaultValue(self::DEFAULT_ROLE)->end()
                            ->scalarNode("role_write")->defaultValue(self::DEFAULT_ROLE)->end()
                            ->scalarNode("role_delete")->defaultValue(self::DEFAULT_ROLE)->end()
                            ->scalarNode("disposition")->defaultValue(HeaderUtils::DISPOSITION_INLINE)->end()
                            // empty: every type is accepted
                            ->arrayNode("mime_types")
                                ->defaultValue(self::DEFAULT_MIME_TYPES)
                                ->scalarPrototype()
                                    ->beforeNormalization()->ifString()->then(fn (string $type) => strtolower(trim($type)))->end()
                                    // an unknown type has no extension, so every file would be refused
                                    ->validate()
                                        ->ifTrue(fn (string $type) => MimeTypes::getDefault()->getExtensions($type) === [])
                                        ->then(fn (string $type) => throw new \InvalidArgumentException(
                                            "Unknown MIME type \"$type\": symfony/mime knows no extension for it."
                                        ))
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
