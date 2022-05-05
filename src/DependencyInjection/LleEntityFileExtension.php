<?php

namespace Lle\EntityFileBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader;

class LleEntityFileExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . "/../Resources/config"));
        $loader->load("services.yaml");

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter("lle.entity_file.configurations", $config["configurations"]);
    }

    public function prepend(ContainerBuilder $container)
    {
        $container->prependExtensionConfig("flysystem", [
            "storages" => [
                Configuration::DEFAULT_STORAGE_ADAPTER => [
                    "adapter" => "local",
                    "options" => [
                        "directory" => $container->getParameter("kernel.project_dir") . "/data",
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
