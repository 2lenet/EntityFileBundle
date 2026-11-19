<?php

declare(strict_types=1);

namespace Lle\EntityFileBundle\DependencyInjection;

use Lle\EntityFileBundle\Entity\EntityFile;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Mime\MimeTypes;

class Configuration implements ConfigurationInterface
{
    public const DEFAULT_STORAGE_ADAPTER = "lle_entity_file.storage.default";
    public const DEFAULT_ENTITY_FILE_CLASS = EntityFile::class;
    public const DEFAULT_ROLE = "ROLE_USER";

    /**
     * Executable and script types, refused when "included_mime_types" is not set.
     * Some are the types finfo detects from the contents (text/x-php...), others the types
     * guessed from the extension when the contents are inconclusive (application/sql...).
     */
    public const DEFAULT_EXCLUDED_MIME_TYPES = [
        // PHP
        "application/x-php",
        "application/x-httpd-php",
        "text/x-php",
        // SQL
        "application/sql",
        "application/x-sql",
        "text/x-sql",
        // Shell
        "application/x-sh",
        "application/x-shellscript",
        "text/x-sh",
        "text/x-shellscript",
        "application/x-csh",
        // Other scripting languages
        "text/x-python",
        "text/x-script.python",
        "application/x-perl",
        "text/x-perl",
        "application/x-ruby",
        "text/x-ruby",
        // Windows executables and scripts
        "application/x-dosexec",
        "application/vnd.microsoft.portable-executable",
        "application/x-msdownload",
        "application/x-msi",
        "application/x-bat",
        "text/x-msdos-batch",
        "application/x-powershell",
        "text/vbscript",
        "application/hta",
        // Linux executables
        "application/x-executable",
        "application/x-pie-executable",
        "application/x-sharedlib",
        // Java
        "application/java-archive",
        "application/x-java-archive",
        // Run scripts in the browser when served inline
        "text/html",
        "application/xhtml+xml",
        "image/svg+xml",
        "text/javascript",
        "application/javascript",
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder("lle_entity_file");
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode("configurations")
                    ->useAttributeAsKey("name")
                    // Checked before the defaults are added, so that only the declared keys count
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function (array $configurations) {
                            foreach ($configurations as $name => $configuration) {
                                if (
                                    is_array($configuration)
                                    && $this->isDeclared($configuration, "included_mime_types")
                                    && $this->isDeclared($configuration, "excluded_mime_types")
                                ) {
                                    throw new \LogicException(sprintf(
                                        "Configuration \"%s\": \"included_mime_types\" and \"excluded_mime_types\""
                                        . " cannot be used together, declare only one of them.",
                                        $configuration["name"] ?? $name,
                                    ));
                                }
                            }

                            return $configurations;
                        })
                    ->end()
                    ->arrayPrototype()
                        // The default exclusions only apply when no inclusion is declared
                        ->validate()
                            ->ifTrue(fn (array $configuration) => $configuration["included_mime_types"] !== [])
                            ->then(fn (array $configuration) => ["excluded_mime_types" => []] + $configuration)
                        ->end()
                        ->children()
                            ->scalarNode("class")->isRequired()->end()
                            ->scalarNode("storage_adapter")->defaultValue(self::DEFAULT_STORAGE_ADAPTER)->end()
                            ->scalarNode("entity_file_class")->defaultValue(self::DEFAULT_ENTITY_FILE_CLASS)->end()
                            ->scalarNode("role")->defaultValue(self::DEFAULT_ROLE)->end()
                            ->scalarNode("role_write")->defaultValue(self::DEFAULT_ROLE)->end()
                            ->scalarNode("role_delete")->defaultValue(self::DEFAULT_ROLE)->end()
                            ->scalarNode("disposition")->defaultValue(HeaderUtils::DISPOSITION_INLINE)->end()
                            // Only these types are accepted, empty: no inclusion list.
                            // An unknown type has no extension, so every file would be refused.
                            ->append($this->createMimeTypesNode("included_mime_types", [], true))
                            // Every type but these is accepted, empty: every type is accepted.
                            // Unknown types are allowed: finfo detects types symfony/mime has no extension
                            // for (text/x-php...)
                            ->append($this->createMimeTypesNode("excluded_mime_types", self::DEFAULT_EXCLUDED_MIME_TYPES, false))
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * @param string[] $default
     * @param bool $knownOnly refuse the types symfony/mime has no extension for
     */
    private function createMimeTypesNode(string $name, array $default, bool $knownOnly): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $prototype = $node
            ->defaultValue($default)
            ->scalarPrototype()
                ->beforeNormalization()->ifString()->then(fn (string $type) => strtolower(trim($type)))->end()
        ;

        if ($knownOnly) {
            $prototype
                ->validate()
                    ->ifTrue(fn (string $type) => MimeTypes::getDefault()->getExtensions($type) === [])
                    ->then(fn (string $type) => throw new \InvalidArgumentException(
                        "Unknown MIME type \"$type\": symfony/mime knows no extension for it."
                    ))
                ->end()
            ;
        }

        return $node;
    }

    /**
     * Keys are not normalized yet: "included-mime-types" is the same key as "included_mime_types".
     *
     * @param mixed[] $configuration
     */
    private function isDeclared(array $configuration, string $key): bool
    {
        return array_key_exists($key, $configuration) || array_key_exists(str_replace("_", "-", $key), $configuration);
    }
}
