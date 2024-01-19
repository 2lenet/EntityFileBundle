<?php

namespace Lle\EntityFileBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader;

class LleEntityFileExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . "/../Resources/config"));
        $loader->load("services.yaml");

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter("lle.entity_file.configurations", $config["configurations"]);

        $enabledBundles = $container->getParameter("kernel.bundles");

        /** @var mixed[] $enabledBundles */
        if (array_key_exists("LleCruditBundle", $enabledBundles)) {
            // load Crudit compatible services only if Crudit exists
            $brick = new Definition("Lle\EntityFileBundle\Crudit\Brick\EntityFileBrickFactory");
            $brick->setAutowired(true);
            $brick->setAutoconfigured(true);
            $brick->addTag("crudit.brick");
            $brick->setArguments([
                new Reference("Lle\CruditBundle\Resolver\ResourceResolver"),
                new Reference("request_stack"),
                new Reference("Lle\EntityFileBundle\Service\EntityFileLoader"),
            ]);

            $container->setDefinition("Lle\EntityFileBundle\Crudit\Brick\EntityFileBrickFactory::class", $brick);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        /** @var string $projectDir */
        $projectDir = $container->getParameter("kernel.project_dir");

        $container->prependExtensionConfig("flysystem", [
            "storages" => [
                Configuration::DEFAULT_STORAGE_ADAPTER => [
                    "adapter" => "local",
                    "options" => [
                        "directory" => $projectDir . "/data",
                        "permissions" => [
                            "file" => [
                                "public" => 511,
                                "private" => 511,
                            ],
                            "dir" => [
                                "public" => 511,
                                "private" => 511,
                            ]
                        ]
                    ],
                ],
            ],
        ]);
    }
}
