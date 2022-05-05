<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\Exception\EntityFileException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;

class EntityFileManager
{
    public function __construct(
        private ParameterBagInterface $parameters,
        private EntityManagerInterface $em,
        private ServiceLocator $storageLocator,
    )
    {
    }

    /**
     * @param resource|File|string $data
     */
    public function save(string $configName, $data, string $name)/*: EntityFileInterface*/
    {
        $storage = $this->getStorage($configName);

        $path = $configName . "/" . $name;

        switch (true) {
            case is_resource($data):
                $storage->writeStream($path, $data);
                break;
            case $data instanceof File:
                $storage->write($path, $data->getContent());
                break;
            case is_string($data):
            default:
                $storage->write($path, $data);
                break;
        }
    }

    /**
     * Retrieve a configuration's storage
     *
     * @param string $configName the configuration name
     * @return FilesystemOperator the storage operator (writer/reader)
     * @throws EntityFileException if the configuration does not exist
     */
    protected function getStorage(string $configName): FilesystemOperator
    {
        $storageAdapter = $this->getConfiguration($configName)["storage_adapter"];

        return $this->storageLocator->get($storageAdapter);
    }

    /**
     * Retrieve a configuration by name
     *
     * @param string $configName the configuration name
     * @return array the configuration
     * @throws EntityFileException if the configuration does not exist
     */
    protected function getConfiguration(string $configName): array
    {
        $configurations = $this->parameters->get("lle.entity_file.configurations");

        if (!isset($configurations[$configName])) {
            throw new EntityFileException("Configuration '$configName' does not exist.");
        }

        return $configurations[$configName];
    }
}
