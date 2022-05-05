<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Exception\EntityFileException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class EntityFileManager
{
    public function __construct(
        private ParameterBagInterface $parameters,
        private ServiceLocator $storageLocator,
        private EntityManagerInterface $em,
    )
    {
    }

    /**
     * Create and write an EntityFile
     *
     * @param resource|File|string $data
     */
    public function save(string $configName, object $object, $data, string $path): EntityFileInterface
    {
        $entityFile = $this->create($configName, $object, $path);
        $this->write($configName, $entityFile, $data);

        return $entityFile;
    }

    /**
     * Create an EntityFile
     */
    public function create(string $configName, object $object, string $path): EntityFileInterface
    {
        $config = $this->getConfiguration($configName);

        /** @var EntityFileInterface $entityFile */
        $entityFile = new $config["entity_file_class"]();

        $entityFile
            ->setObjectId($object->getId())
            ->setConfigName($configName)
            ->setPath($path);

        return $entityFile;
    }

    /**
     * Write an EntityFile
     *
     * @param resource|File|string $data
     */
    public function write(string $configName, EntityFileInterface $entityFile, $data): void
    {
        $storage = $this->getStorage($configName);
        $path = $configName . "/" . $entityFile->getPath();

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

        $mimeType = $storage->mimeType($path);
        $size = $storage->fileSize($path);

        $entityFile->setMimeType($mimeType);
        $entityFile->setSize($size);
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
